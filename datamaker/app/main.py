"""zxt-datamaker - 独立造数据容器（聊天工作台版）
- /gen_data        旧同步接口（兼容 ai_gen_task.php）
- /chat/start      创建 AI 造数据会话（题目信息+配置）
- /chat/message    发送用户消息（多轮上下文），DeepSeek 流式生成 + 运行生成器
- /chat/events     轮询会话事件（流式输出/进度/代码/结果）
- /chat/apply      应用已生成数据（同步 DB 由 OJ 侧做，这里仅确认落盘）
用户可提供 std（python3/c/cpp14/cpp17/cpp20）；DeepSeek 强制 json_object。
"""
import os, json, re, shutil, subprocess, time, uuid, threading, urllib.request
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Optional
from collections import defaultdict, deque

app = FastAPI(title="zxt-datamaker", version="2.0.0")

DEEPSEEK_URL = "https://api.deepseek.com/chat/completions"
DATA_ROOT = os.environ.get("DATA_ROOT", "/data/problems")
GEN_TIMEOUT = 60
STD_TIMEOUT_BASE = 15
STDS = {"python3": None, "c": "-std=c11", "cpp14": "-std=c++14", "cpp17": "-std=c++17", "cpp20": "-std=c++20"}

# ---------- 会话与事件（持久化到 /data/ai_sessions，容器重启不丢）----------
SESSION_DIR = os.environ.get("SESSION_DIR", "/data/ai_sessions")
os.makedirs(SESSION_DIR, exist_ok=True)

sessions = {}
events = defaultdict(deque)     # session_id -> deque of {"seq":int,"type":str,"data":obj}
_event_seq = defaultdict(int)

def _persist(sid):
    try:
        with open(os.path.join(SESSION_DIR, sid + ".json"), "w", encoding="utf-8") as f:
            evs = list(events[sid])[-5000:]   # 只持久化最近 5000 条，防大文件拖慢写盘
            json.dump({"session": sessions[sid], "events": evs,
                       "next_seq": _event_seq[sid]}, f, ensure_ascii=False)
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

def _persist_loop():
    while True:
        time.sleep(3)
        for sid in list(sessions):
            _persist(sid)

_load_sessions()
threading.Thread(target=_persist_loop, daemon=True).start()
def _cleanup_loop():
    while True:
        time.sleep(1800)
        cleanup_old_sessions()
threading.Thread(target=_cleanup_loop, daemon=True).start()

def push_event(sid, typ, data):
    seq = _event_seq[sid]
    _event_seq[sid] += 1
    events[sid].append({"seq": seq, "type": typ, "data": data})
    return seq

def cleanup_old_sessions(max_age=86400):
    """清理超过 max_age 秒的会话文件"""
    now = time.time()
    for sid in list(sessions):
        try:
            if now - sessions[sid].get("created", 0) > max_age:
                sessions.pop(sid, None)
                events.pop(sid, None)
                _event_seq.pop(sid, None)
                for ext in (".json",):
                    try: os.remove(os.path.join(SESSION_DIR, sid + ext))
                    except Exception: pass
        except Exception:
            pass

def session_info(req, pid):
    return {
        "created": time.time(),
        "pid": pid, "api_key": req.api_key, "count": req.count,
        "need_checker": req.need_checker, "checker_req": req.checker_req,
        "extra_req": req.extra_req, "std_code": req.std_code, "std_lang": req.std_lang,
        "title": req.title, "description": req.description, "input_format": req.input_format,
        "output_format": req.output_format, "hints": req.hints,
        "time_limit": req.time_limit, "memory_limit": req.memory_limit,
        "messages": [],          # DeepSeek 多轮上下文
        "last_user_req": "",       # 本轮最新修改要求（注入 prompt 尾部，确保被响应）
        "gen_code": "", "sol_code": "", "ck_code": "", "config_yaml": "",
        "last_result": None,
    }

