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
GEN_TIMEOUT = 20
STD_TIMEOUT_BASE = 15
STDS = {"python3": None, "c": "-std=c11", "cpp14": "-std=c++14", "cpp17": "-std=c++17", "cpp20": "-std=c++20"}

# ---------- 会话与事件 ----------
sessions = {}
events = defaultdict(deque)     # session_id -> deque of {"seq":int,"type":str,"data":obj}
_event_seq = defaultdict(int)

def push_event(sid, typ, data):
    seq = _event_seq[sid]
    _event_seq[sid] += 1
    events[sid].append({"seq": seq, "type": typ, "data": data})
    return seq

def session_info(req, pid):
    return {
        "pid": pid, "api_key": req.api_key, "count": req.count,
        "need_checker": req.need_checker, "checker_req": req.checker_req,
        "extra_req": req.extra_req, "std_code": req.std_code, "std_lang": req.std_lang,
        "title": req.title, "description": req.description, "input_format": req.input_format,
        "output_format": req.output_format, "hints": req.hints,
        "time_limit": req.time_limit, "memory_limit": req.memory_limit,
        "messages": [],          # DeepSeek 多轮上下文
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
              f'如何保证 {n} 组数据有区分度。只写要点，不要废话。"'
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
                   f'要求：{ck_req}。不要写 main 或读文件"')
    extra_note = ""
    if s["extra_req"].strip():
        extra_note = f"\n用户对数据的额外要求（务必逐条满足）：{s['extra_req'].strip()}\n"
    prompt = ("你是 OJ 出题助手。根据以下题目信息生成测试数据构造代码。\n\n" + desc + "\n"
              + std_note + "\n" + extra_note
              + "请严格只返回一个 JSON 对象（禁止 markdown 代码块、禁止任何解释文字、禁止多余字段），格式：\n{"
              + fields + "}\n"
              + f"共需生成 {n} 组测试数据。")
    return prompt, user_std

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

def do_generate(sid):
    """（在线程中执行）DeepSeek 流式生成 -> 解析 -> 运行生成器/std -> 写盘 -> done 事件"""
    s = sessions[sid]
    n = s["count"]
    try:
        prompt, user_std = build_prompt(sid, n)
        messages = [{"role": "system", "content": "你是专业的 OJ 出题助手，严格只输出合法 JSON 对象，不输出任何其他内容。"}] \
                   + s["messages"] + [{"role": "user", "content": prompt}]
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
                    push_event(sid, "analysis_delta", delta)
        push_event(sid, "analysis_end", "")
        gen = extract_json(content)
        analysis = (gen.get("analysis") or "").strip()
        if analysis:
            push_event(sid, "analysis_text", analysis)
        gen_code = (gen.get("gen_code") or "").strip()
        if not gen_code:
            raise RuntimeError("DeepSeek 未返回 gen_code")
        sol_code = s["std_code"].strip() if user_std else (gen.get("sol_code") or "").strip()
        if not sol_code:
            raise RuntimeError("缺少标准解法代码")
        ck_code = (gen.get("checker_code") or "").strip() if s["need_checker"] else ""
        cfg_yaml = (gen.get("config_yaml") or "").strip()
        s["gen_code"], s["sol_code"], s["ck_code"], s["config_yaml"] = gen_code, sol_code, ck_code, cfg_yaml
        push_event(sid, "code", {"gen_code": gen_code, "sol_code": sol_code if not user_std else "[用户std]",
                                 "checker": ck_code, "config_yaml": cfg_yaml, "user_std": user_std})
        # 运行
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
                rc, o, e = run(["python3", f"{work}/gen.py"], GEN_TIMEOUT, work)
                if rc != 0:
                    errors.append(f"第{i}组: 生成器失败")
                    continue
                open(os.path.join(out_dir, f"{i}.in"), "w", encoding="utf-8").write(o)
                rc, o, e = run(std_cmd, int(s["time_limit"]) + STD_TIMEOUT_BASE, work, stdin_data=o)
                if rc != 0:
                    errors.append(f"第{i}组: std 运行失败")
                    continue
                open(os.path.join(out_dir, f"{i}.out"), "w", encoding="utf-8").write(o)
                open(os.path.join(out_dir, f"{i}.score"), "w").write(str(score_each))
                ok_n += 1
            if ok_n == 0:
                raise RuntimeError("全部生成失败：" + "; ".join(errors[:5]))
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
            msg = f"AI 造数据完成：成功 {ok_n}/{n} 组" + (f"（失败 {len(errors)} 组：{'; '.join(errors[:3])}）" if errors else "")
            if ck_code:
                msg += "，已生成 checker"
            if user_std:
                msg += f"，使用用户 std({std_lang})"
            s["last_result"] = {"ok": True, "message": msg, "n": ok_n, "checker": bool(ck_code)}
            s["messages"].append({"role": "assistant", "content": f"（AI 已生成并运行数据：{msg}，可继续提出修改要求）"})
            push_event(sid, "done", s["last_result"])
        finally:
            shutil.rmtree(work, ignore_errors=True)
    except urllib.error.HTTPError as e:
        detail = e.read().decode(errors="replace")
        try:
            msg = json.loads(detail)["error"]["message"]
        except Exception:
            msg = detail[:200]
        push_event(sid, "error", f"DeepSeek 调用失败: {msg}")
    except Exception as e:
        push_event(sid, "error", str(e))

# ---------- 兼容旧接口 ----------
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
def health():
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
def chat_start(req: ChatStartReq):
    pid = re.sub(r"[^a-zA-Z0-9_-]", "", req.problem_id)
    if not pid:
        raise HTTPException(400, "缺少题目编号")
    if not req.api_key:
        raise HTTPException(400, "缺少 DeepSeek API Key")
    sid = uuid.uuid4().hex
    sessions[sid] = session_info(req, pid)
    push_event(sid, "info", f"会话已创建：题目 {pid}，组数 {req.count}，std={req.std_lang if req.std_code.strip() else 'AI生成'}，checker={'开' if req.need_checker else '关'}")
    return {"ok": True, "session_id": sid}

@app.post("/chat/message")
def chat_message(req: ChatMsgReq):
    sid = req.session_id
    if sid not in sessions:
        raise HTTPException(404, "会话不存在或已过期")
    msg = req.user_msg.strip()
    if not msg:
        raise HTTPException(400, "消息不能为空")
    sessions[sid]["messages"].append({"role": "user", "content": msg})
    push_event(sid, "user", msg)
    # 用户提出修改时，把最新代码作为上下文提示（帮助 AI 基于现有代码修改）
    if sessions[sid]["gen_code"]:
        sessions[sid]["messages"].append({
            "role": "user",
            "content": "（当前数据生成器如下，请在它基础上按我的要求修改：\n" + sessions[sid]["gen_code"][:800] + "\n）"
        })
    threading.Thread(target=do_generate, args=(sid,), daemon=True).start()
    return {"ok": True, "session_id": sid}

@app.get("/chat/events")
def chat_events(session_id: str, since: int = 0):
    if session_id not in sessions:
        raise HTTPException(404, "会话不存在")
    evs = [e for e in events[session_id] if e["seq"] >= since]
    done = bool(sessions[session_id].get("last_result")) or any(e["type"] == "error" for e in evs)
    return {"events": evs, "done": done}
