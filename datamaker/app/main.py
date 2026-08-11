"""zxt-datamaker - 带工具的造数据聊天 AI（小型 Astrbot）
- 自由对话：用户可聊天、提问、要求生成/修改测试数据
- 掌握工具：write_file/read_file/list_files（会话工作目录）、run_generator/test_checker
- 上下文：知道题目题面与正解(std)；用户要求都在对话中自然表达
"""
import os, json, re, shutil, subprocess, time, uuid, threading, urllib.request
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Optional
from collections import defaultdict, deque

app = FastAPI(title="zxt-datamaker", version="3.0.0")

DEEPSEEK_URL = "https://api.deepseek.com/chat/completions"
DATA_ROOT = os.environ.get("DATA_ROOT", "/data/problems")
GEN_TIMEOUT = 60
STD_TIMEOUT_BASE = 15
STDS = {"python3": None, "c": "-std=c11", "cpp14": "-std=c++14", "cpp17": "-std=c++17", "cpp20": "-std=c++20"}

# ---------- 会话与事件（持久化） ----------
SESSION_DIR = os.environ.get("SESSION_DIR", "/data/ai_sessions")
os.makedirs(SESSION_DIR, exist_ok=True)

sessions = {}
events = defaultdict(deque)
_event_seq = defaultdict(int)

def _persist(sid):
    try:
        with open(os.path.join(SESSION_DIR, sid + ".json"), "w", encoding="utf-8") as f:
            evs = list(events[sid])[-5000:]
            json.dump({"session": sessions[sid], "events": evs, "next_seq": _event_seq[sid]},
                      f, ensure_ascii=False)
    except Exception:
        pass

def _load_sessions():
    try:
        for fn in os.listdir(SESSION_DIR):
            if not fn.endswith(".json"):
                continue
            sid = fn[:-5]
            try:
                with open(os.path.join(SESSION_DIR, fn), "r", encoding="utf-8") as f:
                    data = json.load(f)
                sessions[sid] = data["session"]
                events[sid] = deque(data.get("events", []))
                _event_seq[sid] = max(data.get("next_seq", len(events[sid])), len(events[sid]))
            except Exception:
                continue
    except Exception:
        pass

_persist_len = {}

def _persist_loop():
    while True:
        time.sleep(6)
        for sid in list(sessions):
            n = len(events[sid])
            if _persist_len.get(sid) != n:
                _persist(sid)
                _persist_len[sid] = n

def cleanup_old_sessions(max_age=2592000):   # 30 天
    now = time.time()
    for sid in list(sessions):
        try:
            if now - sessions[sid].get("created", 0) > max_age:
                sessions.pop(sid, None); events.pop(sid, None); _event_seq.pop(sid, None)
                try: os.remove(os.path.join(SESSION_DIR, sid + ".json"))
                except Exception: pass
        except Exception:
            pass

def _cleanup_loop():
    while True:
        time.sleep(1800)
        cleanup_old_sessions()

_load_sessions()
threading.Thread(target=_persist_loop, daemon=True).start()
threading.Thread(target=_cleanup_loop, daemon=True).start()

def push_event(sid, typ, data):
    seq = _event_seq[sid]
    _event_seq[sid] += 1
    events[sid].append({"seq": seq, "type": typ, "data": data})
    return seq

# ---------- 工具 ----------
WS_ROOT = os.environ.get("WS_ROOT", "/data/ai_ws")   # 共享卷，容器重建不丢

def session_ws(sid):
    ws = os.path.join(WS_ROOT, sid)
    os.makedirs(ws, exist_ok=True)
    return ws

def clean_analysis(t):
    t = re.sub(r"[*#_`|]", "", t)
    t = re.sub(r"^\s{0,3}[-+>]\s+", "", t, flags=re.M)
    t = re.sub(r"^\s*\d+\.\s+", "", t, flags=re.M)
    return t.strip()

def run(cmd, timeout, cwd, stdin_data=None):
    try:
        p = subprocess.run(cmd, input=stdin_data.encode() if stdin_data is not None else None,
                           capture_output=True, timeout=timeout, cwd=cwd)
        return p.returncode, p.stdout.decode(errors="replace"), p.stderr.decode(errors="replace")
    except subprocess.TimeoutExpired:
        return -1, "", "timeout"
    except Exception as e:
        return -2, "", str(e)

