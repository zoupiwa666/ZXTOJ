"""交互题评测：选手进程与交互程序通过双管道实时通信
- 交互库模式：interactor.cpp（testlib，registerInteraction）编译后运行
- IO 交互模式：interactor.py / interactor.sh 等脚本直接运行
调用：interactor 输入文件 答案文件（argv[1]=in, argv[2]=ans）
判定：interactor 退出码 0=AC，非0=WA；选手超时=TLE，选手崩溃=RE
"""
import os, subprocess, threading, time

V_AC, V_WA, V_TLE, V_RE, V_MLE, V_SE = "AC", "WA", "TLE", "RE", "MLE", "SE"

def run_interactive(sol_cmd, inter_cmd, in_file, ans_file, tl, ml, workdir):
    """双进程管道交互；返回 {verdict, time_used, memory_used, exit_code, error}
    任一侧退出后给另一侧短宽限（读到 EOF 自然退出），超时 kill，避免空等超时上限"""
    try:
        # 管道：选手 stdout -> 交互 stdin；交互 stdout -> 选手 stdin
        p2i_r, p2i_w = os.pipe()
        i2p_r, i2p_w = os.pipe()
        sol = subprocess.Popen(sol_cmd, stdin=i2p_r, stdout=p2i_w,
                               stderr=subprocess.DEVNULL, cwd=workdir)
        os.close(i2p_r); os.close(p2i_w)
        # testlib registerInteraction 参数：<input-file> <output-日志文件> [<answer-file>]
        # 日志写沙箱可写目录 /tmp（workdir 可能是只读挂载）
        log_file = f"/tmp/interact_{os.getpid()}_{int(time.time()*1000)}.log"
        inter = subprocess.Popen(inter_cmd + [in_file, log_file, ans_file],
                                 stdin=p2i_r, stdout=i2p_w, cwd=workdir)
        os.close(p2i_r); os.close(i2p_w)

        # 选手内存监控
        peak_kb = [0]; stop_ev = threading.Event()
        def monitor():
            while not stop_ev.is_set():
                try:
                    with open(f'/proc/{sol.pid}/status') as f:
                        for line in f:
                            if line.startswith('VmHWM'):
                                v = int(line.split()[1])
                                if v > peak_kb[0]: peak_kb[0] = v
                                break
                except Exception:
                    pass
                stop_ev.wait(0.05)
        mon = threading.Thread(target=monitor, daemon=True); mon.start()

        start = time.time()
        hard_tl = max(tl * 3 + 10, 60)   # 交互总超时上限（含交互往返）
        grace = 3.0                      # 一侧结束、另一侧的 EOF 宽限
        sol_rc = None; inter_rc = None; timeout = False; first_exit = None
        try:
            while True:
                if sol_rc is None:
                    sol_rc = sol.poll()
                    if sol_rc is not None and first_exit is None: first_exit = ('sol', sol_rc)
                if inter_rc is None:
                    inter_rc = inter.poll()
                    if inter_rc is not None and first_exit is None: first_exit = ('inter', inter_rc)
                if sol_rc is not None and inter_rc is not None:
                    break
                if time.time() - start > hard_tl:
                    timeout = True; break
                if inter_rc is not None and sol_rc is None:
                    # interactor 已结束：选手 stdin 会读到 EOF，等它自然退出
                    try: sol_rc = sol.wait(timeout=grace)
                    except subprocess.TimeoutExpired:
                        sol.kill(); sol.wait(); sol_rc = -9
                    break
                if sol_rc is not None and inter_rc is None:
                    # 选手已结束：interactor stdin 会读到 EOF，等它自然退出
                    try: inter_rc = inter.wait(timeout=grace)
                    except subprocess.TimeoutExpired:
                        inter.kill(); inter.wait(); inter_rc = -9
                    break
                time.sleep(0.02)
            if timeout:
                for p in (sol, inter):
                    try: p.kill()
                    except Exception: pass
                for p in (sol, inter):
                    try: p.wait(timeout=2)
                    except Exception: pass
                sol_rc = sol.returncode; inter_rc = inter.returncode
        finally:
            stop_ev.set(); mon.join(timeout=1)
            try: os.remove(log_file)
            except Exception: pass
        elapsed = round(time.time()-start, 3)
        mem = round(peak_kb[0]/1024, 2)

        if timeout:
            return {"verdict": V_TLE, "time_used": elapsed, "memory_used": mem,
                    "exit_code": sol_rc, "error": "交互超时"}
        if mem > ml:
            return {"verdict": V_MLE, "time_used": elapsed, "memory_used": mem,
                    "exit_code": sol_rc, "error": f"内存超限: {mem:.1f}MB > {ml}MB"}
        # 以 interactor 判定为准：inter_rc==0 即 AC
        if inter_rc == 0:
            return {"verdict": V_AC, "time_used": elapsed, "memory_used": mem,
                    "exit_code": sol_rc, "error": None}
        # interactor 已明确判定失败（testlib: 1=WA 2=PE 3=FAIL 4=DIRT）：
        # 选手随后因管道 SIGPIPE（-13）被信号打断，属于交互正常结束，应保留 interactor 的判定（WA）
        if inter_rc in (1, 2, 3, 4) and (first_exit and first_exit[0] == 'inter' or sol_rc == -13):
            return {"verdict": V_WA, "time_used": elapsed, "memory_used": mem,
                    "exit_code": sol_rc, "error": f"交互判定失败 (interactor exit={inter_rc})"}
        if sol_rc not in (0, None):
            # 选手自身崩溃（先于 interactor 判定，信号退出等）→ RE
            return {"verdict": V_RE, "time_used": elapsed, "memory_used": mem,
                    "exit_code": sol_rc, "error": f"运行时错误 (exit={sol_rc})"}
        return {"verdict": V_WA, "time_used": elapsed, "memory_used": mem,
                "exit_code": sol_rc, "error": f"交互判定失败 (interactor exit={inter_rc})"}
    except Exception as e:
        return {"verdict": V_SE, "time_used": 0, "memory_used": 0, "exit_code": None,
                "error": f"交互异常: {e}"}
