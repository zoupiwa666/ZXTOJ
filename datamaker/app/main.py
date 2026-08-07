"""zxt-datamaker - 独立造数据容器
职责：DeepSeek 生成数据生成器(强制JSON) -> 服务器运行生成器/std 产出 N 组测试点
      -> 直接写入共享卷 /data/problems/{pid}/
用户可提供 std（python3/cpp17），提供后 AI 只生成数据生成器，不再生成解法。
"""
import os, json, re, shutil, subprocess, time, uuid, urllib.request
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Optional

app = FastAPI(title="zxt-datamaker", version="1.0.0")

DEEPSEEK_URL = "https://api.deepseek.com/chat/completions"
DATA_ROOT = os.environ.get("DATA_ROOT", "/data/problems")
GEN_TIMEOUT = 20          # 生成器单次运行超时(秒)
STD_TIMEOUT_BASE = 15     # std 单次运行基础超时(秒)，另加 time_limit

class GenRequest(BaseModel):
    problem_id: str
    api_key: str
    count: int = 10
    need_checker: bool = False
    checker_req: str = ""
    extra_req: str = ""          # 用户额外要求（细粒度数据需求）
    std_code: str = ""          # 用户提供的 std，非空则 AI 不生成解法
    std_lang: str = "python3"   # python3 | cpp17
    title: str = ""
    description: str = ""
    input_format: str = ""
    output_format: str = ""
    hints: str = ""
    time_limit: float = 2.0
    memory_limit: int = 128

def call_deepseek(api_key: str, messages: list, timeout: int = 180) -> dict:
    body = json.dumps({
        "model": "deepseek-chat",
        "messages": messages,
        "temperature": 0.6,
        "response_format": {"type": "json_object"},   # 强制 JSON，不要任何杂余
    }).encode()
    req = urllib.request.Request(DEEPSEEK_URL, data=body, headers={
        "Content-Type": "application/json",
        "Authorization": "Bearer " + api_key,
    })
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read())

def extract_json(content: str) -> dict:
    content = content.strip()
    content = re.sub(r"^```(?:json)?\s*", "", content)
    content = re.sub(r"\s*```$", "", content)
    if not content.startswith("{"):
        a, b = content.find("{"), content.rfind("}")
        if a != -1 and b > a:
            content = content[a:b + 1]
    return json.loads(content)

def run(cmd: list, timeout: int, cwd: str, stdin_data: str = None):
    try:
        p = subprocess.run(cmd, input=stdin_data.encode() if stdin_data is not None else None,
                           capture_output=True, timeout=timeout, cwd=cwd)
        return p.returncode, p.stdout.decode(errors="replace"), p.stderr.decode(errors="replace")
    except subprocess.TimeoutExpired:
        return -1, "", "timeout"
    except Exception as e:
        return -2, "", str(e)

@app.get("/health")
def health():
    return {"status": "ok", "service": "zxt-datamaker"}