def hint_compile_err(e):
    if "undefined reference to `main'" in e or "undefined reference to 'main'" in e:
        return "错误：代码缺少 int main() 入口函数（请补上 int main() { ... return 0; }）"
    if "collect2: error" in e:
        return "链接错误（collect2），常见原因：缺 main / 缺符号，请检查代码完整性"
    return e

def exec_tool(sid, name, args):
    ws = session_ws(sid)
    try:
        if name == "write_file":
            p = os.path.realpath(os.path.join(ws, str(args.get("path", ""))))
            if p != ws and not p.startswith(ws + os.sep):
                return "错误：路径越界，只能写工作目录内文件"
            os.makedirs(os.path.dirname(p), exist_ok=True)
            with open(p, "w", encoding="utf-8") as f:
                f.write(str(args.get("content", "")))
            return f"已写入 {args.get('path')}（{len(str(args.get('content','')))} 字符）"
        if name == "read_file":
            p = os.path.realpath(os.path.join(ws, str(args.get("path", ""))))
            if p != ws and not p.startswith(ws + os.sep):
                return "错误：路径越界"
            if not os.path.exists(p):
                return "文件不存在"
            with open(p, encoding="utf-8", errors="replace") as f:
                return f.read()[:4000]
        if name == "list_files":
            fs = sorted(os.listdir(ws))
            return "\n".join(fs) if fs else "（空目录）"
        if name == "run_generator":
            count = max(1, min(int(args.get("count", 10)), 50))
            if not os.path.exists(os.path.join(ws, "gen.py")):
                return "错误：工作目录没有 gen.py（先用 write_file 写入）"
            has_sol = os.path.exists(os.path.join(ws, "sol.py")) or os.path.exists(os.path.join(ws, "sol.cpp"))
            if not has_sol:
                return "错误：没有 sol.py/sol.cpp（先用 write_file 写入标准解法）"
            std_cmd = None
            if os.path.exists(os.path.join(ws, "sol.cpp")):
                rc, o, e = run(["g++", "-std=c++17", "-O2", "-o", os.path.join(ws, "sol"), os.path.join(ws, "sol.cpp"), "-lm"], 60, ws)
                if rc != 0:
                    return "sol.cpp 编译失败：\n" + hint_compile_err(e[-400:])
                std_cmd = [os.path.join(ws, "sol")]
            else:
                std_cmd = ["python3", os.path.join(ws, "sol.py")]
            ok = 0
            msgs = []
            for i in range(1, count + 1):
                rc, o, e = run(["python3", os.path.join(ws, "gen.py")], GEN_TIMEOUT, ws)
                if rc != 0:
                    msgs.append(f"第{i}组 gen 失败: {e.strip()[:100]}")
                    continue
                open(os.path.join(ws, f"{i}.in"), "w", encoding="utf-8").write(o)
                rc, o2, e2 = run(std_cmd, STD_TIMEOUT_BASE + 15, ws, stdin_data=o)
                if rc != 0:
                    msgs.append(f"第{i}组 sol 失败: {e2.strip()[:100]}")
                    continue
                open(os.path.join(ws, f"{i}.out"), "w", encoding="utf-8").write(o2)
                open(os.path.join(ws, f"{i}.score"), "w").write(str(round(100.0 / count, 2)))
                ok += 1
            return f"生成完成：{ok}/{count} 组" + (("；" + "; ".join(msgs[:3])) if msgs else "")
        if name == "test_checker":
            ck_py = os.path.join(ws, "checker.py")
            ck_cpp = os.path.join(ws, "checker.cpp")
            if os.path.exists(ck_cpp):
                # testlib checker（C++）：编译后逐个运行 checker in out ans
                exe = os.path.join(ws, "checker_exe")
                rc, o, e = run(["g++", "-O2", "-o", exe, ck_cpp, "-I", "/app"], 60, ws)
                if rc != 0:
                    return "checker.cpp 编译失败：\n" + hint_compile_err(e[-400:])
                files = sorted(f for f in os.listdir(ws) if re.fullmatch(r"\d+\.in", f))
                if not files:
                    return "错误：没有数据（先 run_generator）"
                fails = []
                for f in files:
                    idx = int(f[:-3])
                    rc, o, e = run([exe, os.path.join(ws, f),
                                    os.path.join(ws, f"{idx}.out"), os.path.join(ws, f"{idx}.out")], 10, ws)
                    if rc != 0:
                        fails.append(f"第{idx}组")
                if fails:
                    return f"checker 自检失败（标准答案未通过）：{', '.join(fails)}"
                return f"checker 自检通过：{len(files)} 组标准答案全部通过"
            if not os.path.exists(ck_py):
                return "错误：没有 checker.py/checker.cpp（先用 write_file 写入）"
            ns = {}
            exec(open(ck_py, encoding="utf-8").read(), ns)
            ck = ns.get("check")
            if not callable(ck):
                return "错误：checker 缺少 check(input, output, expected) 函数"
            files = sorted(f for f in os.listdir(ws) if re.fullmatch(r"\d+\.in", f))
            if not files:
                return "错误：没有数据（先 run_generator）"
            fails = []
            for f in files:
                idx = int(f[:-3])
                it = open(os.path.join(ws, f), encoding="utf-8", errors="replace").read()
                ot = open(os.path.join(ws, f"{idx}.out"), encoding="utf-8", errors="replace").read()
                try:
                    r = ck(it, ot, ot)
                    passed = r if isinstance(r, bool) else (bool(r[0]) if isinstance(r, (list, tuple)) else bool(r))
                except Exception as e:
                    passed = False
                if not passed:
                    fails.append(f"第{idx}组")
            if fails:
                return f"checker 自检失败（标准答案未通过）：{', '.join(fails)}（checker 可能自创了题面没有的约束）"
            return f"checker 自检通过：{len(files)} 组标准答案全部通过"
        return f"未知工具: {name}"
    except Exception as e:
        return f"工具执行异常: {e}"

