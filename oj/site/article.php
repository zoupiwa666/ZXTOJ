<?php
require __DIR__.'/inc/config.php';
require __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/sanitize.php';
require __DIR__.'/inc/article_tables.php';
requireLogin();
$me = currentUser();
$id = intval($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM articles WHERE id=?"); $s->execute([$id]);
$art = $s->fetch();
if (!$art) die('文章不存在');
// 私密文章：即使输 ID 也看不到（仅作者/管理员），返回干净的 403 页面
if (!article_can_view($art, $me['username'])) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><title>无权查看</title></head>'
       . '<body style="background:#111;color:#ddd;font-family:Consolas,monospace;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">'
       . '<div style="text-align:center"><div style="color:#f66;font-size:40px">403</div>'
       . '<div style="margin:12px 0;color:#999">无权查看此文章（私密）</div>'
       . '<a href="articles.php" style="color:#5af;text-decoration:none">← 返回文章列表</a></div></body></html>';
    exit;
}
$canEdit = article_can_edit($art, $me['username']);
// 点赞/点踩统计 + 我的投票
$st = $pdo->prepare("SELECT SUM(value=1) AS likes, SUM(value=-1) AS dislikes FROM article_likes WHERE article_id=?");
$st->execute([$id]); $vc = $st->fetch();
$likes = intval($vc['likes'] ?? 0); $dislikes = intval($vc['dislikes'] ?? 0);
$mv = $pdo->prepare("SELECT value FROM article_likes WHERE article_id=? AND username=?");
$mv->execute([$id, $me['username']]);
$myVote = intval($mv->fetchColumn() ?: 0);
$pageTitle = '文章 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/contrib/auto-render.min.js"></script>
<script src="assets/marked.min.js"></script>
<style>
.article-body{background:#141414;border:1px solid #222;padding:24px;line-height:1.8}
.article-body h1{font-size:20px;margin:16px 0 8px}.article-body h2{font-size:17px;margin:14px 0 8px}.article-body h3{font-size:15px;margin:12px 0 6px}
.article-body p{margin:8px 0}.article-body ul,.article-body ol{margin:8px 0 8px 22px;padding-left:0}
/* 嵌套列表：明显缩进 + 左侧细线 */
.article-body li > ul,.article-body li > ol{margin:6px 0 6px 24px;padding-left:12px;border-left:2px solid #333}
.cmt-body ul,.cmt-body ol{margin:4px 0 4px 18px;padding-left:0}
.cmt-body li > ul,.cmt-body li > ol{margin:4px 0 4px 22px;padding-left:10px;border-left:2px solid #333}
.article-body li{margin:2px 0}
.article-body code{background:#252525;padding:1px 5px;border-radius:3px;font-size:13px;font-family:Consolas,'Courier New',monospace}
.article-body pre{background:#0f0f0f;border:1px solid #222;padding:12px;border-radius:6px;overflow-x:auto;margin:10px 0}
.article-body pre code{background:none;padding:0}
.article-body blockquote{border-left:3px solid #444;padding-left:12px;color:#999;margin:8px 0}
.article-body a{color:#5af}.article-body img{max-width:100%}
.article-body table{border-collapse:collapse;margin:10px 0}.article-body td,.article-body th{border:1px solid #333;padding:4px 10px}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
  <h1 style="font-size:18px;color:#fff;font-weight:400">
    <?= $art['is_announcement'] ? '<span style="color:#ffab00;font-size:12px"><i class="fa-solid fa-bullhorn"></i>公告</span> ' : '' ?><?=htmlspecialchars($art['title'])?>
    <span style="font-size:11px;color:<?=$art['is_public']?'#0c0':'#c90'?>;margin-left:8px"><?=$art['is_public']?'公开':'私密'?></span>
  </h1>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if ($art['is_solution']): ?>
      <span style="font-size:11px;color:#5af;border:1px solid #2a5a8c;background:#1a3a5c;padding:3px 10px"><i class="fa-solid fa-book-open"></i> 题解 <?=htmlspecialchars($art['solution_problem'])?></span>
      <span style="font-size:11px;color:<?=$art['solution_status']==='approved'?'#0c0':($art['solution_status']==='rejected'?'#f66':'#c90')?>">
        <?=$art['solution_status']==='approved'?'已通过':($art['solution_status']==='rejected'?'已拒绝':'待审核')?>
      </span>
      <?php if (isAdmin() && $art['solution_status']==='pending'): ?>
      <button class="btn btn-sm" style="color:#0c0" onclick="reviewSol('approve')">通过</button>
      <button class="btn btn-sm btn-danger" onclick="reviewSol('reject')">拒绝</button>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($canEdit): ?><a class="btn btn-sm" href="article_edit.php?id=<?=$id?>">编辑</a><?php endif; ?>
    <?php if ($isAdmin = isAdmin() || $art['author']===$me['username']): ?>
    <button class="btn btn-sm btn-danger" onclick="delArticle()">删除</button>
    <?php endif; ?>
  </div>
</div>
<div style="font-size:11px;color:#666;margin-bottom:16px"><?= userBadge($art['author'], null, 16) ?> · <?=date('Y-m-d H:i', strtotime($art['created_at']))?> · 更新 <?=date('Y-m-d H:i', strtotime($art['updated_at']))?></div>

<div class="article-body md"><?=htmlspecialchars(render_mentions($art['content']))?></div>

<!-- 点赞/点踩 -->
<div style="display:flex;gap:12px;align-items:center;margin:16px 0;padding:12px 16px;background:#141414;border:1px solid #222;border-radius:8px">
  <button id="btnLike" onclick="vote(1)" style="padding:6px 16px;background:<?=$myVote==1?'#1a3a5c':'#222'?>;color:<?=$myVote==1?'#5af':'#ccc'?>;border:1px solid #333;border-radius:6px;cursor:pointer;font-size:13px"><i class="fa-solid fa-thumbs-up"></i> 赞 <span id="likeCnt"><?=$likes?></span></button>
  <button id="btnDislike" onclick="vote(-1)" style="padding:6px 16px;background:<?=$myVote==-1?'#3a1a1a':'#222'?>;color:<?=$myVote==-1?'#f66':'#ccc'?>;border:1px solid #333;border-radius:6px;cursor:pointer;font-size:13px"><i class="fa-solid fa-thumbs-down"></i> 踩 <span id="dislikeCnt"><?=$dislikes?></span></button>
  <span style="font-size:11px;color:#888;margin-left:auto"><?=$likes?> 赞 / <?=$dislikes?> 踩</span>
</div>

<!-- 评论区 -->
<h2 style="font-size:14px;color:#fff;font-weight:400;margin:20px 0 10px;letter-spacing:1px"><i class="fa-solid fa-comments"></i> 评论 <span id="cmtTotal" style="color:#888">0</span></h2>
<div style="margin-bottom:14px">
  <textarea id="cmtInput" rows="3" placeholder="写下你的评论（支持 Markdown，字体最大150px）..." style="width:100%;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#ddd;font-size:13px;padding:10px 12px;outline:none;resize:vertical;font-family:inherit"></textarea>
  <div style="margin-top:6px"><button class="btn btn-sm" onclick="postComment()">发表评论</button><span id="cmtMsg" style="font-size:12px;color:#999;margin-left:8px"></span></div>
</div>
<div id="cmtList"></div>
<div id="cmtPager" style="display:flex;gap:10px;align-items:center;margin-top:12px;font-size:12px"></div>

<script>
document.querySelectorAll('.article-body.md').forEach(el=>{
  el.innerHTML = marked.parse(el.textContent);
  if (typeof renderMathInElement === 'function') {
    renderMathInElement(el, {delimiters:[{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],throwOnError:false});
  }
});
if (window.highlightCodeBlocks) highlightCodeBlocks(document.querySelector('.article-body'));
let cmtPage = 1;
async function vote(val){
  const fd=new FormData(); fd.append('article_id',<?=$id?>); fd.append('value',val);
  const r=await fetch('api/article_vote.php',{method:'POST',body:fd});
  const d=await r.json();
  if(!d.ok){ ztAlert(d.message||'操作失败'); return; }
  document.getElementById('likeCnt').textContent=d.likes;
  document.getElementById('dislikeCnt').textContent=d.dislikes;
  const bl=document.getElementById('btnLike'), bd=document.getElementById('btnDislike');
  bl.style.background = val===1 ? '#1a3a5c' : '#222'; bl.style.color = val===1 ? '#5af' : '#ccc';
  bd.style.background = val===-1 ? '#3a1a1a' : '#222'; bd.style.color = val===-1 ? '#f66' : '#ccc';
}
function escapeHtml(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function renderCmt(c){
  const av = c.avatar ? '<img src="'+c.avatar+'" style="width:24px;height:24px;border-radius:50%;object-fit:cover;vertical-align:middle">' : '';
  const color = (c.role==='super_admin'||c.role==='admin') ? '#a855f7' : '#b0815a';
  const tag = c.tag ? '<span style="background:'+color+';color:#fff;font-size:9px;padding:0 5px;border-radius:3px;margin-left:5px;vertical-align:middle">'+escapeHtml(c.tag)+'</span>' : '';
  return '<div style="background:#141414;border:1px solid #222;border-radius:8px;padding:12px 14px;margin-bottom:8px">'
    +'<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">'+av
    +'<a href="user.php?name='+encodeURIComponent(c.username)+'" style="color:'+color+';text-decoration:none;font-size:12px;font-weight:600">'+escapeHtml(c.username)+'</a>'+tag
    +'<span style="color:#666;font-size:10px;margin-left:auto">'+c.created_at+'</span></div>'
    +'<div class="cmt-body" style="font-size:13px;line-height:1.7;word-break:break-word">'+escapeHtml(c.content)+'</div></div>';
}
async function loadComments(page){
  cmtPage = page||1;
  const r=await fetch('api/article_comments.php?article_id=<?=$id?>&page='+cmtPage);
  const d=await r.json();
  document.getElementById('cmtTotal').textContent = d.total||0;
  const list=document.getElementById('cmtList');
  list.innerHTML = (d.comments||[]).map(renderCmt).join('') || '<div style="text-align:center;color:#666;padding:20px;font-size:12px">暂无评论，来抢沙发</div>';
  list.querySelectorAll('.cmt-body').forEach(el=>{
    el.innerHTML = marked.parse(el.textContent);
    if (typeof renderMathInElement === 'function') renderMathInElement(el,{delimiters:[{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],throwOnError:false});
  });
  if (window.highlightCodeBlocks) highlightCodeBlocks(list);
  let p='';
  if(d.page>1) p+='<button class="btn btn-sm" onclick="loadComments('+(d.page-1)+')">上一页</button>';
  p+='<span style="color:#888">'+d.page+'/'+d.totalPages+'</span>';
  if(d.page<d.totalPages) p+='<button class="btn btn-sm" onclick="loadComments('+(d.page+1)+')">下一页</button>';
  document.getElementById('cmtPager').innerHTML=p;
}
async function postComment(){
  const inp=document.getElementById('cmtInput'), msg=document.getElementById('cmtMsg');
  const content=inp.value.trim();
  if(!content){ msg.textContent='评论不能为空'; return; }
  const fd=new FormData(); fd.append('article_id',<?=$id?>); fd.append('content',content);
  const r=await fetch('api/article_comment.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){ inp.value=''; msg.textContent='评论成功'; loadComments(1); }
  else msg.textContent = d.message||'评论失败';
}
loadComments(1);
async function reviewSol(action){
  const fd=new FormData(); fd.append('id',<?=$id?>); fd.append('action',action);
  const r=await fetch('api/article_review.php',{method:'POST',body:fd});
  const d=await r.json();
  ztAlert(d.message||'操作完成'); location.reload();
}
async function delArticle(){
  if(!confirm('确定删除这篇文章？')) return;
  const fd=new FormData(); fd.append('id',<?=$id?>);
  const r=await fetch('api/article_delete.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok) location.href='articles.php';
  else ztAlert(d.message||'删除失败');
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
