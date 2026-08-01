"""根据环境变量生成评测机配置"""
import os

port = os.environ.get('JUDGE_PORT', '8000')
cpu = os.environ.get('JUDGE_CPU_LIMIT', '0.5')
mem = os.environ.get('JUDGE_MEM_LIMIT', '512m')
pool = os.environ.get('JUDGE_POOL_SIZE', '3')

config = f'''
DOCKER_IMAGE = "judge-sandbox:latest"
POOL_SIZE = {pool}
CONTAINER_MEMORY_LIMIT = "{mem}"
CONTAINER_CPU_LIMIT = {cpu}
CONTAINER_NETWORK_DISABLED = True
CONTAINER_TIMEOUT = 30

TEMP_DIR = "/tmp/judge_workspace"
import os
os.makedirs(TEMP_DIR, exist_ok=True)

SHARED_HOST_DIR = "/tmp/judge_shared"
CONTAINER_INPUT_DIR = "/tmp/judge_input"
CONTAINER_OUTPUT_DIR = "/tmp/judge_output"
SHARED_CONTAINER_DIR = "/tmp/shared"

SUPPORTED_LANGUAGES = {{
    "c": {{"ext": ".c"}}, "cpp14": {{"ext": ".cpp"}},
    "cpp17": {{"ext": ".cpp"}}, "cpp20": {{"ext": ".cpp"}},
    "python3": {{"ext": ".py"}},
}}

DEFAULT_TIME_LIMIT = 2.0
DEFAULT_MEMORY_LIMIT = 128
DEFAULT_OUTPUT_LIMIT = 1024 * 1024

TASK_STATUS = {{"PENDING": "pending", "RUNNING": "running",
               "COMPLETED": "completed", "FAILED": "failed"}}

VERDICT_AC = "AC"; VERDICT_WA = "WA"; VERDICT_TLE = "TLE"
VERDICT_MLE = "MLE"; VERDICT_RE = "RE"; VERDICT_OLE = "OLE"
VERDICT_CE = "CE"; VERDICT_SE = "SE"
JUDGE_PARALLEL_WORKERS = 3
'''
with open('/app/config.py', 'w') as f:
    f.write(config)
print(f"[Config] port={port} cpu={cpu} mem={mem} pool={pool}")
