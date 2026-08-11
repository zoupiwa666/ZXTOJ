<?php
require __DIR__.'/inc/config.php';
require __DIR__.'/inc/auth.php';
requireRole('admin');
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['pid'] ?? '');
$sid = preg_replace('/[^a-f0-9]/', '', $_GET['sid'] ?? '');
$prob = null;
if ($pid !== '') {
    $s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?"); $s->execute([$pid]);
    $prob = $s->fetch();
}
$pageTitle = 'AI 助手 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
?>
<style>
.studio-wrap{max-width:960px;margin:0 auto}
.studio-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap}
.studio-head h1{font-size:16px;color:#fff;font-weight:400;letter-spacing:1px;margin:0}
.studio-head .pid-tag{color:#5af;font-size:12px;border:1px solid #2a4a6c;padding:2px 10px}
.config-box{background:#1e1e1e;border:1px solid #2a2a2a;padding:14px 16px;margin-bottom:14px}
.config-box h3{font-size:12px;color:#999;font-weight:400;margin:0 0 10px;letter-spacing:1px}
.config-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.config-row input{background:#000;border:1px solid #333;color:#ddd;padding:7px 10px;font-size:12px;font-family:inherit;outline:none}
.config-row input:focus{border-color:#5af}
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
.tool-call{border:1px solid #2a3a4a;border-radius:6px;margin:6px 0;background:#0f1520;overflow:hidden}
.tool-call summary{cursor:pointer;padding:6px 12px;font-size:12px;color:#8af;letter-spacing:.5px;list-style:none}
.tool-call summary:hover{background:#16202e}
.tool-call .tool-body{padding:8px 12px;border-top:1px solid #222}
.tool-call .tb-title{font-size:10px;color:#666;letter-spacing:1px;margin:6px 0 3px}
.tool-call pre{margin:0;background:#0a0e14;border:1px solid #1c2733;padding:8px;font-size:11px;line-height:1.5;overflow-x:auto;color:#bdb;white-space:pre-wrap;word-break:break-all}
.tool-call .tb-ok{color:#2ecc71}.tool-call .tb-err{color:#ff6b6b}.tool-call .tb-run{color:#ffab00;font-size:11px}
.input-bar{display:flex;gap:8px;margin-top:12px}
.input-bar input{flex:1;background:#000;border:1px solid #333;color:#ddd;padding:10px 14px;font-size:13px;outline:none}
.input-bar input:focus{border-color:#5af}
.input-bar input:disabled{opacity:.4}
.done-box{color:#2ecc71;font-size:13px}
</style>
<div class="studio-wrap">
  <div class="studio-head">
    <h1>🤖 AI 助手 <?php if($pid): ?><span class="pid-tag"><?=htmlspecialchars($pid)?></span><?php endif; ?></h1>
    <a href="edit.php?id=<?=urlencode($pid)?>" style="font-size:12px;color:#999;text-decoration:none">← 返回题目编辑</a>
  </div>

  <div class="config-box" id="cfgBox">
    <h3>🤖 AI 助手（聊天式：直接说话让我生成/修改测试数据，也可随意提问）</h3>
    <div class="config-row">
      <input id="cKey" type="password" placeholder="DeepSeek API Key（已保存可留空）" style="flex:1;min-width:220px">
      <button class="btn btn-blue" onclick="startSession()" id="btnStart">🚀 开始对话</button>
      <span id="cfgMsg" style="font-size:12px;color:#999"></span>
    </div>
    <div class="config-row" style="margin-top:8px">
      <textarea id="cStd" rows="2" placeholder="题目正解 std（可选，供 AI 参考生成标准输出；留空则 AI 根据题面自己写）" style="flex:1;background:#000;border:1px solid #333;color:#ddd;padding:7px 10px;font-size:12px;font-family:inherit;outline:none"></textarea>
      <select id="cLang" style="width:130px;background:#000;border:1px solid #333;color:#ddd;padding:7px;font-size:12px">
        <option value="python3">Python3</option><option value="cpp17">C++17</option>
      </select>
    </div>
  </div>

  <div class="chat-box" id="chatBox"></div>

  <div class="input-bar">
    <input id="userMsg" placeholder="和 AI 聊天，或说：帮我生成 5 组测试数据 / 加个忽略行末空格的 checker / 第2组改成大边界..." disabled>
    <button class="btn btn-blue" onclick="sendMsg()" id="btnSend" disabled>发送</button>
    <button class="btn btn-green" onclick="applyData()" id="btnApply" disabled>✓ 应用数据</button>
  </div>
</div>

<script>
const pid = <?=json_encode($pid)?>;
const urlSid = <?=json_encode($sid)?>;
let sessionId = urlSid || null, since = 0, pollTimer = null, generating = false;
let pendingTool = null, pendingReply = null, replyRaf = false;

function addMsg(html, cls){ const d=document.createElement('div'); d.className='msg '+cls; d.innerHTML=html; document.getElementById('chatBox').appendChild(d); autoScroll(); return d; }
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function autoScroll(){
  const box = document.getElementById('chatBox');
  if(box.scrollHeight - box.scrollTop - box.clientHeight < 100) box.scrollTop = box.scrollHeight;
}
function finalizeReply(){
  if(pendingReply){
    if(pendingReply.buf.trim() === ''){ pendingReply.el.remove(); }
    else { pendingReply.el.classList.add('ai'); }
    if(pendingReply._raf){ cancelAnimationFrame(pendingReply._raf); pendingReply._raf = null; }
    pendingReply = null; replyRaf = false;
  }
}

function renderEvent(ev){
  const box = document.getElementById('chatBox');
  if(ev.type==='info'){ addMsg('🛠 ' + esc(ev.data), 'sys'); }
  else if(ev.type==='user'){ finalizeReply(); addMsg(esc(ev.data), 'user'); }
  else if(ev.type==='reply_delta'){
    // AI 回复流式打字机
    if(!pendingReply){
      const el = addMsg('', 'ai');
      pendingReply = {el, buf:''};
    }
    pendingReply.buf += ev.data;
    if(!pendingReply._raf){
      pendingReply._raf = requestAnimationFrame(()=>{
        if(pendingReply){ pendingReply.el.textContent = pendingReply.buf; pendingReply._raf = null; autoScroll(); }
      });
    }
  }
  else if(ev.type==='reply'){
    finalizeReply();
    if(ev.data){ addMsg(ev.data, 'ai'); }
  }
  else if(ev.type==='tool_delta'){
    finalizeReply();
    const d = ev.data || {};
    if(!pendingTool || (d.name && pendingTool.name !== d.name)){
      if(pendingTool && !pendingTool.finalized){ pendingTool.sum.innerHTML = '🛠 ' + pendingTool.name + ' <span class="tb-run">⏳ 调用中...</span>'; }
      const det = document.createElement('details');
      det.className = 'tool-call'; det.open = true;
      const sum = document.createElement('summary');
      sum.innerHTML = '🛠 ' + (d.name||'tool') + ' <span class="tb-run">⏳ 调用中...</span>';
      det.appendChild(sum);
      const body = document.createElement('div'); body.className='tool-body';
      const a1 = document.createElement('div'); a1.className='tb-title'; a1.textContent='参数'; body.appendChild(a1);
      const pre1 = document.createElement('pre'); pre1.textContent=''; body.appendChild(pre1);
      const a2 = document.createElement('div'); a2.className='tb-title'; a2.textContent='结果'; body.appendChild(a2);
      const pre2 = document.createElement('pre'); pre2.textContent=''; body.appendChild(pre2);
      det.appendChild(body);
      const wrap = document.createElement('div'); wrap.className='msg ai'; wrap.style.padding='4px 6px';
      wrap.appendChild(det);
      document.getElementById('chatBox').appendChild(wrap);
      pendingTool = {name: d.name||'tool', argsText:'', det, sum, pre1, pre2, finalized:false};
    }
    if(d.args_delta){ pendingTool.argsText += d.args_delta; pendingTool.pre1.textContent = pendingTool.argsText; autoScroll(); }
  }
  else if(ev.type==='tool'){
    finalizeReply();
    const t = ev.data || {};
    const st = t.status === 'err' ? '<span class="tb-err">⚠️ 失败</span>' : '<span class="tb-ok">✓</span>';
    if(pendingTool && !pendingTool.finalized && pendingTool.name === t.name){
      pendingTool.sum.innerHTML = '🛠 ' + t.name + ' ' + st;
      pendingTool.pre1.textContent = JSON.stringify(t.args||{}, null, 1);
      pendingTool.pre2.textContent = t.result || '';
      pendingTool.finalized = true;
    } else {
      const det = document.createElement('details');
      det.className = 'tool-call';
      const sum = document.createElement('summary'); sum.innerHTML = '🛠 ' + (t.name||'tool') + ' ' + st;
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
  else if(ev.type==='done'){
    finalizeReply();
    document.getElementById('btnApply').disabled = false;
    document.getElementById('userMsg').disabled = false;
    document.getElementById('btnSend').disabled = false;
    generating = false;
    if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
  }
  else if(ev.type==='error'){
    finalizeReply();
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
        if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
        addMsg('⚠️ ' + (d.message || '会话已失效，请重新开始'), 'err');
        document.getElementById('cfgBox').style.display = '';
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
  fd.append('problem_id', document.getElementById('cPid') ? document.getElementById('cPid').value.trim() : pid);
  fd.append('api_key', document.getElementById('cKey').value.trim());
  fd.append('std_code', document.getElementById('cStd').value);
  fd.append('std_lang', document.getElementById('cLang').value);
  for(let attempt=1; attempt<=3; attempt++){
    try{
      const ac = new AbortController();
      const tm = setTimeout(()=>ac.abort(), 25000);
      const r = await fetch('/api/ai_studio_start.php', {method:'POST', body:fd, signal:ac.signal});
      clearTimeout(tm);
      const d = await r.json();
      if(!d.ok){ document.getElementById('cfgMsg').textContent = d.message; btn.disabled=false; return; }
      sessionId = d.session_id; since = 0; generating = false;
      document.getElementById('cfgBox').style.display = 'none';
      document.getElementById('userMsg').disabled = false;
      document.getElementById('btnSend').disabled = false;
      document.getElementById('cfgMsg').textContent = '';
      if(pid) history.replaceState(null, '', '/chat/' + encodeURIComponent(pid) + '/' + sessionId);
      poll();
      return;
    }catch(e){
      if(attempt < 3){
        document.getElementById('cfgMsg').textContent = '网络请求失败，自动重试 ('+attempt+'/3)...';
        await new Promise(res=>setTimeout(res, 1500));
        continue;
      }
      document.getElementById('cfgMsg').textContent = (e.name==='AbortError' ? '请求超时，已重试 3 次仍失败' : '启动失败');
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
  const fd = new FormData();
  fd.append('session_id', sessionId); fd.append('user_msg', msg);
  try{
    const r = await fetch('/api/ai_studio_message.php', {method:'POST', body:fd});
    const d = await r.json();
    if(!d.ok){
      addMsg('❌ ' + (d.message||'发送失败'), 'err');
      generating=false; document.getElementById('btnSend').disabled=false; document.getElementById('userMsg').disabled=false;
      if(d.message && d.message.indexOf('过期') !== -1){ document.getElementById('cfgBox').style.display=''; document.getElementById('btnStart').disabled=false; }
      return;
    }
    poll();
  }catch(e){ addMsg('❌ 发送失败', 'err'); generating=false; document.getElementById('btnSend').disabled=false; document.getElementById('userMsg').disabled=false; }
}

async function applyData(){
  const fd = new FormData();
  fd.append('problem_id', pid || sessionId);
  try{
    const r = await fetch('/api/ai_studio_apply.php', {method:'POST', body:fd});
    const d = await r.json();
    addMsg(d.ok ? '💾 ' + d.message : '❌ ' + (d.message||'应用失败'), d.ok ? 'ai done-box' : 'err');
  }catch(e){ addMsg('❌ 应用失败', 'err'); }
}

document.getElementById('userMsg').addEventListener('keydown', e => { if(e.key==='Enter') sendMsg(); });

// 通过 /chat/pid/sid 进入：恢复会话（一次性加载历史）
if(urlSid){
  document.getElementById('cfgBox').style.display = 'none';
  document.getElementById('userMsg').disabled = false;
  document.getElementById('btnSend').disabled = false;
  loadHistory();
  addMsg('🔗 已恢复会话，可继续对话', 'sys');
}
async function loadHistory(){
  try{
    const r = await fetch('/api/ai_studio_history.php?session_id='+sessionId);
    const d = await r.json();
    if(d.ok === false){ addMsg('⚠️ ' + (d.message||'会话已失效'), 'err'); return; }
    if(d.events){
      renderHistory(d.events);
      since = d.next_since || 0;
    }
    poll();
  }catch(e){ poll(); }
}
function renderHistory(evs){
  const box = document.getElementById('chatBox');
  const frag = document.createDocumentFragment();
  let i = 0;
  const mk = (html, cls) => { const d=document.createElement('div'); d.className='msg '+cls; d.innerHTML=html; frag.appendChild(d); };
  while(i < evs.length){
    const e = evs[i];
    if(e.type === 'reply_delta'){
      let buf = e.data || ''; let j = i+1;
      while(j < evs.length && evs[j].type === 'reply_delta'){ buf += evs[j].data || ''; j++; }
      mk(esc(buf), 'ai'); i = j;
    } else if(e.type === 'reply'){ mk(esc(e.data||''), 'ai'); i++; }
    else if(e.type === 'user'){ mk(esc(e.data), 'user'); i++; }
    else if(e.type === 'info'){ mk('🛠 ' + esc(e.data), 'sys'); i++; }
    else if(e.type === 'tool_delta'){ i++; }
    else if(e.type === 'tool'){
      const t=e.data||{};
      mk('<details class="tool-call"><summary>🛠 ' + esc(t.name||'tool') + (t.status==='err'?' ⚠️':' ✓') + '</summary><div class="tool-body"><div class="tb-title">参数</div><pre>' + esc(JSON.stringify(t.args||{},null,1)) + '</pre><div class="tb-title">结果</div><pre>' + esc(t.result||'') + '</pre></div></details>', 'ai');
      i++;
    }
    else { i++; }
  }
  box.appendChild(frag);
  box.scrollTop = box.scrollHeight;
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