def build_prompt(sid, n):
    s = sessions[sid]
    user_std = s["std_code"].strip() != ""
    desc = (f"题目编号: {s['pid']}\n题目名称: {s['title']}\n"
            f"时间限制: {s['time_limit']} 秒\n内存限制: {s['memory_limit']} MB\n"
            f"题面: {s['description']}\n输入格式: {s['input_format']}\n"
            f"输出格式: {s['output_format']}\n提示: {s['hints']}\n")
    fields = ('"analysis":"（轻度思考）先用 3-5 句简要分析：题目的关键约束、数据分布策略（边界/随机/大数据/特殊构造）、'
              f'如何保证 {n} 组数据有区分度。只写要点，用纯文本不要 markdown 标记（不要 **、#、列表符号），不要废话。"'
              ',"gen_code":"Python3 数据生成器代码。每次运行向 stdout 输出一组随机、合法的输入数据'
              '（覆盖边界与大数据），不要输出任何多余内容。要求每组运行结果具有随机区分度，'
              f'并覆盖 {n} 组数据规模的多样性"')
    if user_std:
        std_note = "标准解法(std)已由用户提供，无需生成 sol_code。"
    else:
        std_note = "请同时生成标准解法 sol_code（从 stdin 读输入、向 stdout 输出答案）。若用户 std 语言是 C/C++，sol_code 必须是完整程序：含 int main()（从 stdin 读入、stdout 输出），不要只写函数或片段。"
        fields += ',"sol_code":"标准解法代码（与用户 std 语言一致：Python3 直接可运行；C/C++ 必须含 int main() 完整可编译程序）。从 stdin 读取输入，向 stdout 输出正确答案，不要输出多余内容"'
    fields += ('",config_yaml":"yaml 文本，含 name/time_limit/memory_limit/test_cases 字段；'
               '评分使用默认模式，写 scoring_mode: default，不需要写 scores 数组（每个测试点默认平分）"')
    if s["need_checker"]:
        ck_req = s["checker_req"].strip() or "按题意标准比对，必要时放宽浮点误差"
        fields += (',"checker_code":"Python3 特殊判题 checker 代码。必须定义函数 check(input, output, expected)，'
                   '参数均为字符串：input=测试输入、output=选手输出、expected=标准答案。'
                   '返回 True/False，或返回 (是否通过:bool, 提示信息:str, 得分占比:float)。'
                   '【关键要求】只验证题目明确要求的条件（合法性/唯一性/数值误差等），'
                   '绝不自行添加题面没有的额外约束或对输出格式的臆测；'
                   '对 expected 标准答案必须返回 True（它是正确解法的输出）。'
                   f'需要特殊判定的规则：{ck_req}。不要写 main 或读文件"')
    extra_note = ""
    if s["extra_req"].strip():
        extra_note = f"\n用户对数据的额外要求（务必逐条满足）：{s['extra_req'].strip()}\n"
    if s.get("last_user_req", "").strip():
        extra_note += f"\n【用户本轮修改要求——必须据此修改，不得忽略】{s['last_user_req'].strip()}\n"
    if s.get("gen_code"):
        extra_note += "\n当前数据生成器代码（请在它的基础上按上述要求修改，不要推翻重写）：\n" + s["gen_code"][:1000] + "\n"
    prompt = ("你是 OJ 出题助手。根据以下题目信息生成测试数据构造代码。\n\n" + desc + "\n"
              + std_note + "\n" + extra_note
              + "请严格只返回一个 JSON 对象（禁止 markdown 代码块、禁止任何解释文字、禁止多余字段），格式：\n{"
              + fields + "}\n"
              + f"共需生成 {n} 组测试数据。")
    return prompt, user_std

def clean_analysis(t: str) -> str:
    """清洗 AI 思考/消息输出中的 markdown 标记（增量安全：逐字符删标记字符）"""
    t = re.sub(r"[*#_`|]", "", t)
    t = re.sub(r"^\s{0,3}[-+>]\s+", "", t, flags=re.M)
    t = re.sub(r"^\s*\d+\.\s+", "", t, flags=re.M)
    return t.strip()

def extract_json(content: str) -> dict:
    content = content.strip()
    content = re.sub(r"^```(?:json)?\s*", "", content)
    content = re.sub(r"\s*```$", "", content)
    if not content.startswith("{"):
        a, b = content.find("{"), content.rfind("}")
        if a != -1 and b > a:
            content = content[a:b + 1]
    return json.loads(content)

