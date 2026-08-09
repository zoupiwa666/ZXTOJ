"""预热容器池 + 持久化复用 + 流式评测"""
import os, json, shutil, subprocess, asyncio, uuid, glob, time
from pathlib import Path
from config import (
    JUDGE_PARALLEL_WORKERS, DOCKER_IMAGE, POOL_SIZE, CONTAINER_MEMORY_LIMIT,
    CONTAINER_CPU_LIMIT, CONTAINER_NETWORK_DISABLED, CONTAINER_TIMEOUT, TEMP_DIR,
    CONTAINER_INPUT_DIR, CONTAINER_OUTPUT_DIR, SUPPORTED_LANGUAGES
)

SHARED_HOST_DIR = "/tmp/judge_shared"
SHARED_CONTAINER_DIR = "/tmp/shared"
# 宿主机题目数据目录（start.sh 通过 DATA_HOST_DIR 自动传入，兼容任意部署路径；默认 /data）
HOST_DATA_DIR = os.environ.get("DATA_HOST_DIR", "/data") or "/data"
os.makedirs(SHARED_HOST_DIR, exist_ok=True)
os.chmod(SHARED_HOST_DIR, 0o777)

task_progress: dict[str, dict] = {}

class ContainerPool:
    def __init__(self):
        self._pool: list[dict] = []
        self._check_docker()

    def _check_docker(self):
        r = subprocess.run(["docker", "info"], capture_output=True, text=True, timeout=5)
        if r.returncode != 0: raise RuntimeError(f"Docker不可用:{r.stderr}")

    def start_pool(self):
        print(f"[Pool] 预热{POOL_SIZE}个容器...")
        self._cleanup_stale()
        for i in range(POOL_SIZE):
            try:
                cid = self._create_container(i)
                if cid:
                    self._pool.append({"id": i, "container_id": cid, "busy": False})
                    print(f"[Pool]  #{i}就绪:{cid[:12]}")
            except Exception as e: print(f"[Pool] #{i}失败:{e}")
        print(f"[Pool] ✅ {len(self._pool)}/{POOL_SIZE}")

    def _cleanup_stale(self):
        try:
            r = subprocess.run(["docker", "ps", "-a", "--filter", "name=judge-pool", "--format", "{{.ID}}"],
                               capture_output=True, text=True, timeout=5)
            for line in r.stdout.strip().split("\n"):
                cid = line.strip()
                if cid and len(cid) > 5:
                    subprocess.run(["docker", "rm", "-f", cid], capture_output=True, timeout=5)
        except: pass

    def _create_container(self, idx: int) -> str | None:
        name = f"judge-pool-{idx}-{uuid.uuid4().hex[:6]}"
        cmd = ["docker", "run", "-d", "--name", name,
               "--memory", str(CONTAINER_MEMORY_LIMIT),
               "--memory-swap", str(CONTAINER_MEMORY_LIMIT),
               "--cpus", str(CONTAINER_CPU_LIMIT), "--pids-limit", "64",
               "--security-opt", "no-new-privileges:true",
               "--security-opt", "seccomp=unconfined",   # 默认 seccomp 拦截 perf_event_open，放开（容器已无网络/无特权/只读，风险可控）
               "--cap-drop", "ALL",
               "--cap-add", "PERFMON",   # perf_event_open 指令计数（确定性评测）
               "--read-only", "--tmpfs", "/tmp:size=64m,nosuid",
               "-v", f"{SHARED_HOST_DIR}:{SHARED_CONTAINER_DIR}",
               "-v", f"{HOST_DATA_DIR}:/data:ro",
               "-v", f"{TEMP_DIR}:{TEMP_DIR}:ro",  # 只读挂载主机临时目录
               "--entrypoint", ""]
        if CONTAINER_NETWORK_DISABLED: cmd.extend(["--network", "none"])
        cmd.extend([DOCKER_IMAGE, "sh", "-c", "while true; do sleep 30; done"])
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
        if r.returncode != 0:
            print(f"[Pool] docker run错误:{r.stderr[:200]}"); return None
        cid = r.stdout.strip()
        subprocess.run(["docker", "exec", cid, "mkdir", "-p", CONTAINER_INPUT_DIR, CONTAINER_OUTPUT_DIR],
                       capture_output=True, timeout=5)
        check = subprocess.run(["docker", "ps", "--filter", f"id={cid}", "--format", "{{.ID}}"],
                               capture_output=True, text=True, timeout=5)
        if not check.stdout.strip():
            subprocess.run(["docker", "rm", "-f", cid], capture_output=True, timeout=5); return None
        return cid

    async def get_container(self) -> dict | None:
        for _ in range(30):
            for c in self._pool:
                if not c["busy"]: c["busy"] = True; return c
            await asyncio.sleep(0.5)
        return None

    def release_container(self, cid: str):
        for c in self._pool:
            if c["container_id"] == cid: c["busy"] = False; break

    def cleanup_workspace(self, cid: str):
        try:
            subprocess.run(["docker", "exec", cid, "sh", "-c",
                f"rm -rf {CONTAINER_INPUT_DIR}/* {CONTAINER_OUTPUT_DIR}/*"],
                capture_output=True, timeout=5)
        except: pass

    async def exec_judge_streaming(self, cid: str, host_workdir: str, task_id: str) -> dict:
        """流式版：边跑边读进度文件（status.json + prog_*.json），测试点完成即推送"""
        try:
            task_shared = os.path.join(SHARED_HOST_DIR, task_id)
            os.makedirs(task_shared, exist_ok=True)
            os.chmod(task_shared, 0o777)
            task_progress.setdefault(task_id, {})
            task_progress[task_id]["status"] = "running"
            task_progress[task_id].setdefault("results", [])
            seen = set()
            proc = await asyncio.create_subprocess_exec(
                "docker", "exec", cid,
                "python3", "/judge_system/judge_core.py",
                "--workdir", host_workdir,
                "--output-dir", CONTAINER_OUTPUT_DIR,
                "--shared-dir", f"{SHARED_CONTAINER_DIR}/{task_id}",
                stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
            start_t = time.time()
            timed_out = False
            while True:
                # 轮询状态文件（compiling / running / compile_error）
                try:
                    sp = os.path.join(task_shared, "status.json")
                    if os.path.exists(sp):
                        st = json.load(open(sp))
                        if st.get("status") in ("compiling", "running", "compile_error"):
                            task_progress[task_id]["status"] = st["status"]
                            if st.get("message"): task_progress[task_id]["interim"] = st["message"]
                except Exception:
                    pass
                # 轮询各测试点结果文件（完成一个读一个，立即推送）
                for fp in glob.glob(os.path.join(task_shared, "prog_*.json")):
                    if fp in seen: continue
                    seen.add(fp)
                    try:
                        r = json.load(open(fp))
                        idx = r.get("test_case_index")
                        if idx is not None:
                            res = task_progress[task_id].setdefault("results", [])
                            while len(res) <= idx: res.append(None)
                            res[idx] = r
                    except Exception:
                        pass
                # 等待进程结束（0.1s 粒度轮询）
                try:
                    await asyncio.wait_for(proc.wait(), timeout=0.1)
                    break
                except asyncio.TimeoutError:
                    pass
                if time.time() - start_t > CONTAINER_TIMEOUT + 10:
                    timed_out = True
                    break
            if timed_out:
                subprocess.run(["docker","exec",cid,"pkill","-f","judge_core"], capture_output=True, timeout=3)
                try: await proc.wait()
                except Exception: pass
                task_progress[task_id]["status"] = "failed"
                return {"status":"failed","system_error":"评测超时","results":[]}
            # 进程结束后：短暂等待并补读所有 prog 文件（快速测试点可能在轮询间隙全部完成）
            await asyncio.sleep(0.15)
            for fp in glob.glob(os.path.join(task_shared, "prog_*.json")):
                if fp in seen: continue
                seen.add(fp)
                try:
                    r = json.load(open(fp))
                    idx = r.get("test_case_index")
                    if idx is not None:
                        res = task_progress[task_id].setdefault("results", [])
                        while len(res) <= idx: res.append(None)
                        res[idx] = r
                except Exception:
                    pass
            out, err = await proc.communicate()
            # 读最终 result.json（完整结果含 output/expected）
            cat = subprocess.run(["docker","exec",cid,"cat",f"{CONTAINER_OUTPUT_DIR}/result.json"],
                capture_output=True, text=True, timeout=5)
            if cat.returncode == 0 and cat.stdout.strip():
                final = json.loads(cat.stdout)
                if task_id in task_progress:
                    # 最终结果合并进 task_progress，确保 stream 推送所有测试点（即使 prog 文件漏读）
                    task_progress[task_id]["status"] = final.get("status","completed")
                    if final.get("results"):
                        res = task_progress[task_id].setdefault("results", [])
                        for r in final["results"]:
                            idx = r.get("test_case_index")
                            if idx is not None:
                                while len(res) <= idx: res.append(None)
                                res[idx] = r
                return final
            return {"status":"failed","system_error":f"无结果: {err.decode()[:200]}","results":[]}
        except Exception as e:
            return {"status":"failed","system_error":f"评测错误:{e}","results":[]}
    def stop_pool(self):
        print("[Pool] 停止...")
        for c in self._pool:
            try: subprocess.run(["docker", "rm", "-f", c["container_id"]], capture_output=True, timeout=5)
            except: pass
        self._pool.clear()

pool = ContainerPool()

class DockerRunner:
    async def run_judge(self, task_id, language, code, test_cases,
                        checker_code=None, time_limit=None, memory_limit=None, output_limit=None, data_dir=None):
        workdir = Path(TEMP_DIR) / task_id
        workdir.mkdir(parents=True, exist_ok=True)
        try:
            ext = SUPPORTED_LANGUAGES[language]["ext"]
            (workdir / f"solution{ext}").write_text(code)
            (workdir / "test_cases.json").write_text(json.dumps(test_cases, ensure_ascii=False))
            if checker_code: (workdir / "checker.py").write_text(checker_code)
            (workdir / "task_config.json").write_text(json.dumps({
                "task_id": task_id, "language": language,
                "time_limit": time_limit, "memory_limit": memory_limit,
                "output_limit": output_limit, "parallel_workers": JUDGE_PARALLEL_WORKERS, "data_dir": data_dir or ""
            }))
            container = await pool.get_container()
            if not container:
                return {"status": "failed", "system_error": "无可用容器", "results": []}
            cid = container["container_id"]
            try:
                return await pool.exec_judge_streaming(cid, str(workdir), task_id)
            finally:
                pool.cleanup_workspace(cid)
                pool.release_container(cid)
        except Exception as e:
            return {"status": "failed", "system_error": f"错误:{e}", "results": []}
        finally:
            shutil.rmtree(workdir, ignore_errors=True)

    def get_running_count(self):
        return sum(1 for c in pool._pool if c["busy"])

docker_runner = DockerRunner()
