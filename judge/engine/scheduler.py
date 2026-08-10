"""任务调度器"""
import uuid, asyncio, time
from typing import Optional
from models import JudgeRequest, JudgeResult, TestResult
from config import POOL_SIZE, DEFAULT_TIME_LIMIT, DEFAULT_MEMORY_LIMIT, DEFAULT_OUTPUT_LIMIT, TASK_STATUS
from docker_runner import docker_runner, task_progress

class TaskScheduler:
    def __init__(self):
        self._tasks: dict[str, JudgeResult] = {}
        self._queue: asyncio.Queue = asyncio.Queue()
        self._running_tasks: set[str] = set()
        self._worker_task: Optional[asyncio.Task] = None
        self._data_dirs: dict[str, str] = {}

    async def start(self):
        if self._worker_task is None: self._worker_task = asyncio.create_task(self._worker_loop())
        return self

    async def stop(self):
        if self._worker_task: self._worker_task.cancel(); self._worker_task = None

    async def submit(self, request: JudgeRequest) -> str:
        task_id = str(uuid.uuid4())
        max_s = sum(tc.score if tc.score else 1.0 for tc in request.test_cases)
        self._tasks[task_id] = JudgeResult(task_id=task_id, status=TASK_STATUS["PENDING"], language=request.language.value, total_tests=len(request.test_cases), passed_tests=0, score=0.0, max_score=max_s)
        task_progress[task_id] = {"status": "running", "total": len(request.test_cases), "completed": 0, "results": [None]*len(request.test_cases), "score": 0.0}
        await self._queue.put({"task_id": task_id, "request": request})
        return task_id

    def get_result(self, task_id: str) -> Optional[JudgeResult]: return self._tasks.get(task_id)

    async def _worker_loop(self):
        while True:
            try:
                td = await self._queue.get()
                while len(self._running_tasks) >= POOL_SIZE: await asyncio.sleep(0.5)
                asyncio.create_task(self._execute_task(td))
            except asyncio.CancelledError: break
            except Exception as e: print(f"[Scheduler] {e}")

    async def _execute_task(self, td: dict):
        task_id, req = td["task_id"], td["request"]
        result = self._tasks[task_id]
        self._running_tasks.add(task_id)
        result.status = TASK_STATUS["RUNNING"]
        start = time.time()
        try:
            raw_cases = td.get('test_cases_override') or req.test_cases
            data_dir = td.get('data_dir') or self._data_dirs.pop(task_id, ''); print(f'[DEBUG] task={task_id[:8]} data_dir={data_dir}')
            cases = [{"input": tc.input if hasattr(tc,'input') else tc.get('input',''), 
                      "expected_output": tc.expected_output if hasattr(tc,'expected_output') else tc.get('expected_output',''),
                      "time_limit": (tc.time_limit or DEFAULT_TIME_LIMIT) if hasattr(tc,'time_limit') else tc.get('time_limit', DEFAULT_TIME_LIMIT),
                      "memory_limit": (tc.memory_limit or DEFAULT_MEMORY_LIMIT) if hasattr(tc,'memory_limit') else (tc.get('memory_limit') or DEFAULT_MEMORY_LIMIT),
                      "score": tc.score if hasattr(tc,'score') and tc.score else tc.get('score', 1.0)}
                     for tc in raw_cases]
            import json as _json
            dr = await docker_runner.run_judge(task_id=task_id, language=req.language.value, code=req.code,
                test_cases=cases, checker_code=req.checker,
                time_limit=req.time_limit or DEFAULT_TIME_LIMIT, memory_limit=req.memory_limit or DEFAULT_MEMORY_LIMIT,
                output_limit=req.output_limit or DEFAULT_OUTPUT_LIMIT, data_dir=data_dir)
            print(f'[DEBUG] dr status={dr.get("status")} results={_json.dumps(dr.get("results",[]))[:200]}')
            result.status = dr.get("status", TASK_STATUS["FAILED"])
            result.system_error = dr.get("system_error")
            result.compile_error = dr.get("compile_error")
            result.total_time = round(time.time() - start, 3)
            passed = 0; total_score = 0.0; peak = 0
            for r in dr.get("results", []):
                tr = TestResult(**r); result.results.append(tr)
                if tr.passed: passed += 1
                total_score += tr.score
                if tr.memory_used and tr.memory_used > peak: peak = tr.memory_used
            result.passed_tests = passed; result.peak_memory = round(peak, 2)
            result.score = round(total_score, 2)
        except Exception as e:
            result.status = TASK_STATUS["FAILED"]; result.system_error = f"异常: {e}"
        finally:
            self._running_tasks.discard(task_id)
            # 延迟清理内存（60秒后删除任务，给 worker 时间取结果）
            async def _cleanup():
                await asyncio.sleep(60)
                self._tasks.pop(task_id, None)
                if hasattr(__import__('docker_runner'), 'task_progress'):
                    __import__('docker_runner').task_progress.pop(task_id, None)
            asyncio.ensure_future(_cleanup())

scheduler = TaskScheduler()