def run(cmd, timeout, cwd, stdin_data=None):
    try:
        p = subprocess.run(cmd, input=stdin_data.encode() if stdin_data is not None else None,
                           capture_output=True, timeout=timeout, cwd=cwd)
        return p.returncode, p.stdout.decode(errors="replace"), p.stderr.decode(errors="replace")
    except subprocess.TimeoutExpired:
        return -1, "", "timeout"
    except Exception as e:
        return -2, "", str(e)

def fetch_generation(sid, messages):
    """DeepSeek 流式生成并解析 JSON；流式失败自动重试（最多 3 次），最后兜底非流式"""
    s = sessions[sid]
    last_err = None
    for attempt in range(3):
        try:
            body = json.dumps({"model": "deepseek-chat", "messages": messages,
                               "temperature": 0.6, "stream": True,
                               "response_format": {"type": "json_object"}}).encode()
            req = urllib.request.Request(DEEPSEEK_URL, data=body, headers={
                "Content-Type": "application/json", "Authorization": "Bearer " + s["api_key"]})
            content = ""
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
                        delta = ((chunk.get("choices") or [{}])[0].get("delta") or {}).get("content", "")
                    except Exception:
                        continue
                    if delta:
                        content += delta
                        push_event(sid, "analysis_delta", clean_analysis(delta))
            push_event(sid, "analysis_end", "")
            return extract_json(content)
        except Exception as e:
            last_err = e
            push_event(sid, "analysis_delta", f"\n[流式中断，第 {attempt+1} 次重试...]\n")
            continue
    # 最后兜底：非流式一次
    try:
        body = json.dumps({"model": "deepseek-chat", "messages": messages,
                           "temperature": 0.6, "stream": False,
                           "response_format": {"type": "json_object"}}).encode()
        req = urllib.request.Request(DEEPSEEK_URL, data=body, headers={
            "Content-Type": "application/json", "Authorization": "Bearer " + s["api_key"]})
        with urllib.request.urlopen(req, timeout=300) as r:
            data = json.loads(r.read())
        content = ((data.get("choices") or [{}])[0].get("message") or {}).get("content", "")
        push_event(sid, "analysis_end", "")
        return extract_json(content)
    except Exception as e2:
        raise RuntimeError(f"DeepSeek 生成失败（流式 {3} 次 + 非流式 1 次）: {e2} / {last_err}")

# ---------- AI 工具（function calling）：文件读写限于会话工作目录，运行仅限专用工具 ----------
def session_ws(sid):
    ws = f"/tmp/dm_ws/{sid}"
    os.makedirs(ws, exist_ok=True)
    return ws

def exec_tool(sid, name, args):
    """执行 AI 请求的工具；文件操作限制在会话工作目录内，绝不执行任意代码"""
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
            # 运行工作目录 gen.py 生成 count 组数据（用 sol.py 或编译 sol.cpp 产出 .out）
            count = max(1, min(int(args.get("count", sessions[sid]["count"])), 50))
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
                rc, o2, e2 = run(std_cmd, int(sessions[sid]["time_limit"]) + STD_TIMEOUT_BASE, ws, stdin_data=o)
                if rc != 0:
                    msgs.append(f"第{i}组 sol 失败: {e2.strip()[:100]}")
                    continue
                open(os.path.join(ws, f"{i}.out"), "w", encoding="utf-8").write(o2)
                open(os.path.join(ws, f"{i}.score"), "w").write(str(round(100.0 / count, 2)))
                ok += 1
            return f"生成完成：{ok}/{count} 组" + (("；" + "; ".join(msgs[:3])) if msgs else "")
        if name == "test_checker":
            # 用工作目录数据（N.in/N.out）跑 checker.py 自检（标准答案必须通过）
            if not os.path.exists(os.path.join(ws, "checker.py")):
                return "错误：没有 checker.py（先用 write_file 写入）"
            ns = {}
            exec(open(os.path.join(ws, "checker.py"), encoding="utf-8").read(), ns)
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
                    it = f"异常: {e}"
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
        "description": "在工作目录写文件（编写/修改 gen.py、sol.py、sol.cpp、checker.py 等）。路径必须是相对文件名。",
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
        "description": "用已生成的数据对 checker.py 做自检（标准答案必须全部通过）。这是唯一能测试 checker 的方式。",
        "parameters": {"type": "object", "properties": {}}}},
]

