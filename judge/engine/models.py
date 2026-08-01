"""数据模型"""
from pydantic import BaseModel, Field
from typing import Optional
from enum import Enum

class Language(str, Enum):
    C = "c"; CPP14 = "cpp14"; CPP17 = "cpp17"; CPP20 = "cpp20"; PYTHON3 = "python3"

class TestCase(BaseModel):
    input: str = Field("", description="输入数据")
    expected_output: str = Field("", description="预期输出")
    time_limit: Optional[float] = Field(None, description="时间限制(秒)")
    memory_limit: Optional[int] = Field(None, description="内存限制(MB)")
    score: Optional[float] = Field(None, description="该测试点满分分值")

class JudgeRequest(BaseModel):
    language: Language
    code: str = Field(..., description="用户代码")
    test_cases: list[TestCase] = Field(..., description="测试用例列表")
    checker: Optional[str] = Field(None, description="自定义 checker, 返回 (bool, str, float) 或 float 或 bool")
    time_limit: Optional[float] = Field(None)
    memory_limit: Optional[int] = Field(None)
    output_limit: Optional[int] = Field(None, description="输出大小限制(字节)")

class TestResult(BaseModel):
    test_case_index: int
    verdict: str = "SE"       # AC / WA / TLE / MLE / RE / OLE / CE / SE
    passed: bool = False
    score: float = 0.0        # 该测试点得分
    output: str = ""
    expected_output: str = ""
    time_used: Optional[float] = None
    memory_used: Optional[float] = None
    exit_code: Optional[int] = None
    error: Optional[str] = None

class JudgeResponse(BaseModel):
    task_id: str; status: str = "pending"; message: str = "任务已接收"

class JudgeResult(BaseModel):
    task_id: str; status: str; language: str
    total_tests: int; passed_tests: int
    score: float; max_score: float = 0.0
    results: list[TestResult] = []
    compile_error: Optional[str] = None
    system_error: Optional[str] = None
    total_time: Optional[float] = None
    peak_memory: Optional[float] = None

from fastapi import UploadFile

class PackageInfo(BaseModel):
    """数据包解析结果"""
    name: str = ""                    # 题目名称
    test_cases_count: int = 0         # 测试点数量
    time_limit: float = 2.0           # 默认时间限制
    memory_limit: int = 128           # 默认内存限制
    scoring_mode: str = "default"     # 评分模式
    checker: Optional[str] = None         # checker 代码
    test_cases: list[TestCase] = []   # 解析后的测试用例
    error: Optional[str] = None       # 解析错误