TOOLS = [
    {"type": "function", "function": {"name": "write_file",
        "description": "在工作目录写文件（编写/修改 gen.py、sol.py、sol.cpp、checker.py、checker.cpp 等；checker.cpp 可用 testlib.h 编写，编译时自动包含）。路径必须是相对文件名。",
        "parameters": {"type": "object", "properties": {"path": {"type": "string", "description": "相对文件名，如 gen.py"}, "content": {"type": "string"}}, "required": ["path", "content"]}}},
    {"type": "function", "function": {"name": "read_file",
        "description": "读取工作目录文件内容（限 4000 字符）。",
        "parameters": {"type": "object", "properties": {"path": {"type": "string"}}, "required": ["path"]}}},
    {"type": "function", "function": {"name": "list_files",
        "description": "列出工作目录所有文件。",
        "parameters": {"type": "object", "properties": {}}}},
    {"type": "function", "function": {"name": "run_generator",
        "description": "运行工作目录 gen.py 生成 count 组数据（用 sol.py 或 sol.cpp 产出 .out）。这是唯一能执行代码的方式。",
        "parameters": {"type": "object", "properties": {"count": {"type": "integer", "description": "数据组数"}}, "required": ["count"]}}},
    {"type": "function", "function": {"name": "test_checker",
        "description": "用已生成的数据对 checker（checker.py 的 check 函数，或 checker.cpp 的 testlib 程序）做自检（标准答案必须全部通过）。这是唯一能测试 checker 的方式。",
        "parameters": {"type": "object", "properties": {}}}},
]

