"""评测机 API + 测试前端"""
import sys, os, json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, StreamingResponse
import asyncio
from contextlib import asynccontextmanager
from models import JudgeRequest, JudgeResponse, JudgeResult, TestCase
from scheduler import scheduler
from docker_runner import pool, task_progress
from package_parser import parse_package
from fastapi import UploadFile, File
from models import PackageInfo

@asynccontextmanager
async def lifespan(app: FastAPI):
    pool.start_pool()
    await scheduler.start()
    print("[Judge] 🚀 评测机就绪")
    yield
    await scheduler.stop()
    pool.stop_pool()

app = FastAPI(title="代码评测机", version="3.0.0", lifespan=lifespan)
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

@app.get("/")
async def root():
    return HTMLResponse(content=HTML_PAGE)

@app.post("/judge", response_model=JudgeResponse)
async def create_judge(request: JudgeRequest):
    return await _do_judge(request.language.value, request.code, request.test_cases, request.checker, request.time_limit, request.memory_limit)

async def _do_judge(language, code, test_cases, checker, time_limit, memory_limit):
    from models import Language
    req = JudgeRequest(language=Language(language), code=code, test_cases=test_cases, checker=checker, time_limit=time_limit or 2.0, memory_limit=memory_limit or 128)
    task_id = await scheduler.submit(req)
    return JudgeResponse(task_id=task_id, status="pending", message="任务已提交")

@app.post("/judge_by_problem")
async def judge_by_problem(data: dict):
    pid = data.get('problem_id','')
    if not pid: raise HTTPException(400, "need problem_id")
    scheduler._data_dirs["pending"] = "/data/problems/" + pid
    from models import Language
    req = JudgeRequest(language=Language(data.get('language','python3')), code=data.get('code',''), test_cases=[], checker=data.get('checker'), time_limit=data.get('time_limit',2.0), memory_limit=data.get('memory_limit',128))
    task_id = await scheduler.submit(req)
    scheduler._data_dirs[task_id] = "/data/problems/" + pid
    return {"task_id": task_id, "status": "pending", "message": "ok"}

@app.get("/result/{task_id}", response_model=JudgeResult)
async def get_result(task_id: str):
    r = scheduler.get_result(task_id)
    if not r: raise HTTPException(404, f"任务 {task_id} 不存在")
    return r

@app.post("/parse_package", response_model=PackageInfo)
async def parse_package_endpoint(file: UploadFile = File(...)):
    """上传并解析数据包"""
    data = await file.read()
    result = parse_package(data, file.filename or "package.zip")
    return PackageInfo(**result)

@app.get("/stream/{task_id}")
async def stream_results(task_id: str):
    """SSE 流式推送评测进度"""
    async def event_generator():
        sent = set()
        last_status = None
        for _ in range(600):
            prog = task_progress.get(task_id)
            if prog is None:
                await asyncio.sleep(0.1)
                continue
            # 状态变化时推送（compiling → 编译中）
            cur_status = prog.get("status", "")
            if cur_status != last_status:
                last_status = cur_status
                if cur_status == "compiling":
                    yield f"data: {json.dumps({'status':'compiling','_interim': prog.get('interim','编译中...')}, ensure_ascii=False)}\n\n"
                elif cur_status == "running":
                    yield f"data: {json.dumps({'status':'running','_interim': prog.get('interim','评测中...')}, ensure_ascii=False)}\n\n"
            results = prog.get("results", [])
            for i, r in enumerate(results):
                if r is not None and i not in sent:
                    sent.add(i)
                    line = json.dumps(r, ensure_ascii=False)
                    yield f"data: {line}\n\n"
            if prog.get("status") in ("completed", "failed", "compile_error"):
                done_data = json.dumps({"status": prog.get("status", "")})
                yield f"event: done\ndata: {done_data}\n\n"
                break
            await asyncio.sleep(0.1)
    return StreamingResponse(event_generator(), media_type="text/event-stream")

