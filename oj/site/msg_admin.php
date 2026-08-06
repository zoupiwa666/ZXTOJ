<?php
$pageTitle = '系统消息 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
requireRole('admin');
?>
<h1 class="page-title">📨 系统消息</h1>
<div style="max-width:600px">
  <div style="font-size:12px;color:#888;margin-bottom:12px">以 <b style="color:#5af">Message</b> 身份批量发送系统消息给用户（可多个用户名，逗号/空格/换行分隔）</div>
  <div style="margin-bottom:10px">
    <label>收件人（用户名，多个用逗号分隔）</label>
    <input id="msgTo" placeholder="如: admin, zxt, djx" style="width:100%;padding:10px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#ddd;font-size:13px;outline:none">
  </div>
  <div style="margin-bottom:10px">
    <label>消息内容</label>
    <textarea id="msgContent" rows="5" placeholder="输入系统消息内容（最长2000字）" style="width:100%;padding:10px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#ddd;font-size:13px;outline:none;resize:vertical"></textarea>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="sendMsg()">发送</button>
    <a class="btn btn-line" href="chat.php">返回聊天</a>
    <span id="msgResult" style="font-size:12px;color:#999"></span>
  </div>
</div>
<script>
async function sendMsg(){
  const btn=event.target; btn.disabled=true;
  const res=document.getElementById('msgResult'); res.textContent='发送中...';
  const fd=new FormData();
  fd.append('to',document.getElementById('msgTo').value);
  fd.append('content',document.getElementById('msgContent').value);
  try{
    const r=await fetch('message/api_send.php',{method:'POST',body:fd});
    const d=await r.json();
    res.textContent=d.message||(d.ok?'已发送':'失败');
  }catch(e){ res.textContent='发送失败: '+e.message; }
  btn.disabled=false;
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
