<?php
$pageTitle = '发布文章 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
require __DIR__.'/inc/article_tables.php';
requireLogin();
$me = currentUser();
$perm = article_perm($me['username']);
$isAdmin = isAdmin();
$id = intval($_GET['id'] ?? 0);
$art = null;
if ($id > 0) {
    $s = $pdo->prepare("SELECT * FROM articles WHERE id=?"); $s->execute([$id]);
    $art = $s->fetch();
    if (!$art) die('文章不存在');
    if (!article_can_edit($art, $me['username'])) die('无修改权限');
} else {
    if (!$isAdmin && $perm['can_publish'] != 1) die('你没有被授权发布文章');
}
?>
<style>
.ae-box{max-width:800px}
.ae-box input,.ae-box textarea{width:100%;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#ddd;font-size:13px;outline:none;padding:10px 12px;transition:border-color .2s,box-shadow .2s;font-family:inherit}
.ae-box input:focus,.ae-box textarea:focus{border-color:#5af;box-shadow:0 0 0 3px rgba(90,170,255,.12)}
.ae-box textarea{font-family:Consolas,'Courier New',monospace;line-height:1.6}
.chk{display:inline-flex;align-items:center;gap:6px;margin-right:16px;font-size:12px;color:#999}
.chk input{width:auto}
</style>
<h1 class="page-title"><?= $id>0 ? '编辑文章 #'.$id : '发布文章' ?></h1>
<div class="ae-box">
  <input id="aTitle" placeholder="文章标题" value="<?=htmlspecialchars($art['title'] ?? '')?>" style="margin-bottom:10px">
  <textarea id="aContent" rows="18" placeholder="支持 Markdown（- 列表缩进、**加粗**、$公式$、代码块...）"><?=htmlspecialchars($art['content'] ?? '')?></textarea>
  <div style="margin:10px 0">
    <?php if ($id>0 || $isAdmin): ?>
    <label class="chk"><input type="checkbox" id="aPublic" <?=($art['is_public']??0)?'checked':''?>> 设为公开</label>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <label class="chk"><input type="checkbox" id="aAnn" <?=($art['is_announcement']??0)?'checked':''?>> 设为公告（置顶）</label>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="saveArticle()">保存</button>
    <a class="btn btn-line" href="articles.php">返回</a>
    <span id="saveMsg" style="font-size:12px;color:#999"></span>
  </div>
</div>
<script>
async function saveArticle(){
  const btn=event.target; btn.disabled=true;
  const msg=document.getElementById('saveMsg');
  msg.textContent='保存中...';
  const fd=new FormData();
  fd.append('id',<?=$id?>);
  fd.append('title',document.getElementById('aTitle').value);
  fd.append('content',document.getElementById('aContent').value);
  fd.append('is_public',document.getElementById('aPublic')&&document.getElementById('aPublic').checked?'1':'0');
  fd.append('is_announcement',document.getElementById('aAnn')&&document.getElementById('aAnn').checked?'1':'0');
  try{
    const r=await fetch('api/article_save.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){ msg.textContent=d.message; setTimeout(()=>location.href='article.php?id='+d.id,800); }
    else{ msg.textContent=d.message||'保存失败'; btn.disabled=false; }
  }catch(e){ msg.textContent='保存失败: '+e.message; btn.disabled=false; }
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
