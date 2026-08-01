"""评测核心 - 多线程并行 + 流式输出 + 全判决 + 分数 Checker"""
import sys, os, json, subprocess, time, resource, argparse, traceback
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor, as_completed

V_AC, V_WA, V_TLE, V_MLE, V_RE, V_OLE, V_CE, V_SE = "AC","WA","TLE","MLE","RE","OLE","CE","SE"

def compile_code(workdir, lang, shared_dir):
    os.makedirs(shared_dir, exist_ok=True)
    src_ext = {"c":".c","cpp14":".cpp","cpp17":".cpp","cpp20":".cpp"}
    ext = src_ext.get(lang, ".cpp")
    src = f"{workdir}/solution{ext}"
    dst = f"{shared_dir}/solution{ext}"
    if os.path.exists(src):
        data = open(src,'rb').read()
        try: os.remove(dst)
        except: pass
        open(dst,'wb').write(data)
    cmds = {
        "c":["gcc","-std=c11","-O2","-o",f"{shared_dir}/solution",f"{shared_dir}/solution.c","-lm"],
        "cpp14":["g++","-std=c++14","-O2","-o",f"{shared_dir}/solution",f"{shared_dir}/solution.cpp","-lm"],
        "cpp17":["g++","-std=c++17","-O2","-o",f"{shared_dir}/solution",f"{shared_dir}/solution.cpp","-lm"],
        "cpp20":["g++","-std=c++20","-O2","-o",f"{shared_dir}/solution",f"{shared_dir}/solution.cpp","-lm"],
    }
    cmd = cmds.get(lang)
    if not cmd: return True, None
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=30, cwd=shared_dir)
    if r.returncode != 0: return False, r.stderr or r.stdout
    return True, None

def run_case_sync(lang, workdir, shared_dir, inp, tl, ml, input_file=None):
    if input_file and os.path.exists(input_file):
        inp = open(input_file, 'r', encoding='utf-8', errors='replace').read()
    cmds = {
        "c":[f"{shared_dir}/solution"],"cpp14":[f"{shared_dir}/solution"],
        "cpp17":[f"{shared_dir}/solution"],"cpp20":[f"{shared_dir}/solution"],
        "python3":["python3", f"{workdir}/solution.py"],
    }
    cmd = cmds.get(lang)
    if not cmd: return {"output":"","time_used":0,"memory_used":0,"exit_code":None,"verdict":V_SE,"error":"不支持"}
    try:
        start = time.time()
        proc = subprocess.Popen(cmd, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, cwd=shared_dir)
        try:
            out, err = proc.communicate(input=inp.encode() if inp else None, timeout=tl)
        except subprocess.TimeoutExpired:
            proc.kill(); proc.wait()
            return {"output":"","time_used":tl,"memory_used":0,"exit_code":None,"verdict":V_TLE,"error":"运行超时"}
        elapsed = round(time.time()-start, 3)
        output = out.decode(errors="replace")
        err_output = err.decode(errors="replace")
        mem = 0
        verdict = V_AC; error = None
        if mem > ml: verdict=V_MLE; error=f"内存超限: {mem:.1f}MB > {ml}MB"
        elif proc.returncode != 0:
            verdict=V_RE; error=f"运行时错误 (exit={proc.returncode})"
            if err_output: error += f"\n{err_output[:300]}"
        return {"output":output,"time_used":elapsed,"memory_used":mem,
                "exit_code":proc.returncode,"verdict":verdict,"error":error}
    except Exception as e:
        return {"output":"","time_used":0,"memory_used":0,"exit_code":None,"verdict":V_SE,"error":f"异常:{e}"}

def run_checker(checker_code, inp, out, exp, max_score=1.0):
    if not checker_code:
        # 逐行比对，避免大字符串拷贝
        ol = out.splitlines(); el = exp.splitlines()
        while ol and ol[-1]=='': ol.pop()
        while el and el[-1]=='': el.pop()
        if len(ol)!=len(el): return False,f"行数不同: {len(ol)} vs {len(el)}",0.0
        for i in range(len(ol)):
            if ol[i].rstrip() != el[i].rstrip():
                return False,f"第{i+1}行不同",0.0
        return True,"",max_score
        return False,f"期望:{exp[:200]}\n实际:{out[:200]}",0.0
    try:
        ns={}; exec(checker_code, ns)
        if "check" not in ns: return False,"checker 缺少 check(input,output,expected)",0.0
        r=ns["check"](inp,out,exp)
        if isinstance(r,bool): return r,"" if r else "checker 不通过",(max_score if r else 0.0)
        if isinstance(r,(int,float)): s=float(r); return s>0,"" if s>0 else "0分",min(s,max_score)
        if isinstance(r,(list,tuple)):
            if len(r)>=3: return bool(r[0]),str(r[1]),min(float(r[2]),max_score)
            if len(r)==2: return bool(r[0]),str(r[1]),(max_score if r[0] else 0.0)
        return False,"checker 返回值格式错误",0.0
    except Exception as e: return False,f"checker 异常:{e}",0.0