def chat_stream_tools(sid, messages):
    """流式聊天 + 工具循环：AI 文本增量推送（reply_delta），工具即时执行；返回最终 content"""
    s = sessions[sid]
    for _ in range(25):
        body = json.dumps({"model": "deepseek-chat", "messages": messages, "temperature": 0.7,
                           "stream": True, "tools": TOOLS}).encode()
        req = urllib.request.Request(DEEPSEEK_URL, data=body, headers={
            "Content-Type": "application/json", "Authorization": "Bearer " + s["api_key"]})
        content = ""
        tool_calls = {}
        with urllib.request.urlopen(req, timeout=300) as r:
            for raw in r:
                line = raw.decode(errors="replace").strip()
                if not line.startswith("data:"):
                    continue
                d = line[5:].strip()
                if d == "[DONE]":
                    break
                try:
                    chunk = json.loads(d)
                except Exception:
                    continue
                delta = ((chunk.get("choices") or [{}])[0].get("delta") or {})
                if delta.get("content"):
                    content += delta["content"]
                    c = clean_analysis(delta["content"])
                    if c:
                        push_event(sid, "reply_delta", c)
                for tc in delta.get("tool_calls") or []:
                    idx_t = tc.get("index", 0)
                    t = tool_calls.setdefault(idx_t, {"id": "", "name": "", "args": ""})
                    fn = tc.get("function") or {}
                    if tc.get("id"):
                        t["id"] = tc["id"]
                    if fn.get("name"):
                        t["name"] += fn["name"]
                        push_event(sid, "tool_delta", {"name": t["name"], "args_delta": ""})
                    if fn.get("arguments"):
                        t["args"] += fn["arguments"]
        if tool_calls:
            tcs = [{"id": t["id"], "type": "function",
                    "function": {"name": t["name"], "arguments": t["args"]}}
                   for _, t in sorted(tool_calls.items())]
            messages.append({"role": "assistant", "content": content or None, "tool_calls": tcs})
            for t in tcs:
                try:
                    args = json.loads(t["function"]["arguments"] or "{}")
                except Exception:
                    args = {"_raw": t["function"]["arguments"]}
                result = exec_tool(sid, t["function"]["name"], args)
                status = "ok" if not str(result).startswith(("错误", "异常", "没有", "未知")) else "err"
                push_event(sid, "tool", {"name": t["function"]["name"], "args": args,
                                         "result": str(result)[:400], "status": status})
                messages.append({"role": "tool", "tool_call_id": t["id"], "content": result})
            continue
        return content
    raise RuntimeError("AI 工具调用次数过多（超过 25 次）")

def do_chat_round(sid):
    """对话回合：AI 回复（聊天/工具自由），结束推 reply + done"""
    s = sessions[sid]
    try:
        system = s.get("system", "你是 ZXT OJ 的 AI 助手。")
        messages = [{"role": "system", "content": system}] + s.get("messages", [])
        content = chat_stream_tools(sid, messages)
        content = content.strip()
        s["messages"].append({"role": "assistant", "content": content or "（无文字回复）"})
        push_event(sid, "reply", content)
        s["last_result"] = {"ok": True, "message": (content or "")[:120]}
        push_event(sid, "done", s["last_result"])
    except urllib.error.HTTPError as e:
        detail = e.read().decode(errors="replace")
        try:
            msg = json.loads(detail)["error"]["message"]
        except Exception:
            msg = detail[:200]
        push_event(sid, "error", f"DeepSeek 调用失败: {msg}")
    except Exception as e:
        push_event(sid, "error", str(e))

# ---------- 模型 ----------
class ChatStartReq(BaseModel):
    problem_id: str
    api_key: str
    title: str = ""
    description: str = ""
    input_format: str = ""
    output_format: str = ""
    hints: str = ""
    std_code: str = ""            # 正解（std），AI 知道它
    std_lang: str = "python3"
    time_limit: float = 2.0
    memory_limit: int = 128

class ChatMsgReq(BaseModel):
    session_id: str
    user_msg: str

# ---------- 端点 ----------
@app.get("/health")
async def health():
    return {"status": "ok", "service": "zxt-datamaker", "sessions": len(sessions)}