def hint_compile_err(e: str) -> str:
    """识别常见编译错误并给出修复方向提示"""
    if "undefined reference to `main'" in e or "undefined reference to 'main'" in e:
        return "错误：代码缺少 int main() 入口函数（请补上 int main() { ... return 0; }）"
    if "collect2: error" in e:
        return "链接错误（collect2），常见原因：缺 main / 缺符号，请检查代码完整性"
    return e

def chat_stream_tools(sid, messages):
    """流式工具模式：AI 文本/工具参数增量实时推送，工具执行结果即时返回；返回最终 content"""
    s = sessions[sid]
    for _ in range(20):
        body = json.dumps({"model": "deepseek-chat", "messages": messages, "temperature": 0.6,
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
                    push_event(sid, "analysis_delta", clean_analysis(delta["content"]))
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
                        t["args"] += fn["arguments"]   # 参数不流式推送（长参数流式会卡死），执行完随 tool 事件一并显示
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
    raise RuntimeError("AI 工具调用次数过多（超过 20 次）")
class _SkipRun(Exception):
    """标记：数据已由 AI 工具在工作目录生成，跳过本地运行直接落盘"""

MAX_AUTO_FIX = 3   # 生成/编译/自检失败时，自动把错误反馈给 AI 重写

def do_generate(sid):
    """（在线程中执行）DeepSeek 生成 -> 运行 -> 自检；失败自动反馈 AI 重写（最多 MAX_AUTO_FIX 轮）"""
    s = sessions[sid]
    n = s["count"]
    last_err = ""
    for fix in range(MAX_AUTO_FIX):
        if fix > 0:
            push_event(sid, "analysis_delta", clean_analysis(f"\n[检测到问题，已通知 AI 修复（第 {fix}/{MAX_AUTO_FIX-1} 次）...]\n"))
            s["messages"].append({"role": "user",
                "content": f"你上次生成的代码有问题，请修复后重新输出完整 JSON：{last_err}"})
        try:
            prompt, user_std = build_prompt(sid, n)
            messages = [{"role": "system", "content": "你是专业的 OJ 出题助手。工作方式：优先使用工具——write_file 编写 gen.py/sol.py/checker.py，run_generator 生成数据，test_checker 自检 checker（标准答案必须通过），失败则修改重试；每次调用工具前先用一两句话说明你的思考与意图（供用户实时查看）；仅当题目极其简单、无需迭代时才可直接输出 JSON；最终仍严格输出合法 JSON 对象。"}] \
                       + s["messages"] + [{"role": "user", "content": prompt}]
            gen = extract_json(chat_stream_tools(sid, messages))
            analysis = (gen.get("analysis") or "").strip()
            if analysis:
                push_event(sid, "analysis_text", clean_analysis(analysis))
            gen_code = (gen.get("gen_code") or "").strip()
            sol_code = (gen.get("sol_code") or "").strip()
            ck_code = (gen.get("checker_code") or "").strip() if s["need_checker"] else ""
            cfg_yaml = (gen.get("config_yaml") or "").strip()
            # 若 AI 通过工具在工作目录写了代码，优先使用文件内容
            ws = session_ws(sid)
            if not gen_code and os.path.exists(os.path.join(ws, "gen.py")):
                gen_code = open(os.path.join(ws, "gen.py"), encoding="utf-8").read()
            if not sol_code and not user_std:
                if os.path.exists(os.path.join(ws, "sol.py")):
                    sol_code = open(os.path.join(ws, "sol.py"), encoding="utf-8").read()
                elif os.path.exists(os.path.join(ws, "sol.cpp")):
                    sol_code = open(os.path.join(ws, "sol.cpp"), encoding="utf-8").read()
                    s["std_lang"] = "cpp17"
                    user_std = True
            if not ck_code and os.path.exists(os.path.join(ws, "checker.py")):
                ck_code = open(os.path.join(ws, "checker.py"), encoding="utf-8").read()
            if not gen_code:
                raise RuntimeError("DeepSeek 未返回 gen_code（也未用 write_file 写入 gen.py）")
            ws_has_data = any(re.fullmatch(r"\d+\.in", f) for f in (os.listdir(ws) if os.path.isdir(ws) else []))
            if not sol_code and not ws_has_data:
                raise RuntimeError("缺少标准解法代码（且未通过 run_generator 生成数据）")
            if not sol_code:
                sol_code = ""   # 数据已由 AI 工具（run_generator）生成，本地不再需要 sol
            if not user_std and sol_code:
                s["std_code"] = sol_code
            s["gen_code"], s["sol_code"], s["ck_code"], s["config_yaml"] = gen_code, sol_code, ck_code, cfg_yaml
            push_event(sid, "code", {"gen_code": gen_code, "sol_code": sol_code,
                                     "checker": ck_code, "config_yaml": cfg_yaml, "user_std": user_std})
            work = f"/tmp/dm_{uuid.uuid4().hex[:8]}"
            os.makedirs(work, exist_ok=True)
            try:
                open(f"{work}/gen.py", "w", encoding="utf-8").write(gen_code)
                std_lang = s["std_lang"] if user_std else "python3"
                if std_flag := STDS.get(std_lang):
                    open(f"{work}/std.cpp", "w", encoding="utf-8").write(sol_code)
                    cc = "gcc" if std_lang == "c" else "g++"
                    rc, o, e = run([cc, std_flag, "-O2", "-o", f"{work}/std", f"{work}/std.cpp", "-lm"], 60, work)
                    if rc != 0:
                        raise RuntimeError("std 编译失败：\n" + hint_compile_err(e[-500:]))
                    std_cmd = [f"{work}/std"]
                else:
                    open(f"{work}/std.py", "w", encoding="utf-8").write(sol_code)
                    std_cmd = ["python3", f"{work}/std.py"]
                out_dir = os.path.join(DATA_ROOT, s["pid"])
                os.makedirs(out_dir, exist_ok=True)
                for f in os.listdir(out_dir):
                    if re.fullmatch(r"\d+\.(in|out|score)", f):
                        os.remove(os.path.join(out_dir, f))
                # AI 已通过 run_generator 在工作目录生成数据时，直接复制落盘
                score_each = round(100.0 / n, 2)
                errors, ok_n = [], 0
                ws_files = os.listdir(ws) if os.path.isdir(ws) else []
                skip_run = bool(any(re.fullmatch(r"\d+\.in", f) for f in ws_files))
                if skip_run:
                    from shutil import copyfile as _cp
                    cnt = 0
                    for f in ws_files:
                        if re.fullmatch(r"\d+\.(in|out|score)", f):
                            _cp(os.path.join(ws, f), os.path.join(out_dir, f))
                            cnt += 1
                    push_event(sid, "progress", {"i": 1, "n": 1, "msg": f"复制工作目录数据落盘（{cnt} 个文件）..."})
                    ok_n = max(int(f.split(".")[0]) for f in ws_files if re.fullmatch(r"\d+\.in", f))
                    errors = []
                if not skip_run:
                  for i in range(1, n + 1):
                    push_event(sid, "progress", {"i": i, "n": n, "msg": f"正在生成第 {i}/{n} 组数据..."})
                    ok = False
                    for attempt in (1, 2):
                        rc, o, e = run(["python3", f"{work}/gen.py"], GEN_TIMEOUT, work)
                        if rc == 0:
                            rc2, o2, e2 = run(std_cmd, int(s["time_limit"]) + STD_TIMEOUT_BASE, work, stdin_data=o)
                            if rc2 == 0:
                                open(os.path.join(out_dir, f"{i}.in"), "w", encoding="utf-8").write(o)
                                open(os.path.join(out_dir, f"{i}.out"), "w", encoding="utf-8").write(o2)
                                open(os.path.join(out_dir, f"{i}.score"), "w").write(str(score_each))
                                ok_n += 1
                                ok = True
                                break
                            else:
                                e = f"std: {e2.strip()[:400]}"
                        else:
                            e = f"gen: {e.strip()[:400]}"
                        if attempt == 1:
                            push_event(sid, "progress", {"i": i, "n": n, "msg": f"第 {i} 组失败，重试一次..."})
                    if not ok:
                        errors.append(f"第{i}组: {e}")
                if ok_n == 0:
                    raise RuntimeError("全部生成失败：" + "; ".join(errors[:5]))
                if ok_n < n:
                    raise RuntimeError(f"部分失败（{ok_n}/{n}）：" + "; ".join(errors[:3]))
                if ck_code:
                    ns = {}
                    exec(ck_code, ns)
                    ck_fn = ns.get("check")
                    if not callable(ck_fn):
                        raise RuntimeError("checker 缺少可调用函数 check(input, output, expected)")
                    for si in range(1, n + 1):
                        it = open(os.path.join(out_dir, f"{si}.in"), encoding="utf-8", errors="replace").read()
                        ot = open(os.path.join(out_dir, f"{si}.out"), encoding="utf-8", errors="replace").read()
                        r = ck_fn(it, ot, ot)
                        passed = r if isinstance(r, bool) else (bool(r[0]) if isinstance(r, (list, tuple)) else bool(r))
                        if not passed:
                            raise RuntimeError(f"checker 自检失败：标准答案 .out 第{si}组未通过（checker 可能自创了题面没有的约束）")
                cfg_name = re.sub(r"[\r\n:]+", " ", s["title"] or s["pid"])
                m = re.search(r"name\s*:\s*(.+)", cfg_yaml)
                if m:
                    cfg_name = m.group(1).strip()
                open(os.path.join(out_dir, "config.yaml"), "w", encoding="utf-8").write(
                    f"name: {cfg_name}\ntime_limit: {s['time_limit']}\nmemory_limit: {s['memory_limit']}\n"
                    f"test_cases: {n}\nscoring_mode: default\n")
                if ck_code:
                    open(os.path.join(out_dir, "checker.py"), "w", encoding="utf-8").write(ck_code)
                elif os.path.exists(os.path.join(out_dir, "checker.py")):
                    os.remove(os.path.join(out_dir, "checker.py"))
                msg = f"AI 造数据完成：成功 {ok_n}/{n} 组"
                if ck_code:
                    msg += "，已生成 checker"
                if user_std:
                    msg += f"，使用用户 std({std_lang})"
                if fix > 0:
                    msg += f"（自动修复 {fix} 次后成功）"
                s["last_result"] = {"ok": True, "message": msg, "n": ok_n, "checker": bool(ck_code)}
                s["messages"].append({"role": "assistant", "content": f"（AI 已生成并运行数据：{msg}，可继续提出修改要求）"})
                push_event(sid, "done", s["last_result"])
                return
            finally:
                shutil.rmtree(work, ignore_errors=True)
        except urllib.error.HTTPError as e:
            detail = e.read().decode(errors="replace")
            try:
                msg = json.loads(detail)["error"]["message"]
            except Exception:
                msg = detail[:200]
            last_err = f"DeepSeek 调用失败: {msg}"
        except Exception as e:
            last_err = str(e)
        push_event(sid, "analysis_delta", clean_analysis(f"\n[❌ {last_err[:200]}]\n"))
    push_event(sid, "error", f"自动修复 {MAX_AUTO_FIX} 次后仍失败：{last_err}")

class GenRequest(BaseModel):
    problem_id: str
    api_key: str
    count: int = 10
    need_checker: bool = False
    checker_req: str = ""
    extra_req: str = ""
    std_code: str = ""
    std_lang: str = "python3"
    title: str = ""
    description: str = ""
    input_format: str = ""
    output_format: str = ""
    hints: str = ""
    time_limit: float = 2.0
    memory_limit: int = 128

class ChatStartReq(GenRequest):
    pass

class ChatMsgReq(BaseModel):
    session_id: str
    user_msg: str

class ChatUpdateReq(BaseModel):
    session_id: str
    count: Optional[int] = None
    need_checker: Optional[bool] = None
    checker_req: Optional[str] = None
    extra_req: Optional[str] = None
    std_code: Optional[str] = None
    std_lang: Optional[str] = None
    api_key: Optional[str] = None

@app.get("/health")
async def health():
    return {"status": "ok", "service": "zxt-datamaker", "sessions": len(sessions)}

@app.post("/gen_data")
def gen_data(req: GenRequest):
    """旧接口：一次性生成（兼容 ai_gen_task.php）"""
    sid = "legacy_" + uuid.uuid4().hex[:8]
    sessions[sid] = session_info(req, re.sub(r"[^a-zA-Z0-9_-]", "", req.problem_id))
    t = threading.Thread(target=do_generate, args=(sid,), daemon=True)
    t.start()
    t.join(timeout=320)
    res = sessions[sid]["last_result"]
    err = [e["data"] for e in events[sid] if e["type"] == "error"]
    if err:
        raise HTTPException(502, err[-1])
    if not res:
        raise HTTPException(502, "生成超时或失败")
    return res

# ---------- 聊天工作台 ----------
@app.post("/chat/start")
async def chat_start(req: ChatStartReq):
    pid = re.sub(r"[^a-zA-Z0-9_-]", "", req.problem_id)
    if not pid:
        raise HTTPException(400, "缺少题目编号")
    if not req.api_key:
        raise HTTPException(400, "缺少 DeepSeek API Key")
    sid = uuid.uuid4().hex
    sessions[sid] = session_info(req, pid)
    push_event(sid, "info", f"会话已创建：题目 {pid}，组数 {req.count}，std={req.std_lang if req.std_code.strip() else 'AI生成'}，checker={'开' if req.need_checker else '关'}")
    # 开始会话即自动生成第一轮（用户可在输入框继续多轮修改）
    threading.Thread(target=do_generate, args=(sid,), daemon=True).start()
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
    sessions[sid]["last_user_req"] = msg
    push_event(sid, "user", msg)
    # 新一轮生成：记录本轮事件起点、清除上轮结果（events 的 done 只按本轮判定）
    sessions[sid]["round_start"] = _event_seq[sid]
    sessions[sid]["last_result"] = None
    threading.Thread(target=do_generate, args=(sid,), daemon=True).start()
    return {"ok": True, "session_id": sid}

@app.get("/chat/events")
async def chat_events(session_id: str, since: int = 0):
    if session_id not in sessions:
        raise HTTPException(404, "会话不存在")
    rs = sessions[session_id].get("round_start", 0)
    evs = [e for e in events[session_id] if e["seq"] >= max(since, rs)]
    # 限量返回：一次最多 300 条（防止大会话事件全量传输拖慢线程/网络），前端持续轮询补齐
    evs = evs[:300]
    # done 只认本轮（round_start 之后）的 done/error，避免历史结果导致轮询提前停止
    done = any(e["type"] in ("done", "error") and e["seq"] >= rs for e in events[session_id])
    return {"events": evs, "done": done, "next_since": evs[-1]["seq"] + 1 if evs else since}

@app.post("/chat/update")
async def chat_update(req: ChatUpdateReq):
    """会话中调整参数并触发重新生成"""
    sid = req.session_id
    if sid not in sessions:
        raise HTTPException(404, "会话不存在或已过期")
    s = sessions[sid]
    changed = []
    if req.count is not None and 1 <= req.count <= 50:
        s["count"] = req.count; changed.append(f"组数={req.count}")
    if req.need_checker is not None:
        s["need_checker"] = req.need_checker; changed.append(f"checker={'开' if req.need_checker else '关'}")
    if req.checker_req is not None:
        s["checker_req"] = req.checker_req.strip(); changed.append("checker要求")
    if req.extra_req is not None:
        s["extra_req"] = req.extra_req.strip(); changed.append("额外要求")
    if req.std_code is not None:
        s["std_code"] = req.std_code; changed.append("std")
    if req.std_lang is not None and req.std_lang in STDS:
        s["std_lang"] = req.std_lang
    if req.api_key:
        s["api_key"] = req.api_key
    if not changed:
        raise HTTPException(400, "没有需要更新的参数")
    # 清空工作目录旧文件（新参数新开始）
    ws = session_ws(sid)
    for f in os.listdir(ws):
        try: os.remove(os.path.join(ws, f))
        except Exception: pass
    s["last_user_req"] = "参数已更新（" + "，".join(changed) + "），请按新参数重新生成完整数据。"
    s["messages"].append({"role": "user", "content": "参数已更新（" + "，".join(changed) + "），请重新生成。"})
    s["round_start"] = _event_seq[sid]
    s["last_result"] = None
    push_event(sid, "user", "⚙️ 参数已调整：" + "，".join(changed) + "，重新生成中...")
    threading.Thread(target=do_generate, args=(sid,), daemon=True).start()
    return {"ok": True, "session_id": sid, "changed": changed}
