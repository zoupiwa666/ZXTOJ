<?php
$pageTitle = '聊天 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
requireLogin();
require_once __DIR__.'/inc/chat_tables.php';
$me = currentUser();
?>
<style>
.chat-wrap{display:flex;gap:0;height:560px;border:1px solid #222;background:#141414}
.chat-side{width:260px;border-right:1px solid #222;display:flex;flex-direction:column;background:#181818}
.chat-side .search-box{padding:10px;border-bottom:1px solid #222}
.chat-side .search-res{max-height:140px;overflow:auto;border-bottom:1px solid #222;display:none}
.chat-side .search-res .sr{display:flex;justify-content:space-between;align-items:center;padding:6px 10px;font-size:12px;color:#ccc;border-bottom:1px solid #1c1c1c}
.chat-side .search-res .sr:hover{background:#222}
.chat-friends{flex:1;overflow-y:auto}
.chat-friend{padding:10px 12px;border-bottom:1px solid #1c1c1c;cursor:pointer;display:flex;flex-direction:column;gap:3px}
.chat-friend:hover,.chat-friend.active{background:#222}
.chat-friend .cf-name{color:#fff;font-size:13px}
.chat-friend .cf-prev{color:#777;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px}
.chat-main{flex:1;display:flex;flex-direction:column;min-width:0;min-height:0}
.chat-head{padding:12px 16px;border-bottom:1px solid #222;color:#fff;font-size:14px;background:#1a1a1a}
.chat-msgs{flex:1;overflow-y:auto;overflow-x:hidden;padding:16px;display:flex;flex-direction:column;gap:10px;min-height:0}
.chat-msgs::-webkit-scrollbar{width:8px}
.chat-msgs::-webkit-scrollbar-thumb{background:#333;border-radius:4px}
.chat-msgs::-webkit-scrollbar-thumb:hover{background:#444}
.chat-msgs::-webkit-scrollbar-track{background:transparent}
.cmsg{max-width:70%;padding:8px 12px;border-radius:8px;font-size:13px;line-height:1.5}
.cmsg .cm-body{display:block;white-space:pre-wrap;word-break:break-word}
.cmsg .cm-body.cm-long{max-height:72px;overflow:hidden}
.cmsg .cm-expand{display:block;margin-top:6px;background:none;border:none;color:#5af;font-size:11px;cursor:pointer;padding:0;font-family:inherit}
.cmsg .cm-expand:hover{text-decoration:underline}
.cmsg .cm-t{display:block;font-size:10px;color:#777;margin-top:4px}
.cmsg .cm-body h1,.cmsg .cm-body h2,.cmsg .cm-body h3{font-size:14px;margin:6px 0 4px}
.cmsg .cm-body p{margin:4px 0}
.cmsg .cm-body ul,.cmsg .cm-body ol{margin:4px 0 4px 18px}
.cmsg .cm-body code{background:#333;padding:1px 4px;border-radius:3px;font-size:12px}
.cmsg .cm-body pre{background:#111;padding:8px;border-radius:4px;overflow-x:auto;margin:6px 0}
.cmsg .cm-body pre code{background:none;padding:0}
.cmsg .cm-body a{color:#5af}
.cmsg .cm-body img{max-width:100%;border-radius:4px}
.cmsg .cm-body blockquote{border-left:3px solid #444;padding-left:8px;color:#999;margin:4px 0}
.cmsg.mine{align-self:flex-end;background:#1a3a5c;color:#ddd}
.cmsg.theirs{align-self:flex-start;background:#2a2a2a;color:#ddd}
.chat-input{display:flex;gap:8px;padding:12px;border-top:1px solid #222;background:#181818}
.chat-input textarea{flex:1;height:64px;resize:none}
.chat-empty{display:flex;align-items:center;justify-content:center;flex:1;color:#555;font-size:13px}
.hint{font-size:11px;color:#666;padding:8px 12px;border-bottom:1px solid #1c1c1c}
</style>

<div class="chat-wrap">
  <!-- 左侧：搜索 + 好友列表 -->
  <div class="chat-side">
    <div class="search-box">
      <input id="searchKw" placeholder="搜索用户名..." onkeydown="if(event.key==='Enter')searchUsers()">
      <button class="btn btn-sm" style="width:100%;margin-top:6px" onclick="searchUsers()">搜索</button>
    </div>
    <div class="search-res" id="searchRes"></div>
    <div class="hint">我的好友</div>
    <div class="chat-friends" id="friendList"></div>
  </div>

  <!-- 右侧：聊天窗口 -->
  <div class="chat-main">
    <div class="chat-head" id="chatHead">选择左侧好友开始聊天</div>
    <div class="chat-msgs" id="chatMsgs"><div class="chat-empty">👈 选择左侧好友开始聊天</div></div>
    <div class="chat-input" id="chatInputBox" style="display:none">
      <textarea id="msgInput" placeholder="输入消息（最多3.5KB），Enter发送，Shift+Enter换行" onkeydown="onInputKey(event)"></textarea>
      <button class="btn" style="align-self:stretch" onclick="sendMsg()">发送</button>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/contrib/auto-render.min.js"></script>
<script src="assets/marked.min.js"></script>
<script>
let currentFriend = null;
let msgTimer = null;
let expandedIds = new Set(); // 已展开的消息id（轮询刷新不丢失）
let renderedIds = [];       // 已渲染的消息id（只追加，不整表重建）
let lastFriendList = '';

function avatarHtml(u, size){
  size = size || 20;
  if(u && u.avatar) return '<img class="uavatar" src="'+u.avatar+'" width="'+size+'" height="'+size+'">';
  const ch = (u && u.username ? u.username[0] : '?').toUpperCase();
  return '<span class="uavatar uavatar-char" style="width:'+size+'px;height:'+size+'px;line-height:'+size+'px;font-size:'+Math.max(9,Math.round(size*0.5))+'px">'+ch+'</span>';
}

function fmtTime(t){
  if(!t) return '';
  const d = new Date(t.replace(' ','T') + (t.includes('Z')?'':'Z'));
  if(isNaN(d)) return t;
  const now = new Date();
  const sameDay = d.toDateString() === now.toDateString();
  const hh = String(d.getHours()).padStart(2,'0'), mm = String(d.getMinutes()).padStart(2,'0');
  return (sameDay?'':(d.getMonth()+1)+'/'+d.getDate()+' ') + hh+':'+mm;
}

async function api(url, data){
  const opt = {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}};
  if(data) opt.body = new URLSearchParams(data);
  const r = await fetch(url, opt);
  return r.json();
}

async function searchUsers(){
  const kw = document.getElementById('searchKw').value.trim();
  if(!kw) return;
  const d = await api('api/chat_search.php', {kw});
  const box = document.getElementById('searchRes');
  box.style.display = 'block';
  if(!d.users || d.users.length===0){ box.innerHTML = '<div class="sr" style="color:#777">未找到用户</div>'; return; }
  box.innerHTML = d.users.map(u =>
    `<div class="sr"><span style="display:inline-flex;align-items:center;gap:5px">${avatarHtml(u,18)}${u.username} <span style="color:#666">(${u.role})</span></span>` +
    (u.is_friend ? '<span style="color:#0c0;font-size:11px">✓好友</span>'
                : `<button class="btn btn-sm" onclick="addFriend(${u.id}, this)">添加</button></div>`)
  ).join('');
}

async function addFriend(id, btn){
  btn.disabled = true;
  const d = await api('api/chat_add.php', {friend_id:id});
  if(d.ok){ btn.innerHTML = '✓已添加'; loadFriends(); }
  else { btn.disabled = false; btn.innerHTML = d.message||'失败'; }
}

async function loadFriends(){
  const d = await api('api/chat_friends.php', {});
  const list = document.getElementById('friendList');
  const html = (d.friends||[]).map(f => {
    const prev = f.last_msg ? (f.last_msg.length>30 ? f.last_msg.slice(0,30)+'…' : f.last_msg) : '暂无消息';
    return `<div class="chat-friend${currentFriend==f.id?' active':''}" onclick="openChat(${f.id},'${f.username}','${f.avatar||''}')">
      <span style="display:flex;align-items:center;gap:6px">${avatarHtml(f,20)}<span class="cf-name">${f.username}</span></span>
      <span class="cf-prev">${prev}</span></div>`;
  }).join('') || '<div style="padding:16px;color:#555;font-size:12px">还没有好友，搜索用户名添加吧</div>';
  if(html !== lastFriendList){ list.innerHTML = html; lastFriendList = html; }
}

async function openChat(fid, name, avatar){
  currentFriend = fid;
  document.getElementById('chatHead').innerHTML = '<span style="display:inline-flex;align-items:center;gap:8px">' + avatarHtml({username:name, avatar:avatar}, 22) + '与 ' + name + ' 聊天中</span>';
  document.getElementById('chatInputBox').style.display = 'flex';
  loadFriends();
  await loadMessages();
  clearInterval(msgTimer);
  msgTimer = setInterval(loadMessages, 3000);
  document.getElementById('msgInput').focus();
}

async function loadMessages(){
  if(currentFriend === null) return;
  const d = await api('api/chat_messages.php?friend_id='+currentFriend, {});
  const box = document.getElementById('chatMsgs');
  const me = <?= $me['id'] ?>;
  const msgs = d.messages||[];

  // 无消息：显示占位
  if(msgs.length===0){
    if(box.querySelector('.cmsg')){ box.innerHTML='<div class="chat-empty">还没有消息，说点什么吧</div>'; renderedIds=[]; }
    return;
  }
  // 消息被裁剪（最早的已渲染消息被清掉）→ 重建一次
  if(renderedIds.length && msgs[0].id !== renderedIds[0]){
    box.innerHTML='';
    renderedIds=[];
  }
  // 只追加新消息（不重建整个列表，避免代码块/公式闪跳）
  const known = new Set(renderedIds);
  const fresh = msgs.filter(m=>!known.has(m.id));
  if(!fresh.length) return;

  const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
  const emptyEl = box.querySelector('.chat-empty');
  if(emptyEl) emptyEl.remove();

  fresh.forEach(m => {
    const mine = m.sender_id == me;
    const isLong = (m.content||'').length > 100;
    const expanded = expandedIds.has(m.id);
    let body;
    if(isLong && !expanded){
      body = `<span class="cm-body cm-long md">${escapeHtml(m.content)}</span><button class="cm-expand" onclick="expandMsg(${m.id}, this)">展开全部</button>`;
    } else if(isLong && expanded){
      body = `<span class="cm-body md">${escapeHtml(m.content)}</span><button class="cm-expand" onclick="collapseMsg(${m.id}, this)">收起</button>`;
    } else {
      body = `<span class="cm-body md">${escapeHtml(m.content)}</span>`;
    }
    const div = document.createElement('div');
    div.className = 'cmsg '+(mine?'mine':'theirs');
    div.innerHTML = body + `<span class="cm-t">${fmtTime(m.created_at)}</span>`;
    box.appendChild(div);
    renderedIds.push(m.id);
  });

  // 只渲染新消息的 Markdown + KaTeX + 代码高亮
  box.querySelectorAll('.cm-body.md').forEach(el => {
    if(el.dataset.rendered) return;
    el.dataset.rendered = '1';
    el.innerHTML = marked.parse(el.textContent);
    if (typeof renderMathInElement === 'function') {
      renderMathInElement(el, {delimiters:[{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],throwOnError:false});
    }
  });
  if (window.highlightCodeBlocks) highlightCodeBlocks(box);

  // 仅当用户接近底部时自动滚动，否则保留阅读位置
  if(nearBottom) box.scrollTop = box.scrollHeight;
}

function expandMsg(id, btn){
  expandedIds.add(id);
  btn.parentElement.querySelector('.cm-body').classList.remove('cm-long');
  btn.textContent = '收起';
  btn.setAttribute('onclick', `collapseMsg(${id}, this)`);
}

function collapseMsg(id, btn){
  expandedIds.delete(id);
  btn.parentElement.querySelector('.cm-body').classList.add('cm-long');
  btn.textContent = '展开全部';
  btn.setAttribute('onclick', `expandMsg(${id}, this)`);
}

function escapeHtml(s){
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function onInputKey(e){
  if(e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); sendMsg(); }
}

async function sendMsg(){
  if(currentFriend === null) return;
  const inp = document.getElementById('msgInput');
  const content = inp.value.trim();
  if(!content) return;
  const d = await api('api/chat_send.php', {friend_id:currentFriend, content});
  if(d.ok){ inp.value=''; await loadMessages(); loadFriends(); }
  else alert(d.message || '发送失败');
}

loadFriends();
setInterval(loadFriends, 5000);
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