@app.post("/chat/start")
async def chat_start(req: ChatStartReq):
    pid = re.sub(r"[^a-zA-Z0-9_-]", "", req.problem_id)
    if not pid:
        raise HTTPException(400, "缺少题目编号")
    if not req.api_key:
        raise HTTPException(400, "缺少 DeepSeek API Key")
    sid = uuid.uuid4().hex
    std_txt = f"\n【本题正解 std（{req.std_lang}）】\n{req.std_code[:3000]}\n" if req.std_code.strip() else ""
    system = (
        f"你是 ZXT OJ 的 AI 助手（一个小型 Astrbot），正在辅助出题人处理题目 {pid}。\n"
        f"【题目 {pid}】\n题目名称: {req.title}\n时间限制: {req.time_limit}s 内存限制: {req.memory_limit}MB\n"
        f"题面: {req.description}\n输入格式: {req.input_format}\n输出格式: {req.output_format}\n提示: {req.hints}\n"
        + std_txt +
        "\n你掌握以下工具（自主决定何时使用）：\n"
        "- write_file/read_file/list_files：读写你的工作目录（写生成器 gen.py、标准解法 sol.py/sol.cpp、checker.py）\n"
        "- run_generator：运行生成器产出测试数据（唯一执行代码的方式）\n"
        "- test_checker：自检 checker（标准答案必须通过）\n"
        "你可以和用户自由聊天、回答问题、讨论题目；当用户要求生成/修改测试数据时，"
        "用工具完成（写文件→生成→自检），并告知用户结果。数据会由用户点击'应用数据'落盘。"
    )
    sessions[sid] = {
        "created": time.time(), "pid": pid, "api_key": req.api_key,
        "system": system, "messages": [], "last_result": None,
        "round_start": 0,
    }
    push_event(sid, "info", f"AI 助手已就绪：题目 {pid}（可自由聊天，或让我生成/修改测试数据）")
    return {"ok": True, "session_id": sid}

@app.post("/chat/message")
async def chat_message(req: ChatMsgReq):
    sid = req.session_id
    if sid not in sessions:
        raise HTTPException(404, "会话不存在或已过期")
    msg = req.user_msg.strip()
    if not msg:
        raise HTTPException(400, "消息不能为空")
    sessions[sid]["messages"].append({"role": "user", "content": msg})
    sessions[sid]["round_start"] = _event_seq[sid]
    sessions[sid]["last_result"] = None
    push_event(sid, "user", msg)
    threading.Thread(target=do_chat_round, args=(sid,), daemon=True).start()
    return {"ok": True, "session_id": sid}

@app.get("/chat/events")
async def chat_events(session_id: str, since: int = 0):
    if session_id not in sessions:
        raise HTTPException(404, "会话不存在")
    rs = sessions[session_id].get("round_start", 0)
    evs = [e for e in events[session_id] if e["seq"] >= max(since, rs)]
    evs = evs[:300]
    done = any(e["type"] in ("done", "error") and e["seq"] >= rs for e in events[session_id])
    return {"events": evs, "done": done, "next_since": evs[-1]["seq"] + 1 if evs else since}

@app.get("/chat/history")
async def chat_history(session_id: str):
    if session_id not in sessions:
        raise HTTPException(404, "会话不存在或已过期")
    evs = list(events[session_id])
    return {"events": evs, "next_since": _event_seq[session_id], "session": {
        "pid": sessions[session_id].get("pid", ""),
    }}

@app.post("/chat/apply")
async def chat_apply(session_id: str):
    """把会话工作目录生成的测试数据落盘到 /data/problems/{pid}（用户点'应用数据'时调用）"""
    if session_id not in sessions:
        raise HTTPException(404, "会话不存在或已过期")
    s = sessions[session_id]
    ws = session_ws(session_id)
    files = [f for f in os.listdir(ws) if re.fullmatch(r"\d+\.in", f)]
    if not files:
        raise HTTPException(400, "工作目录没有测试数据，请先让 AI 生成")
    pid = s["pid"]
    out_dir = os.path.join(DATA_ROOT, pid)
    os.makedirs(out_dir, exist_ok=True)
    for f in os.listdir(out_dir):
        if re.fullmatch(r"\d+\.(in|out|score)", f):
            os.remove(os.path.join(out_dir, f))
    from shutil import copyfile as _cp
    cnt = 0
    for f in os.listdir(ws):
        if re.fullmatch(r"\d+\.(in|out|score)", f):
            _cp(os.path.join(ws, f), os.path.join(out_dir, f)); cnt += 1
    if os.path.exists(os.path.join(ws, "checker.py")):
        _cp(os.path.join(ws, "checker.py"), os.path.join(out_dir, "checker.py"))
    n = max(int(f[:-3]) for f in files)
    open(os.path.join(out_dir, "config.yaml"), "w", encoding="utf-8").write(
        f"name: {pid}\ntime_limit: {s.get('time_limit', 2.0)}\nmemory_limit: {s.get('memory_limit', 128)}\n"
        f"test_cases: {n}\nscoring_mode: default\n")
    return {"ok": True, "message": f"已应用 {n} 个测试点到题目 {pid}", "n": n, "copied": cnt}