@app.post("/gen_data")
def gen_data(req: GenRequest):
    pid = re.sub(r"[^a-zA-Z0-9_-]", "", req.problem_id)
    n = max(1, min(req.count, 50))
    if not pid:
        raise HTTPException(400, "缺少题目编号")
    if not req.api_key:
        raise HTTPException(400, "缺少 DeepSeek API Key")

    user_std = req.std_code.strip() != ""
    STDS = {"python3": None, "c": "-std=c11", "cpp14": "-std=c++14", "cpp17": "-std=c++17", "cpp20": "-std=c++20"}
    if user_std and req.std_lang not in STDS:
        raise HTTPException(400, "std 语言仅支持 python3 / c / cpp14 / cpp17 / cpp20")

    # ---------- 1. 组 prompt 调 DeepSeek（强制 JSON） ----------
    desc = (f"题目编号: {pid}\n题目名称: {req.title}\n"
            f"时间限制: {req.time_limit} 秒\n内存限制: {req.memory_limit} MB\n"
            f"题面: {req.description}\n输入格式: {req.input_format}\n"
            f"输出格式: {req.output_format}\n提示: {req.hints}\n")

    fields = ('"analysis":"（轻度思考）先用 3-5 句简要分析：题目的关键约束、数据分布策略（边界/随机/大数据/特殊构造）、'
              '如何保证 {n} 组数据有区分度。只写要点，不要废话。"'
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
    if req.need_checker:
        ck_req = req.checker_req.strip() or "按题意标准比对，必要时放宽浮点误差"
        fields += (',"checker_code":"Python3 特殊判题 checker 代码。必须定义函数 check(input, output, expected)，'
                   '参数均为字符串：input=测试输入、output=选手输出、expected=标准答案。'
                   '返回 True/False，或返回 (是否通过:bool, 提示信息:str, 得分占比:float)。'
                   f'要求：{ck_req}。不要写 main 或读文件"')

    extra_note = ""
    if req.extra_req.strip():
        extra_note = f"\n用户对数据的额外要求（务必逐条满足）：{req.extra_req.strip()}\n"

    prompt = ("你是 OJ 出题助手。根据以下题目信息生成测试数据构造代码。\n\n" + desc + "\n"
              + std_note + "\n" + extra_note
              + "请严格只返回一个 JSON 对象（禁止 markdown 代码块、禁止任何解释文字、禁止多余字段），格式：\n{"
              + fields + "}\n"
              + f"共需生成 {n} 组测试数据。")

    try:
        resp = call_deepseek(req.api_key, [
            {"role": "system", "content": "你是专业的 OJ 出题助手，严格只输出合法 JSON 对象，不输出任何其他内容。"},
            {"role": "user", "content": prompt},
        ])
    except urllib.error.HTTPError as e:
        detail = e.read().decode(errors="replace")
        try:
            msg = json.loads(detail)["error"]["message"]
        except Exception:
            msg = detail[:200]
        raise HTTPException(502, f"DeepSeek 调用失败: {msg}")
    except Exception as e:
        raise HTTPException(502, f"DeepSeek 调用失败: {e}")

    content = (resp.get("choices") or [{}])[0].get("message", {}).get("content", "")
    try:
        gen = extract_json(content)
    except Exception:
        raise HTTPException(502, "DeepSeek 返回内容无法解析为 JSON（已强制 json_object 仍异常）")

    analysis = (gen.get("analysis") or "").strip()
    if analysis:
        print(f"[gen] 思考摘要: {analysis[:200]}")
    gen_code = (gen.get("gen_code") or "").strip()
    if not gen_code:
        raise HTTPException(502, "DeepSeek 未返回 gen_code")
    sol_code = (gen.get("sol_code") or "").strip() if not user_std else req.std_code.strip()
    if not sol_code:
        raise HTTPException(502, "缺少标准解法代码")
    ck_code = (gen.get("checker_code") or "").strip() if req.need_checker else ""

    # ---------- 2. 工作目录 ----------
    work = f"/tmp/dm_{uuid.uuid4().hex[:8]}"
    os.makedirs(work, exist_ok=True)
    try:
        (open(f"{work}/gen.py", "w", encoding="utf-8")).write(gen_code)
        std_lang = req.std_lang if user_std else "python3"
        std_flag = STDS.get(std_lang)
        if std_flag is not None:
            (open(f"{work}/std.cpp", "w", encoding="utf-8")).write(sol_code)
            cc = "gcc" if std_lang == "c" else "g++"
            rc, out, err = run([cc, std_flag, "-O2", "-o", f"{work}/std", f"{work}/std.cpp", "-lm"], 60, work)
            if rc != 0:
                raise HTTPException(502, "std 编译失败：\n" + err[-500:])
            std_cmd = [f"{work}/std"]
        else:
            (open(f"{work}/std.py", "w", encoding="utf-8")).write(sol_code)
            std_cmd = ["python3", f"{work}/std.py"]

        # ---------- 3. 生成 N 组输入，跑 std 出输出 ----------
        out_dir = os.path.join(DATA_ROOT, pid)
        os.makedirs(out_dir, exist_ok=True)
        # 清旧数字测试文件
        for f in os.listdir(out_dir):
            if re.fullmatch(r"\d+\.(in|out|score)", f):
                os.remove(os.path.join(out_dir, f))

        score_each = round(100.0 / n, 2)
        errors, ok_n = [], 0
        gen_stderr = ""
        for i in range(1, n + 1):
            rc, o, e = run(["python3", f"{work}/gen.py"], GEN_TIMEOUT, work)
            if rc != 0:
                errors.append(f"第{i}组: 生成器失败({e.strip()[:100] or 'exit ' + str(rc)})")
                if not gen_stderr:
                    gen_stderr = e.strip()[:300]
                continue
            open(os.path.join(out_dir, f"{i}.in"), "w", encoding="utf-8").write(o)
            rc, o, e = run(std_cmd, int(req.time_limit) + STD_TIMEOUT_BASE, work, stdin_data=o)
            if rc != 0:
                errors.append(f"第{i}组: std 运行失败")
                continue
            open(os.path.join(out_dir, f"{i}.out"), "w", encoding="utf-8").write(o)
            open(os.path.join(out_dir, f"{i}.score"), "w").write(str(score_each))
            ok_n += 1

        if ok_n == 0:
            raise HTTPException(502, "全部生成失败：" + "; ".join(errors[:5]))

        # ---------- 4. config.yaml + checker ----------
        cfg_name = re.sub(r"[\r\n:]+", " ", req.title or pid)
        yaml_txt = (gen.get("config_yaml") or "")
        m = re.search(r"name\s*:\s*(.+)", yaml_txt)
        if m:
            cfg_name = m.group(1).strip()
        # 默认评分模式：每个测试点默认平分（.score 文件已写），config.yaml 无需 scores 数组
        open(os.path.join(out_dir, "config.yaml"), "w", encoding="utf-8").write(
            f"name: {cfg_name}\ntime_limit: {req.time_limit}\nmemory_limit: {req.memory_limit}\n"
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
        return {"ok": True, "message": msg, "n": ok_n, "errors": errors,
                "checker": bool(ck_code), "user_std": user_std, "gen_stderr": gen_stderr}
    finally:
        shutil.rmtree(work, ignore_errors=True)