@app.get("/health")
async def health():
    return {"status":"ok","pool_total":len(pool._pool),"pool_idle":sum(1 for c in pool._pool if not c["busy"]),
            "pool_busy":sum(1 for c in pool._pool if c["busy"]),
            "running_tasks":len(scheduler._running_tasks),"queued":scheduler._queue.qsize()}

HTML_PAGE = """<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>代码评测机</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);min-height:100vh;color:#e0e0e0;display:flex;justify-content:center;padding:20px}
.container{max-width:900px;width:100%}
h1{text-align:center;font-size:2em;margin:20px 0;background:linear-gradient(90deg,#f093fb,#f5576c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.card{background:rgba(255,255,255,.05);border-radius:16px;padding:24px;margin-bottom:20px;border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(10px)}
.card h2{font-size:1.2em;color:#b388ff;margin-bottom:16px}
.row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.row>div{flex:1;min-width:200px}
label{display:block;font-size:.85em;color:#aaa;margin-bottom:4px}
select,textarea,input{width:100%;padding:10px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(0,0,0,.3);color:#e0e0e0;font-size:.9em;font-family:monospace;transition:.2s}
select:focus,textarea:focus,input:focus{outline:none;border-color:#b388ff;box-shadow:0 0 0 3px rgba(179,136,255,.15)}
textarea{resize:vertical;min-height:120px}
.test-group{border:1px dashed rgba(255,255,255,.1);border-radius:12px;padding:16px;margin-bottom:12px;position:relative}
.test-group .num{position:absolute;top:-10px;left:12px;background:#b388ff;color:#000;font-size:.8em;padding:2px 10px;border-radius:10px;font-weight:bold}
.test-row{display:flex;gap:12px;flex-wrap:wrap}
.test-row>div{flex:1;min-width:120px}
.btn{background:linear-gradient(135deg,#f093fb,#f5576c);border:none;color:#fff;padding:12px 40px;border-radius:10px;font-size:1.1em;font-weight:bold;cursor:pointer;transition:.3s;width:100%;letter-spacing:1px}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(245,87,108,.4)}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
.results{margin-top:16px}
.result-card{padding:12px 16px;border-radius:8px;margin:8px 0;display:flex;align-items:center;gap:12px;font-size:.9em}
.AC{background:rgba(0,200,83,.15);border-left:3px solid #00c853}
.WA{background:rgba(255,23,68,.15);border-left:3px solid #ff1744}
.TLE{background:rgba(255,171,0,.15);border-left:3px solid #ffab00}
.RE{background:rgba(255,61,0,.15);border-left:3px solid #ff3d00}
.MLE{background:rgba(213,0,249,.15);border-left:3px solid #d500f9}
.OLE{background:rgba(0,145,234,.15);border-left:3px solid #0091ea}
.CE{background:rgba(255,145,0,.15);border-left:3px solid #ff9100}
.SE{background:rgba(158,158,158,.15);border-left:3px solid #9e9e9e}
.verdict-badge{padding:4px 12px;border-radius:20px;font-weight:bold;font-size:.85em;min-width:50px;text-align:center}
.AC .verdict-badge{background:#00c853;color:#000}
.WA .verdict-badge{background:#ff1744;color:#fff}
.TLE .verdict-badge{background:#ffab00;color:#000}
.RE .verdict-badge{background:#ff3d00;color:#fff}
.MLE .verdict-badge{background:#d500f9;color:#fff}
.OLE .verdict-badge{background:#0091ea;color:#fff}
.CE .verdict-badge{background:#ff9100;color:#000}
.SE .verdict-badge{background:#9e9e9e;color:#fff}
.info{flex:1;font-size:.85em;color:#aaa}
.score-total{text-align:center;font-size:2em;font-weight:bold;margin:16px 0;background:linear-gradient(90deg,#f093fb,#f5576c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.loading{text-align:center;padding:20px;color:#aaa}
.error-msg{color:#ff5252;font-size:.85em;margin-top:6px}
.spinner{display:inline-block;width:20px;height:20px;border:2px solid rgba(255,255,255,.2);border-top:2px solid #b388ff;border-radius:50%;animation:spin .8s linear infinite;margin-right:8px}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="container">
<h1>⚡ 代码评测机</h1><div style="text-align:center;margin-bottom:20px"><a href="http://156.239.236.66:1226/" target="_blank" style="color:#b388ff;text-decoration:none;font-size:.9em">📚 题目列表</a></div>

<div class="card">
<h2>📝 提交代码</h2>
<div class="row">
<div><label>语言</label><select id="lang"><option value="python3">Python 3</option><option value="cpp17">C++17</option><option value="cpp20">C++20</option><option value="cpp14">C++14</option><option value="c">C</option></select></div>
<div style="flex:2"><label>时间限制(秒)</label><input type="number" id="time_limit" value="2" step="0.5" min="0.5"></div>
<div><label>内存限制(MB)</label><input type="number" id="memory_limit" value="128" step="8" min="16"></div>
</div>
<label>代码</label>
<textarea id="code" placeholder="输入你的代码...">print(input())</textarea>
</div>

<div class="card">
<h2>🧪 测试数据</h2>
<div style="margin-bottom:16px;display:flex;gap:12px;align-items:center">
  <label style="cursor:pointer;background:rgba(179,136,255,.2);border:1px dashed #b388ff;padding:8px 20px;border-radius:8px;font-size:.9em;display:flex;align-items:center;gap:6px">
    📦 上传数据包 (.zip/.tar.gz)
    <input type="file" id="packageFile" accept=".zip,.tar.gz,.tgz,.tar" onchange="uploadPackage()" style="display:none">
  </label>
  <span id="packageName" style="color:#b388ff;font-size:.85em"></span>
  <div id="checkerArea" style="display:none;margin-top:8px">
  <label>🔍 Checker (数据包自带)</label>
  <textarea id="checkerCode" rows="4" style="font-size:.8em" placeholder="def check(input, output, expected): ..."></textarea>
</div>
<div id="packageLoading" style="display:none;color:#aaa"><span class="spinner"></span>解析中...</div>
</div>
<div id="testcases"></div>
<div style="text-align:right;margin:8px 0"><button onclick="addTest()" style="background:rgba(255,255,255,.1);border:1px dashed rgba(255,255,255,.3);color:#aaa;padding:6px 20px;border-radius:8px;cursor:pointer">+ 添加测试点</button></div>
</div>

<button class="btn" onclick="submitJudge()" id="submitBtn">🚀 提交评测</button>

<div class="card" id="resultCard" style="display:none">
<h2>📊 评测结果</h2>
<div class="score-total" id="scoreTotal"></div>
<div class="results" id="results">
<div id="streamStatus" style="text-align:center;color:#aaa;font-size:.9em;display:none">
  <span class="spinner"></span>评测中... <span id="streamCount">0/0</span>
</div>
</div>
<div id="errorBox"></div>
</div>
</div>

<script>
function addTest(){
 const n=document.querySelectorAll('.test-group').length;
 const d=document.getElementById('testcases');
 d.insertAdjacentHTML('beforeend',`<div class="test-group"><span class="num">#${n+1}</span>
  <div class="test-row">
   <div><label>输入</label><textarea rows="2" class="t-in" placeholder="stdin 输入"></textarea></div>
   <div><label>预期输出</label><textarea rows="2" class="t-out" placeholder="期望输出"></textarea></div>
   <div style="max-width:80px"><label>分值</label><input type="number" class="t-score" value="10" min="0" step="1"></div>
  </div></div>`);
}
function getTests(){
 return [...document.querySelectorAll('.test-group')].map(g=>({
  input:g.querySelector('.t-in').value,
  expected_output:g.querySelector('.t-out').value,
  score:parseFloat(g.querySelector('.t-score').value)||10
 }));
}
// 默认3组
for(let i=0;i<3;i++) addTest();

async function uploadPackage(){
 const f=document.getElementById('packageFile').files[0];
 if(!f) return;
 document.getElementById('packageLoading').style.display='block';
 const form=new FormData(); form.append('file',f);
 try{
  const r=await fetch('/parse_package',{method:'POST',body:form});
  const d=await r.json();
  if(d.error){ alert('解析失败: '+d.error); return; }
  document.getElementById('checkerCode').value = d.checker || '';
  document.getElementById('checkerArea').style.display = d.checker ? 'block' : 'none';
  document.getElementById('packageName').textContent='📋 '+d.name+' ('+d.test_cases_count+'个测试点)';
  document.getElementById('time_limit').value=d.time_limit;
  document.getElementById('memory_limit').value=d.memory_limit;
  // 填充测试用例
  document.getElementById('testcases').innerHTML='';
  d.test_cases.forEach((tc,i)=>{
   addTest();
   const g=document.querySelectorAll('.test-group')[i];
   if(g){
    g.querySelector('.t-in').value=tc.input;
    g.querySelector('.t-out').value=tc.expected_output;
    g.querySelector('.t-score').value=tc.score;
   }
  });
 }catch(e){ alert('上传失败: '+e.message); }
 finally{ document.getElementById('packageLoading').style.display='none'; }
}
async function submitJudge(){
 const btn=document.getElementById('submitBtn');
 btn.disabled=true; btn.textContent='⏳ 评测中...';
 const card=document.getElementById('resultCard');
 card.style.display='block';
 document.getElementById('results').innerHTML='<div class="loading"><span class="spinner"></span>正在评测...</div>';
 document.getElementById('scoreTotal').textContent='';
 document.getElementById('errorBox').innerHTML='';

 try{
  const body={
   language:document.getElementById('lang').value,
   code:document.getElementById('code').value,
   test_cases:getTests(),
   time_limit:parseFloat(document.getElementById('time_limit').value)||2,
   memory_limit:parseInt(document.getElementById('memory_limit').value)||128
  };
  const checkerVal=document.getElementById('checkerCode').value.trim();
  if(checkerVal) body.checker=checkerVal;
  const r1=await fetch('/judge',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const j1=await r1.json();
  const taskId=j1.task_id;

  // SSE 流式接收
  const ss=document.getElementById('streamStatus');if(ss)ss.style.display='block';
  const es=new EventSource('/stream/'+taskId);
  let total=body.test_cases.length;
  const sc=document.getElementById('streamCount');if(sc)sc.textContent='0/'+total;
  
  es.onerror=function(){return}; // 忽略连接重置
  es.onmessage=function(e){
   const r=JSON.parse(e.data);
   if(r._interim){
     const ss=document.getElementById('streamStatus');
     if(ss)ss.innerHTML='<span class="spinner"></span>'+r._interim;
     return;
   }
   addStreamResult(r);
   const done=document.querySelectorAll('.result-card').length;
   const sc2=document.getElementById('streamCount');if(sc2)sc2.textContent=done+'/'+total;
  };
  es.addEventListener('done',function(e){
   es.close();
   const ss2=document.getElementById('streamStatus');if(ss2)ss2.style.display='none';
   fetch('/result/'+taskId).then(r=>r.json()).then(d=>{
    document.getElementById('scoreTotal').textContent=d.score+' / '+(d.max_score||100);
   });
  });
  es.onerror=function(){
   es.close();
   const ss2=document.getElementById('streamStatus');if(ss2)ss2.style.display='none';
   fetch('/result/'+taskId).then(r=>r.json()).then(d=>showResult(d));
  };
 }catch(e){
  document.getElementById('errorBox').innerHTML=`<div class="error-msg">请求失败: ${e.message}</div>`;
  document.getElementById('results').innerHTML='';
 }finally{
  btn.disabled=false; btn.textContent='🚀 提交评测';
 }
}

function addStreamResult(r){
 const v=r.verdict||'SE';
 const el=document.getElementById('res-'+r.test_case_index);
 if(el) return; // 已存在
 const div=document.createElement('div');
 div.id='res-'+r.test_case_index;
 div.className='result-card '+v;
 div.innerHTML=`<span class="verdict-badge">${v}</span>
  <div class="info">测试点 #${r.test_case_index+1} | 得分: <b>${r.score}</b> | 耗时: <b>${(r.time_used||0).toFixed(3)}s</b> | 内存: <b>${(r.memory_used||0).toFixed(1)}MB</b>
  ${r.error?`<br><span style="color:#ff5252;font-size:.8em">${r.error.substring(0,100)}</span>`:''}
  </div>`;
 // 插入到 streamStatus 之前
 const st=document.getElementById('streamStatus');
 const resDiv=document.getElementById('results');if(resDiv&&st)resDiv.insertBefore(div,st);else if(resDiv)resDiv.appendChild(div);
}
function showResult(d){
 document.getElementById('scoreTotal').textContent=`${d.score} / ${d.max_score||100}`;
 if(d.system_error){
  document.getElementById('errorBox').innerHTML=`<div class="error-msg">系统错误: ${d.system_error.substring(0,200)}</div>`;
  return;
 }
 if(d.compile_error){
  document.getElementById('errorBox').innerHTML=`<div class="error-msg">编译错误:<br><pre style="font-size:.8em;margin-top:4px">${d.compile_error.substring(0,500)}</pre></div>`;
  return;
 }
 let html='';
 d.results.forEach((r,i)=>{
  const v=r.verdict||'SE';
  html+=`<div class="result-card ${v}">
   <span class="verdict-badge">${v}</span>
   <div class="info">
    测试点 #${i+1} | 得分: <b>${r.score}</b> | 耗时: <b>${(r.time_used||0).toFixed(3)}s</b> | 内存: <b>${(r.memory_used||0).toFixed(1)}MB</b>
    ${r.error?`<br><span style="color:#ff5252;font-size:.8em">${r.error.substring(0,100)}</span>`:''}
   </div>
  </div>`;
 });
 document.getElementById('results').innerHTML=html;
 document.getElementById('errorBox').innerHTML='';
}
</script>
</body>
</html>"""

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)

