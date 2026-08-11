<?php
require __DIR__.'/inc/config.php';
require __DIR__.'/inc/auth.php';
requireRole('admin');
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['pid'] ?? '');
$sid = preg_replace('/[^a-f0-9]/', '', $_GET['sid'] ?? '');  // /chat/pid/sid 会话恢复
$prob = null;
if ($pid !== '') {
    $s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?"); $s->execute([$pid]);
    $prob = $s->fetch();
}
$pageTitle = 'AI 造数据工作台 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
?>
<style>
.studio-wrap{max-width:960px;margin:0 auto}
.studio-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap}
.studio-head h1{font-size:16px;color:#fff;font-weight:400;letter-spacing:1px;margin:0}
.studio-head .pid-tag{color:#5af;font-size:12px;border:1px solid #2a4a6c;padding:2px 10px}
.config-box{background:#1e1e1e;border:1px solid #2a2a2a;padding:14px 16px;margin-bottom:14px}
.config-box h3{font-size:12px;color:#999;font-weight:400;margin:0 0 10px;letter-spacing:1px}
.config-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px}
.config-row input,.config-row select,.config-row textarea{background:#000;border:1px solid #333;color:#ddd;padding:7px 10px;font-size:12px;font-family:inherit;outline:none}
.config-row input:focus,.config-row select:focus,.config-row textarea:focus{border-color:#5af}
.btn{padding:8px 22px;background:#2a2a2a;color:#ddd;border:none;font-size:12px;cursor:pointer;letter-spacing:1px;font-family:inherit}
.btn:hover{background:#3a3a3a;color:#fff}
.btn-blue{background:#1a3a5c;color:#5af}
.btn-blue:hover{background:#1f4a75;color:#7cf}
.btn-green{background:#0d3a20;color:#2ecc71}
.chat-box{background:#141414;border:1px solid #222;min-height:420px;max-height:65vh;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px}
.msg{max-width:85%;padding:10px 14px;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-word}
.msg.user{align-self:flex-end;background:#1a3a5c;color:#cde;border-radius:10px 10px 2px 10px}
.msg.ai{align-self:flex-start;background:#1c1c1c;border:1px solid #2a2a2a;color:#ccc;border-radius:2px 10px 10px 10px}
.msg.sys{align-self:center;background:transparent;color:#666;font-size:11px}
.msg.err{align-self:flex-start;background:#300;border:1px solid #500;color:#f66}
.ai-think{color:#8af;font-size:12px;opacity:.9}
.code-block{background:#0a0a0a;border:1px solid #222;margin-top:8px}
.code-block summary{cursor:pointer;padding:6px 10px;font-size:11px;color:#9a9;letter-spacing:1px;list-style:none}
.code-block summary:hover{color:#cfc}
.code-block pre{margin:0;padding:10px;font-size:11px;line-height:1.5;overflow-x:auto;color:#bdb;max-height:300px;overflow-y:auto}
.progress-line{display:flex;align-items:center;gap:10px;font-size:12px;color:#5af}
progress{flex:1;height:4px;accent-color:#5af;border:none;background:#222}
.input-bar{display:flex;gap:8px;margin-top:12px}
.input-bar input{flex:1;background:#000;border:1px solid #333;color:#ddd;padding:10px 14px;font-size:13px;outline:none}
.input-bar input:focus{border-color:#5af}
.input-bar input:disabled{opacity:.4}
.done-box{color:#2ecc71;font-size:13px}
.tool-call{border:1px solid #2a3a4a;border-radius:6px;margin:6px 0;background:#0f1520;overflow:hidden}
.tool-call summary{cursor:pointer;padding:6px 12px;font-size:12px;color:#8af;letter-spacing:.5px;list-style:none;display:flex;align-items:center;gap:6px}
.tool-call summary:hover{background:#16202e}
.tool-call summary::before{content:'▸';color:#5af}
.tool-call[open] summary::before{content:'▾'}
.tool-call .tool-body{padding:8px 12px;border-top:1px solid #222}
.tool-call .tb-title{font-size:10px;color:#666;letter-spacing:1px;margin:6px 0 3px}
.tool-call pre{margin:0;background:#0a0e14;border:1px solid #1c2733;padding:8px;font-size:11px;line-height:1.5;overflow-x:auto;color:#bdb;white-space:pre-wrap;word-break:break-all}
.tool-call .tb-ok{color:#2ecc71}.tool-call .tb-err{color:#ff6b6b}.tool-call .tb-run{color:#ffab00;font-size:11px}
.think-item{border:1px solid #2a2a3a;border-radius:6px;margin:4px 0;background:#12121c;overflow:hidden}
.think-item summary{cursor:pointer;padding:6px 12px;font-size:12px;color:#8af;list-style:none;display:flex;gap:6px;align-items:center}
.think-item summary:hover{background:#1a1a2a}
.think-item summary::before{content:'▸';color:#5af}
.think-item[open] summary::before{content:'▾'}
.think-item .think-body{padding:8px 12px;border-top:1px solid #222;color:#9ab;font-size:12px;line-height:1.7;white-space:pre-wrap;word-break:break-word;max-height:320px;overflow-y:auto}
</style>
<div class="studio-wrap">
  <div class="studio-head">
    <h1>🤖 AI 造数据工作台 <?php if($pid): ?><span class="pid-tag"><?=htmlspecialchars($pid)?></span><?php endif; ?></h1>
    <a href="edit.php?id=<?=urlencode($pid)?>" style="font-size:12px;color:#999;text-decoration:none">← 返回题目编辑</a>
  </div>

  <!-- 配置区 -->
  <div class="config-box" id="cfgBox">
    <h3>会话配置（开始后不可改，可多轮对话修改数据）</h3>
    <div class="config-row">
      <input id="cPid" value="<?=htmlspecialchars($pid)?>" placeholder="题目编号" style="flex:0 0 140px">
      <input id="cKey" type="password" placeholder="DeepSeek API Key" style="flex:1;min-width:200px">
      <input id="cCount" type="number" value="10" min="1" max="50" style="width:90px" title="数据组数">
    </div>
    <div class="config-row">
      <select id="cLang" style="width:130px">
        <option value="python3">std: Python3</option>
        <option value="cpp17">std: C++17</option>
        <option value="cpp14">std: C++14</option>
        <option value="cpp20">std: C++20</option>
        <option value="c">std: C</option>
      </select>
      <textarea id="cStd" rows="2" placeholder="标准解法 std（可选，留空由 AI 生成；将用它生成每组标准输出）" style="flex:1"></textarea>
    </div>
    <div class="config-row">
      <input id="cCkReq" placeholder="Checker 要求（可选，勾选启用）：如 忽略行末空格、允许误差 1e-6" style="flex:1">
      <input id="cExtra" placeholder="额外要求（可选）：如 前2组大边界、中间随机、最后一组最坏情况" style="flex:1">
    </div>
    <div class="config-row" style="align-items:center">
      <label style="font-size:12px;color:#999"><input type="checkbox" id="cCk" style="width:auto"> 需要 checker</label>
      <label style="font-size:12px;color:#999"><input type="checkbox" id="cSaveKey" style="width:auto"> 记住 key</label>
      <button class="btn btn-blue" onclick="startSession()" id="btnStart">🚀 开始会话</button>
      <button class="btn btn-blue" onclick="saveParams()" id="btnSave" style="display:none">💾 保存参数并重新生成</button>
      <span id="cfgMsg" style="font-size:12px;color:#999"></span>
    </div>
  </div>

  <!-- 对话区 -->
  <div class="chat-box" id="chatBox"></div>

  <div class="input-bar">
    <input id="userMsg" placeholder="提出修改要求，如：把第1组改成极限大数据 / checker 加个特判..." disabled>
    <button class="btn btn-blue" onclick="sendMsg()" id="btnSend" disabled>发送</button>
    <button class="btn btn-blue" onclick="openParams()" id="btnParams" style="display:none">⚙️ 调整参数</button>
    <button class="btn btn-green" onclick="applyData()" id="btnApply" disabled>✓ 应用数据</button>
  </div>
</div>

<script>
const pid = <?=json_encode($pid)?>;
const urlSid = <?=json_encode($sid)?>;
let sessionId = urlSid || null, since = 0, pollTimer = null, generating = false;
let curCfg = {count:10, need_checker:false, checker_req:'', extra_req:'', std_code:'', std_lang:'python3'};

function autoScroll(){
  const box = document.getElementById('chatBox');
  const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 100;
  if(nearBottom) box.scrollTop = box.scrollHeight;
}
function finalizeThink(){
  if(pendingThink){
    const s = pendingThink.querySelector('summary');
    if(s) s.textContent = '🧠 AI 思考（点击展开）';
    if(pendingThink._raf){ cancelAnimationFrame(pendingThink._raf); pendingThink._raf = null; }
    pendingThink = null; thinkRaf = false;
  }
}
function addMsg(html, cls){ const d=document.createElement('div'); d.className='msg '+cls; d.innerHTML=html; document.getElementById('chatBox').appendChild(d); chatBox.scrollTop=chatBox.scrollHeight; return d; }
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function codeBlock(title, code){ return '<details class="code-block"><summary>'+title+'（点击展开 '+code.length+' 字符）</summary><pre></pre></details>'; }

let curThink = null, roundNo = 0, pendingTool = null;
let pendingThink = null, thinkRaf = false;
function renderEvent(ev){
  const box = document.getElementById('chatBox');
  if(ev.type==='info'){ addMsg('🛠 ' + esc(ev.data), 'sys'); }
  else if(ev.type==='user'){ roundNo++; finalizeThink(); addMsg(esc(ev.data), 'user'); }
  else if(ev.type==='analysis_delta'){
    // 思考条目：一段思考一个可折叠条，rAF 防抖累积，不强制滚动
    if(!pendingThink){
      const det = document.createElement('details');
      det.className = 'think-item';
      const sum = document.createElement('summary'); sum.textContent = '🧠 AI 思考中...';
      det.appendChild(sum);
      const body = document.createElement('div'); body.className='think-body';
      det.appendChild(body);
      const wrap = document.createElement('div'); wrap.className='msg ai'; wrap.style.padding='2px 4px';
      wrap.appendChild(det);
      document.getElementById('chatBox').appendChild(wrap);
      pendingThink = {det, body, buf:''};
    }
    pendingThink.buf += ev.data;
    if(!pendingThink._raf){
      pendingThink._raf = requestAnimationFrame(()=>{
        if(pendingThink){ pendingThink.body.textContent = pendingThink.buf; pendingThink._raf = null; autoScroll(); }
      });
    }
  }
  else if(ev.type==='analysis_end' || ev.type==='analysis_text'){
    finalizeThink();
    if(ev.type==='analysis_text' && ev.data){
      const det = document.createElement('details');
      det.className = 'think-item';
      const sum = document.createElement('summary'); sum.textContent = '🧠 AI 思考（点击展开）';
      det.appendChild(sum);
      const body = document.createElement('div'); body.className='think-body'; body.textContent = ev.data;
      det.appendChild(body);
      const wrap = document.createElement('div'); wrap.className='msg ai'; wrap.style.padding='2px 4px';
      wrap.appendChild(det);
      document.getElementById('chatBox').appendChild(wrap);
    }
  }
  else if(ev.type==='analysis_text'){
    curThink = addMsg('<div class="ai-think">🧠 AI 思考</div><div></div>', 'ai');
    curThink.lastChild.textContent = ev.data;
  }
  else if(ev.type==='code'){
    const c = ev.data; let html = '<div class="ai" style="font-size:12px;color:#999;margin-top:6px">—— 生成产物 ——</div>';
    const blocks = [['生成器 generator.py', c.gen_code], ['标准解法 std.py'+(c.user_std?'(用户提供)':''), c.sol_code], ['config.yaml', c.config_yaml]];
    if(c.checker) blocks.push(['checker.py', c.checker]);
    const wrap = addMsg('', 'ai');
    for(const [t, code] of blocks){
      if(!code) continue;
      const d = document.createElement('details'); d.className='code-block';
      const s = document.createElement('summary'); s.textContent = '📄 ' + t + '（点击展开 ' + code.length + ' 字符）'; d.appendChild(s);
      const pre = document.createElement('pre'); pre.textContent = code; d.appendChild(pre);
      wrap.appendChild(d);
    }
  }
  else if(ev.type==='tool_delta'){
    finalizeThink();
    // 工具参数流式：名称出现即建卡片（调用中...），参数增量实时追加
    const d = ev.data || {};
    if(!pendingTool || (d.name && pendingTool.name !== d.name)){
      if(pendingTool && !pendingTool.finalized){
        pendingTool.sum.innerHTML = '🛠 ' + pendingTool.name + ' <span class="tb-run">⏳ 调用中...</span>';
      }
      const det = document.createElement('details');
      det.className = 'tool-call'; det.open = true;
      const sum = document.createElement('summary');
      sum.innerHTML = '🛠 ' + (d.name||'tool') + ' <span class="tb-run">⏳ 调用中...</span>';
      det.appendChild(sum);
      const body = document.createElement('div'); body.className='tool-body';
      const a1 = document.createElement('div'); a1.className='tb-title'; a1.textContent='参数（实时）'; body.appendChild(a1);
      const pre1 = document.createElement('pre'); pre1.textContent = ''; body.appendChild(pre1);
      const a2 = document.createElement('div'); a2.className='tb-title'; a2.textContent='结果'; body.appendChild(a2);
      const pre2 = document.createElement('pre'); pre2.textContent = ''; body.appendChild(pre2);
      det.appendChild(body);
      const wrap = document.createElement('div'); wrap.className='msg ai'; wrap.style.padding='4px 6px';
      wrap.appendChild(det);
      document.getElementById('chatBox').appendChild(wrap);
      pendingTool = {name: d.name||'tool', argsText:'', det, sum, pre1, pre2, finalized:false};
    }
    if(d.args_delta){
      pendingTool.argsText += d.args_delta;
      pendingTool.pre1.textContent = pendingTool.argsText;
      autoScroll();
    }
  }
  else if(ev.type==='tool'){
    // 工具执行完成：更新卡片（完整参数 + 结果 + 状态）
    const t = ev.data || {};
    const st = t.status === 'err' ? '<span class="tb-err">⚠️ 失败</span>' : '<span class="tb-ok">✓</span>';
    if(pendingTool && !pendingTool.finalized && pendingTool.name === t.name){
      pendingTool.sum.innerHTML = '🛠 ' + t.name + ' ' + st;
      pendingTool.pre1.textContent = JSON.stringify(t.args||{}, null, 1);
      pendingTool.pre2.textContent = t.result || '';
      pendingTool.finalized = true;
    } else {
      // 无流式前缀（如刷新重放）：直接建完成卡片
      const det = document.createElement('details');
      det.className = 'tool-call';
      const sum = document.createElement('summary');
      sum.innerHTML = '🛠 ' + (t.name||'tool') + ' ' + st;
      det.appendChild(sum);
      const body = document.createElement('div'); body.className='tool-body';
      const a1 = document.createElement('div'); a1.className='tb-title'; a1.textContent='参数'; body.appendChild(a1);
      const pre1 = document.createElement('pre'); pre1.textContent = JSON.stringify(t.args||{}, null, 1); body.appendChild(pre1);
      const a2 = document.createElement('div'); a2.className='tb-title'; a2.textContent='结果'; body.appendChild(a2);
      const pre2 = document.createElement('pre'); pre2.textContent = t.result || ''; body.appendChild(pre2);
      det.appendChild(body);
      const wrap = document.createElement('div'); wrap.className='msg ai'; wrap.style.padding='4px 6px';
      wrap.appendChild(det);
      document.getElementById('chatBox').appendChild(wrap);
    }
    autoScroll();
  }
  else if(ev.type==='progress'){
    let p = document.getElementById('progLine');
    if(!p){ p = document.createElement('div'); p.id='progLine'; p.className='progress-line'; p.innerHTML='<span>🏃 运行数据生成器</span><progress max="100" value="0"></progress><span class="pt"></span>'; box.appendChild(p); }
    p.querySelector('progress').value = Math.round(ev.data.i/ev.data.n*100);
    p.querySelector('.pt').textContent = ev.data.i + '/' + ev.data.n;
    autoScroll();
  }
  else if(ev.type==='done'){ finalizeThink(); pendingTool=null;
    const p = document.getElementById('progLine'); if(p) p.remove();
    addMsg('✅ ' + esc(ev.data.message), 'ai done-box');
    document.getElementById('btnApply').disabled = false;
    document.getElementById('userMsg').disabled = false;
    document.getElementById('btnSend').disabled = false;
    generating = false;
    if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
  }
  else if(ev.type==='error'){ finalizeThink();
    const p = document.getElementById('progLine'); if(p) p.remove();
    addMsg('❌ ' + esc(ev.data), 'err');
    generating = false;
    document.getElementById('userMsg').disabled = false;
    document.getElementById('btnSend').disabled = false;
    if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
  }
}

function poll(){
  if(pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(async () => {
    try{
      const r = await fetch('/api/ai_studio_events.php?session_id='+sessionId+'&since='+since);
      const d = await r.json();
      if(d.ok === false){
        // 会话不存在/失效：停止轮询，提示并允许重新开始
        if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
        addMsg('⚠️ ' + (d.message || '会话已失效，请重新开始'), 'err');
        document.getElementById('cfgBox').style.display = '';
        document.getElementById('userMsg').disabled = true;
        document.getElementById('btnSend').disabled = true;
        document.getElementById('btnStart').disabled = false;
        return;
      }
      if(d.events){ for(const ev of d.events){ since = Math.max(since, ev.seq+1); renderEvent(ev); } }
      if(d.done && pollTimer){ clearInterval(pollTimer); pollTimer=null; }
    }catch(e){}
  }, 1500);
}

async function startSession(){
  const btn = document.getElementById('btnStart');
  btn.disabled = true; document.getElementById('cfgMsg').textContent = '创建会话中...';
  const fd = new FormData();
  fd.append('problem_id', document.getElementById('cPid').value.trim() || pid);
  fd.append('api_key', document.getElementById('cKey').value.trim());
  fd.append('count', document.getElementById('cCount').value);
  fd.append('need_checker', document.getElementById('cCk').checked ? '1':'0');
  fd.append('checker_req', document.getElementById('cCkReq').value.trim());
  fd.append('extra_req', document.getElementById('cExtra').value.trim());
  const stdVal = document.getElementById('cStd').value;
  if(!stdLooksLikeCode(stdVal)){
    document.getElementById('cfgMsg').textContent = '⚠️ std 框内容看起来不是代码（只允许 C/C++/Python 代码或留空），请检查';
    btn.disabled = false;
    return;
  }
  fd.append('std_code', stdVal);
  fd.append('std_lang', document.getElementById('cLang').value);
  fd.append('save_key', document.getElementById('cSaveKey').checked ? '1':'0');
  for(let attempt=1; attempt<=3; attempt++){
    try{
      const ac = new AbortController();
      const tm = setTimeout(()=>ac.abort(), 25000);
      const r = await fetch('/api/ai_studio_start.php', {method:'POST', body:fd, signal:ac.signal});
      clearTimeout(tm);
      const d = await r.json();
      if(!d.ok){ document.getElementById('cfgMsg').textContent = d.message; btn.disabled=false; return; }
      sessionId = d.session_id; since = 0; generating = true;
      curCfg = {count: document.getElementById('cCount').value, need_checker: document.getElementById('cCk').checked,
                checker_req: document.getElementById('cCkReq').value, extra_req: document.getElementById('cExtra').value,
                std_code: document.getElementById('cStd').value, std_lang: document.getElementById('cLang').value};
      document.getElementById('cfgBox').style.display = 'none';
      document.getElementById('btnParams').style.display = '';
      history.replaceState(null, '', '/chat/' + encodeURIComponent(document.getElementById('cPid').value.trim() || pid) + '/' + sessionId);
      document.getElementById('userMsg').disabled = false;
      document.getElementById('btnSend').disabled = false;
      document.getElementById('cfgMsg').textContent = '';
      poll();
      return;
    }catch(e){
      if(attempt < 3){
        document.getElementById('cfgMsg').textContent = '网络请求失败，自动重试 ('+attempt+'/3)...';
        await new Promise(res=>setTimeout(res, 1500));
        continue;
      }
      document.getElementById('cfgMsg').textContent = (e.name==='AbortError' ? '请求超时，已自动重试 3 次仍失败，请稍后再试' : '启动失败');
      btn.disabled=false;
    }
  }
}

async function sendMsg(){
  const inp = document.getElementById('userMsg');
  const msg = inp.value.trim(); if(!msg || generating) return;
  inp.value = '';
  addMsg(esc(msg), 'user');
  generating = true;
  document.getElementById('btnSend').disabled = true;
  document.getElementById('userMsg').disabled = true;
  document.getElementById('btnApply').disabled = true;
  const fd = new FormData();
  fd.append('session_id', sessionId); fd.append('user_msg', msg);
  try{
    const r = await fetch('/api/ai_studio_message.php', {method:'POST', body:fd});
    const d = await r.json();
    if(!d.ok){
      addMsg('❌ ' + (d.message||'发送失败'), 'err');
      generating=false; document.getElementById('btnSend').disabled=false; document.getElementById('userMsg').disabled=false;
      if(d.message && d.message.indexOf('过期') !== -1){
        document.getElementById('cfgBox').style.display = '';
        document.getElementById('btnStart').disabled = false;
      }
      return;
    }
    // 保持 since 增量轮询（datamaker 本轮事件从 round_start 继续追加，done 只按本轮判定）
    poll();
  }catch(e){ addMsg('❌ 发送失败', 'err'); generating=false; document.getElementById('btnSend').disabled=false; document.getElementById('userMsg').disabled=false; }
}

function openParams(){
  document.getElementById('cfgBox').style.display = '';
  document.getElementById('btnStart').style.display = 'none';
  document.getElementById('btnSave').style.display = '';
  document.querySelector('.config-box h3').textContent = '⚙️ 调整参数（保存后重新生成，不影响已生成数据）';
  document.getElementById('cCount').value = curCfg.count;
  document.getElementById('cCk').checked = curCfg.need_checker;
  document.getElementById('cCkReq').value = curCfg.checker_req;
  document.getElementById('cExtra').value = curCfg.extra_req;
  document.getElementById('cStd').value = curCfg.std_code;
  document.getElementById('cLang').value = curCfg.std_lang;
  document.getElementById('cCkReq').disabled = !curCfg.need_checker;
}
async function saveParams(){
  const btn = document.getElementById('btnSave');
  btn.disabled = true; document.getElementById('cfgMsg').textContent = '保存并重新生成中...';
  const fd = new FormData();
  fd.append('session_id', sessionId);
  fd.append('count', document.getElementById('cCount').value);
  fd.append('need_checker', document.getElementById('cCk').checked ? '1':'0');
  fd.append('checker_req', document.getElementById('cCkReq').value);
  fd.append('extra_req', document.getElementById('cExtra').value);
  fd.append('std_code', document.getElementById('cStd').value);
  fd.append('std_lang', document.getElementById('cLang').value);
  try{
    const r = await fetch('/api/ai_studio_update.php', {method:'POST', body:fd});
    const d = await r.json();
    if(!d.ok){ document.getElementById('cfgMsg').textContent = d.message; btn.disabled=false; return; }
    curCfg = {count: fd.get('count'), need_checker: fd.get('need_checker')==='1', checker_req: fd.get('checker_req'),
              extra_req: fd.get('extra_req'), std_code: fd.get('std_code'), std_lang: fd.get('std_lang')};
    document.getElementById('cfgMsg').textContent = '';
    btn.disabled = false;
    document.getElementById('cfgBox').style.display = 'none';
    document.getElementById('btnStart').style.display = '';
    document.getElementById('btnSave').style.display = 'none';
    document.querySelector('.config-box h3').textContent = '会话配置（开始后不可改，可多轮对话修改数据）';
    poll();
  }catch(e){ document.getElementById('cfgMsg').textContent = '保存失败'; btn.disabled=false; }
}
async function applyData(){
  const fd = new FormData();
  fd.append('problem_id', document.getElementById('cPid').value.trim() || pid);
  try{
    const r = await fetch('/api/ai_studio_apply.php', {method:'POST', body:fd});
    const d = await r.json();
    addMsg(d.ok ? '💾 ' + d.message : '❌ ' + (d.message||'应用失败'), d.ok ? 'ai done-box' : 'err');
  }catch(e){ addMsg('❌ 应用失败', 'err'); }
}

document.getElementById('userMsg').addEventListener('keydown', e => { if(e.key==='Enter') sendMsg(); });
// std 语言自动识别：填 C/C++ 代码自动切 C++，Python 代码自动切 Python3
document.getElementById('cStd').addEventListener('input', function(){
  const v = this.value.trim();
  const lang = document.getElementById('cLang');
  if(!v || lang.dataset.auto) return;
  if(/^\s*#\s*include/.test(v) || /\bint\s+main\s*\(/.test(v)) lang.value = 'cpp17';
  else if(/^\s*(def |import |from |print\(|n=input)/.test(v)) lang.value = 'python3';
});
document.getElementById('cLang').addEventListener('change', function(){ delete this.dataset.auto; });
// 提交前校验 std 是否像代码
function stdLooksLikeCode(v){
  if(!v.trim()) return true;                       // 空 = AI 生成，允许
  return /#\s*include|int\s+main|def\s+|import\s+|from\s+|using\s+namespace|return\s+0|std::|cin>>|cout<<|printf\(|scanf\(/.test(v);
}
// 通过 /chat/pid/sid 进入：恢复会话
if(urlSid){
  document.getElementById('cfgBox').style.display = 'none';
  document.getElementById('userMsg').disabled = false;
  document.getElementById('btnSend').disabled = false;
  poll();
  addMsg('🔗 已恢复会话 ' + urlSid.slice(0,8) + '...，可继续对话修改', 'sys');
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
