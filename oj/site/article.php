<?php
require __DIR__.'/inc/config.php';
require __DIR__.'/inc/auth.php';
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
.article-body p{margin:8px 0}.article-body ul,.article-body ol{margin:8px 0 8px 22px}
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
    <?= $art['is_announcement'] ? '<span style="color:#ffab00;font-size:12px">📢公告</span> ' : '' ?><?=htmlspecialchars($art['title'])?>
    <span style="font-size:11px;color:<?=$art['is_public']?'#0c0':'#c90'?>;margin-left:8px"><?=$art['is_public']?'公开':'私密'?></span>
  </h1>
  <div style="display:flex;gap:8px">
    <?php if ($canEdit): ?><a class="btn btn-sm" href="article_edit.php?id=<?=$id?>">编辑</a><?php endif; ?>
    <?php if ($isAdmin = isAdmin() || $art['author']===$me['username']): ?>
    <button class="btn btn-sm btn-danger" onclick="delArticle()">删除</button>
    <?php endif; ?>
  </div>
</div>
<div style="font-size:11px;color:#666;margin-bottom:16px"><?=$art['author']?> · <?=date('Y-m-d H:i', strtotime($art['created_at']))?> · 更新 <?=date('Y-m-d H:i', strtotime($art['updated_at']))?></div>

<div class="article-body md"><?=htmlspecialchars($art['content'])?></div>

<script>
document.querySelectorAll('.article-body.md').forEach(el=>{
  el.innerHTML = marked.parse(el.textContent);
  if (typeof renderMathInElement === 'function') {
    renderMathInElement(el, {delimiters:[{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],throwOnError:false});
  }
});
if (window.highlightCodeBlocks) highlightCodeBlocks(document.querySelector('.article-body'));
async function delArticle(){
  if(!confirm('确定删除这篇文章？')) return;
  const fd=new FormData(); fd.append('id',<?=$id?>);
  const r=await fetch('api/article_delete.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok) location.href='articles.php';
  else alert(d.message||'删除失败');
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
