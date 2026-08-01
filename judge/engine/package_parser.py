"""数据包解析器 - 支持 zip/tar.gz + checker + 评分模式"""
import os, io, zipfile, tarfile, yaml, re
from pathlib import Path
from models import TestCase

SUPPORTED_MODES = ["default"]  # 可扩展

def parse_package(file_bytes: bytes, filename: str) -> dict:
    tmpdir = f"/tmp/pkg_{os.getpid()}"
    os.makedirs(tmpdir, exist_ok=True)
    
    try:
        # 1. 解压
        if filename.endswith('.zip'):
            _extract_zip(file_bytes, tmpdir)
        elif filename.endswith(('.tar.gz', '.tgz', '.tar')):
            _extract_tar(file_bytes, tmpdir)
        else:
            return {"error": f"不支持的文件格式: {filename}，仅支持 .zip / .tar.gz"}
        
        # 2. 查找 config.yaml
        yaml_path = _find_file(tmpdir, 'config.yaml')
        if not yaml_path:
            yaml_path = _find_file(tmpdir, 'config.yml')
        if not yaml_path:
            return {"error": "数据包中未找到 config.yaml"}
        
        with open(yaml_path, 'r', encoding='utf-8') as f:
            config = yaml.safe_load(f)
        
        if not config:
            return {"error": "config.yaml 为空或格式错误"}
        
        # 3. 解析基础配置
        name = config.get('name', Path(filename).stem)
        count = config.get('test_cases', 0)
        if not count or not isinstance(count, int) or count < 1:
            return {"error": "config.yaml 中 test_cases 必须为正整数"}
        
        tl = float(config.get('time_limit', 2.0))
        ml = int(config.get('memory_limit', 128))
        mode = str(config.get('scoring_mode', 'default'))
        if mode not in SUPPORTED_MODES:
            return {"error": f"不支持的评分模式: {mode}，目前支持: {SUPPORTED_MODES}"}
        
        # 4. 解析 checker
        checker_code = None
        # 方式A: config.yaml 中内嵌 checker
        if 'checker' in config and config['checker']:
            checker_code = config['checker']
        # 方式B: 数据包中的 checker.py 文件
        else:
            checker_path = _find_file(tmpdir, 'checker.py')
            if checker_path:
                checker_code = open(checker_path, 'r', encoding='utf-8').read()
        
        # 5. 解析每个测试点的分数（支持自定义分数）
        case_scores = config.get('scores', [])
        if case_scores and isinstance(case_scores, list):
            # 补全或截断到 count
            case_scores = case_scores[:count]
            while len(case_scores) < count:
                case_scores.append(round(100.0 / count, 2))
        else:
            case_scores = [round(100.0 / count, 2)] * count
        
        # 6. 查找测试数据文件
        base_dir = os.path.dirname(yaml_path)
        test_cases = []
        
        for i in range(1, count + 1):
            inp = _find_case_file(base_dir, tmpdir, i, 'in')
            out = _find_case_file(base_dir, tmpdir, i, 'out')
            
            if inp is None or out is None:
                return {"error": f"找不到测试点 #{i} 的输入/输出文件"}
            
            test_cases.append(TestCase(
                input=inp,
                expected_output=out,
                score=float(case_scores[i - 1]),
            ))
        
        return {
            "name": name,
            "test_cases_count": count,
            "time_limit": tl,
            "memory_limit": ml,
            "scoring_mode": mode,
            "checker": checker_code,
            "test_cases": test_cases,
            "error": None,
        }
    
    except yaml.YAMLError as e:
        return {"error": f"config.yaml 解析失败: {e}"}
    except Exception as e:
        return {"error": f"解析数据包失败: {e}"}
    finally:
        import shutil
        shutil.rmtree(tmpdir, ignore_errors=True)


def _extract_zip(data: bytes, dest: str):
    with zipfile.ZipFile(io.BytesIO(data)) as zf:
        zf.extractall(dest)


def _extract_tar(data: bytes, dest: str):
    mode = 'r:gz' if data[:2] == b'\x1f\x8b' else 'r'
    with tarfile.open(fileobj=io.BytesIO(data), mode=mode) as tf:
        tf.extractall(dest)


def _find_file(base: str, filename: str) -> str | None:
    for root, dirs, files in os.walk(base):
        for f in files:
            if f == filename:
                return os.path.join(root, f)
    return None


def _find_case_file(base: str, root_dir: str, case_num: int, ext: str) -> str | None:
    patterns = [
        f"{case_num}.{ext}", f"{case_num:02d}.{ext}", f"{case_num:03d}.{ext}",
        f"test{case_num}.{ext}", f"test_{case_num}.{ext}",
        f"data{case_num}.{ext}", f"data_{case_num}.{ext}",
    ]
    all_files = []
    for root, dirs, files in os.walk(root_dir):
        for f in files:
            all_files.append(os.path.join(root, f))
    for pat in patterns:
        for f in all_files:
            if os.path.basename(f) == pat:
                return open(f, 'r', encoding='utf-8', errors='replace').read()
    return None
