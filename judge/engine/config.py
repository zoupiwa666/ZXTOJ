"""评测机配置"""
import os

DOCKER_IMAGE = "judge-sandbox:latest"
# 从环境变量读取（start.sh 通过 -e 传入，对应 .env 配置）
POOL_SIZE = int(os.environ.get("JUDGE_POOL_SIZE", "3") or "3")
CONTAINER_MEMORY_LIMIT = os.environ.get("JUDGE_MEM_LIMIT", "512m") or "512m"
CONTAINER_CPU_LIMIT = float(os.environ.get("JUDGE_CPU_LIMIT", "0.5") or "0.5")
CONTAINER_NETWORK_DISABLED = True
CONTAINER_TIMEOUT = 300

TEMP_DIR = "/tmp/judge_workspace"
os.makedirs(TEMP_DIR, exist_ok=True)

SHARED_HOST_DIR = "/tmp/judge_shared"
CONTAINER_INPUT_DIR = "/tmp/judge_input"
CONTAINER_OUTPUT_DIR = "/tmp/judge_output"
SHARED_CONTAINER_DIR = "/tmp/shared"

SUPPORTED_LANGUAGES = {
    "c": {"ext": ".c"}, "cpp14": {"ext": ".cpp"},
    "cpp17": {"ext": ".cpp"}, "cpp20": {"ext": ".cpp"},
    "python3": {"ext": ".py"},
}

DEFAULT_TIME_LIMIT = 2.0
DEFAULT_MEMORY_LIMIT = 128
DEFAULT_OUTPUT_LIMIT = 1024 * 1024  # 1MB 最大输出

TASK_STATUS = {"PENDING": "pending", "RUNNING": "running",
               "COMPLETED": "completed", "FAILED": "failed"}

# 评测判决
VERDICT_AC = "AC"   # Accepted
VERDICT_WA = "WA"   # Wrong Answer
VERDICT_TLE = "TLE" # Time Limit Exceeded
VERDICT_MLE = "MLE" # Memory Limit Exceeded
VERDICT_RE = "RE"   # Runtime Error
VERDICT_OLE = "OLE" # Output Limit Exceeded
VERDICT_CE = "CE"   # Compile Error
VERDICT_SE = "SE"   # System Error

# 容器内并发评测线程数（每个测试用例用独立子进程，线程负责调度）
JUDGE_PARALLEL_WORKERS = 3  # 容器内并行评测数
