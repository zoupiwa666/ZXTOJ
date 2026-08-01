"""OJ 评测 Worker - 多线程 + 流式逐测试点更新"""
import time, json, threading, urllib.request, pymysql
from concurrent.futures import ThreadPoolExecutor

import os
JUDGE = os.environ.get('JUDGE_URL', 'http://127.0.0.1:18000')
DB = dict(host=os.environ.get('DB_HOST','127.0.0.1'), port=int(os.environ.get('DB_PORT',3306)), user=os.environ.get('DB_USER','root'), password=os.environ.get('DB_PASS',''), database='judge_problems', charset='utf8mb4')
MAX_WORKERS = 3

def api(path, data=None, timeout=120):
    req = urllib.request.Request(JUDGE + path,
        data=json.dumps(data).encode() if data else None,
        headers={'Content-Type':'application/json'} if data else {})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return json.loads(r.read())
    except Exception as e:
        return {'error': str(e)}

def save_partial(conn, sid, index, verdict, score, time_used, memory_used, passed, error=None):
    """保存单个测试点结果到 details JSON"""
    cur = conn.cursor()
    cur.execute("SELECT details FROM submissions WHERE id=%s", (sid,))
    row = cur.fetchone()
    details = json.loads(row[0]) if row and row[0] else []
    # 确保列表长度
    while len(details) <= index:
        details.append(None)
    details[index] = {
        "test_case_index": index, "verdict": verdict, "passed": passed,
        "score": score, "output": "", "expected_output": "",
        "time_used": time_used, "memory_used": memory_used,
        "exit_code": 0, "error": error
    }
    # 计算已得分
    total_score = sum(d['score'] for d in details if d)
    cur.execute("UPDATE submissions SET details=%s, score=%s WHERE id=%s", (json.dumps(details), total_score, sid))
    conn.commit()

def process_submission(sid, username, problem_id, language, code):
    try:
        conn = pymysql.connect(**DB)
        cur = conn.cursor(pymysql.cursors.DictCursor)
        print(f"[W] #{sid} {problem_id} judging")
        res = api('/judge_by_problem', {'problem_id': problem_id, 'language': language, 'code': code, 'time_limit': 2.0, 'memory_limit': 128})
        task_id = res.get('task_id')
        if not task_id:
            cur.execute("UPDATE submissions SET status='SE' WHERE id=%s", (sid,)); conn.commit(); conn.close(); return
        cur.execute("UPDATE submissions SET judge_task_id=%s WHERE id=%s", (task_id, sid)); conn.commit()

        # SSE 流式监听
        try:
            stream = urllib.request.urlopen(f"{JUDGE}/stream/{task_id}", timeout=120)
            done = False
            for line in stream:
                line = line.decode().strip()
                if line.startswith('event: done'):
                    done = True
                    break
                if not line.startswith('data:'): continue
                try:
                    data = json.loads(line[5:])
                except: continue
                if '_interim' in data: continue
                if 'test_case_index' not in data or data.get('test_case_index') is None: continue
                idx = int(data['test_case_index'])
                save_partial(conn, sid, idx, data.get('verdict','SE'), data.get('score',0),
                             data.get('time_used',0), data.get('memory_used',0), data.get('passed',False), data.get('error'))
                print(f"[W] #{sid} 测试点{idx+1}: {data.get('verdict','SE')}")
        except Exception as e:
            print(f"[W] #{sid} stream: {e}")
        # 如果 SSE 没拿到 done，再拉一次 result 确认
        if not done:
            try:
                final_check = api('/result/' + task_id)
                if final_check.get('status') not in ('pending','running'):
                    done = True
            except: pass

        # 拉最终结果完善细节
        result = api('/result/' + task_id)
        if result.get('results'):
            rs = result['results']
            status = 'CE' if result.get('status') == 'compile_error' else ('AC' if result.get('status') == 'completed' else 'SE')
            score = passed = 0; t = 0; mem = 0
            for r in rs:
                score += float(r.get('score', 0)); t += float(r.get('time_used', 0))
                m = float(r.get('memory_used', 0)); 
                if m > mem: mem = m
                if r.get('passed'): passed += 1
                if not r.get('passed') and status == 'AC': status = r.get('verdict', 'WA')
            if rs and passed == len(rs): status = 'AC'
            cur.execute("UPDATE submissions SET status=%s,score=%s,passed_tests=%s,peak_memory=%s,total_time=%s,details=%s WHERE id=%s",
                (status, score, passed, mem, round(t,3), json.dumps(rs), sid))
            conn.commit()
            print(f"[W] #{sid}: {status} {score}")
        conn.close()
    except Exception as e:
        print(f"[W] #{sid} error: {e}")
        try:
            conn = pymysql.connect(**DB)
            conn.cursor().execute("UPDATE submissions SET status='SE' WHERE id=%s", (sid,))
            conn.commit(); conn.close()
        except: pass

def main():
    print("[W] OJ Worker started (3 threads)")
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as pool:
        futures = set()
        while True:
            try:
                futures = {f for f in futures if not f.done()}
                while len(futures) < MAX_WORKERS:
                    conn = pymysql.connect(**DB)
                    cur = conn.cursor(pymysql.cursors.DictCursor)
                    cur.execute("SELECT * FROM submissions WHERE status='waiting' ORDER BY id LIMIT %s", (MAX_WORKERS - len(futures),))
                    subs = cur.fetchall()
                    conn.close()
                    if not subs: break
                    for s in subs:
                        futures.add(pool.submit(process_submission, s['id'], s['username'], s['problem_id'], s['language'], s['code']))
                time.sleep(2)
            except Exception as e:
                print(f"[W] main error: {e}"); time.sleep(3)

if __name__ == '__main__':
    main()