def process_one_case(args_tuple):
    i, c, lang, workdir, shared_dir, tl, ml, ol, ck, output_dir = args_tuple
    inp=c.get("input",""); exp=c.get("expected_output","")
    ct=c.get("time_limit",tl); cm=c.get("memory_limit",ml); max_score=c.get("score",1.0)

    inp = c.get("input", "")
    if c.get("input_file"):
        inp = open(c["input_file"], 'r', encoding='utf-8', errors='replace').read()
    exp = c.get("expected_output", "")
    if c.get("output_file"):
        exp = open(c["output_file"], 'r', encoding='utf-8', errors='replace').read()
    rr = run_case_sync(lang, workdir, shared_dir, inp, ct, cm)
    verdict=rr["verdict"]; output=rr.get("output",""); err_msg=rr.get("error")

    if verdict==V_AC and len(output.encode())>ol:
        verdict=V_OLE; err_msg=f"输出超限:{len(output.encode())}B > {ol}B"

    passed=False; score=0.0
    if verdict==V_AC:
        passed,cmsg,score=run_checker(ck, inp, output, exp, max_score)
        if not passed: verdict=V_WA
        if cmsg and not passed: err_msg=cmsg

    result = {"test_case_index":i,"verdict":verdict,"passed":(verdict==V_AC),
              "score":score,"output":output,"expected_output":exp,
              "time_used":rr.get("time_used",0),"memory_used":rr.get("memory_used",0),
              "exit_code":rr.get("exit_code"),"error":err_msg}

    # 🔥 流式输出：每完成一个测试点就写进度文件
    try:
        progress_path = os.path.join(shared_dir, "prog_"+str(i)+".json")  # 写到共享卷; orig: f"progress_{i}.json")
        with open(progress_path, 'w') as f:
            json.dump(result, f, ensure_ascii=False)
    except:
        pass

    return result

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--workdir", required=True)
    p.add_argument("--output-dir", required=True)
    p.add_argument("--shared-dir", default="/tmp/shared")
    args = p.parse_args()

    result={"status":"failed","compile_error":None,"system_error":None,"results":[]}
    try:
        cfg=json.loads((Path(args.workdir)/"task_config.json").read_text())
        # 从文件读取测试用例（新架构）
        lang=cfg["language"]; tl=cfg.get("time_limit",2.0); ml=cfg.get("memory_limit",128); ol=cfg.get("output_limit",1024*1024); workers=cfg.get("parallel_workers",3)
        data_dir = cfg.get('data_dir', '')
        if data_dir and os.path.isdir(data_dir):
            cases = []
            i = 1
            while True:
                inp_file = os.path.join(data_dir, f"{i}.in")
                out_file = os.path.join(data_dir, f"{i}.out")
                score_file = os.path.join(data_dir, f"{i}.score")
                if not os.path.exists(inp_file): break
                # 惰性：只存路径，运行时再读
                score = float(open(score_file,'r').read().strip()) if os.path.exists(score_file) else 10.0
                cases.append({"input_file": inp_file, "output_file": out_file, "input": "", "expected_output": "", "score": score, "time_limit": tl, "memory_limit": ml})
                i += 1
        else:
            cases=json.loads((Path(args.workdir)/"test_cases.json").read_text())
        cp=Path(args.workdir)/"checker.py"; ck=cp.read_text() if cp.exists() else None

        if lang in ("c","cpp14","cpp17","cpp20"):
            ok,err=compile_code(args.workdir, lang, args.shared_dir)
            if not ok:
                result["status"]="compile_error"; result["compile_error"]=err
                (Path(args.output_dir)/"result.json").write_text(json.dumps(result,ensure_ascii=False)); return

        tasks=[(i,c,lang,args.workdir,args.shared_dir,tl,ml,ol,ck,args.output_dir) 
               for i,c in enumerate(cases)]
        all_r=[None]*len(cases)

        with ThreadPoolExecutor(max_workers=min(workers,len(cases))) as executor:
            futures={executor.submit(process_one_case,ta):ta[0] for ta in tasks}
            for future in as_completed(futures):
                idx=futures[future]
                try: all_r[idx]=future.result()
                except Exception as e:
                    all_r[idx]={"test_case_index":idx,"verdict":V_SE,"passed":False,
                                "score":0.0,"output":"","expected_output":"",
                                "time_used":0,"memory_used":0,"exit_code":None,"error":f"线程异常:{e}"}

        result["status"]="completed"; result["results"]=all_r
    except Exception as e:
        result["status"]="failed"
        result["system_error"]=f"{type(e).__name__}:{e}\n{traceback.format_exc()}"
    (Path(args.output_dir)/"result.json").write_text(json.dumps(result,ensure_ascii=False))

if __name__=="__main__":
    main()
