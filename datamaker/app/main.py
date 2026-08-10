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
            json.dump({"session": sessions[sid], "events": list(events[sid]),
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
threading.Thread(target=lambda: (time.sleep(3600), cleanup_old_sessions()), daemon=True).start()

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
        std_note = "请同时生成标准解法 sol_code（从 stdin 读输入、向 stdout 输出答案）。"
        fields += ',"sol_code":"Python3 标准解法代码。从 stdin 读取输入，向 stdout 输出正确答案，不要输出多余内容"'
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
            messages = [{"role": "system", "content": "你是专业的 OJ 出题助手，严格只输出合法 JSON 对象，不输出任何其他内容。"}] \
                       + s["messages"] + [{"role": "user", "content": prompt}]
            gen = fetch_generation(sid, messages)
            analysis = (gen.get("analysis") or "").strip()
            if analysis:
                push_event(sid, "analysis_text", clean_analysis(analysis))
            gen_code = (gen.get("gen_code") or "").strip()
            if not gen_code:
                raise RuntimeError("DeepSeek 未返回 gen_code")
            sol_code = s["std_code"].strip() if user_std else (gen.get("sol_code") or "").strip()
            if not sol_code:
                raise RuntimeError("缺少标准解法代码")
            ck_code = (gen.get("checker_code") or "").strip() if s["need_checker"] else ""
            cfg_yaml = (gen.get("config_yaml") or "").strip()
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
                        raise RuntimeError("std 编译失败：\n" + e[-500:])
                    std_cmd = [f"{work}/std"]
                else:
                    open(f"{work}/std.py", "w", encoding="utf-8").write(sol_code)
                    std_cmd = ["python3", f"{work}/std.py"]
                out_dir = os.path.join(DATA_ROOT, s["pid"])
                os.makedirs(out_dir, exist_ok=True)
                for f in os.listdir(out_dir):
                    if re.fullmatch(r"\d+\.(in|out|score)", f):
                        os.remove(os.path.join(out_dir, f))
                score_each = round(100.0 / n, 2)
                errors, ok_n = [], 0
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