@app.post("/judge_problem")
async def judge_problem(request: dict):
    """按题目ID评测 - 自动拉取测试数据"""
    import pymysql, sys, os
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    
    problem_id = request.get('problem_id', '')
    code = request.get('code', '')
    language = request.get('language', 'python3')
    time_limit = request.get('time_limit', 2.0)
    memory_limit = request.get('memory_limit', 128)
    checker = request.get('checker')
    
    if not problem_id or not code:
        raise HTTPException(400, "缺少 problem_id 或 code")
    
    # 连接 MySQL 拉测试数据
    try:
        conn = pymysql.connect(host='127.0.0.1', port=13306, user='root', password='cJGxJZEbLjh6EJzw', database='judge_problems', charset='utf8mb4')
        cur = conn.cursor()
        cur.execute("SELECT input_text, output_text, score FROM problem_testcases WHERE problem_id=%s ORDER BY sort_order", (problem_id,))
        rows = cur.fetchall()
        if not rows:
            raise HTTPException(404, "题目无测试数据")
        test_cases = [{"input": r[0], "expected_output": r[1], "score": float(r[2])} for r in rows]
        conn.close()
    except pymysql.err.OperationalError:
        raise HTTPException(500, "数据库连接失败")
    
    from models import JudgeRequest, Language
    from scheduler import scheduler
    req = JudgeRequest(language=Language(language), code=code, test_cases=[], time_limit=time_limit, memory_limit=memory_limit, checker=checker)
    # 直接调用 scheduler
    task_id = await scheduler.submit_direct(language, code, test_cases, checker, time_limit, memory_limit)
    return {"task_id": task_id, "status": "pending"}
