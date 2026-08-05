<?php
$pageTitle = '发布文章 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
require __DIR__.'/inc/article_tables.php';
requireLogin();
$me = currentUser();
$perm = article_perm($me['username']);
$isAdmin = isAdmin();
$id = intval($_GET['id'] ?? 0);
// 从题解区"提交题解"进入时预填题目编号
$preProblem = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['problem'] ?? '');
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/contrib/auto-render.min.js"></script>
<script src="assets/marked.min.js"></script>
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
  <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
    <button class="btn btn-sm" id="tabEdit" onclick="switchTab('edit')" style="background:#1a3a5c;color:#5af">✏️ 编辑</button>
    <button class="btn btn-sm" id="tabPrev" onclick="switchTab('prev')">👁️ 预览</button>
    <span style="font-size:11px;color:#888;margin-left:auto">Markdown + 公式 + 代码高亮，实时预览</span>
  </div>
  <textarea id="aContent" rows="18" placeholder="支持 Markdown（- 列表缩进、**加粗**、$公式$、代码块...）" oninput="schedulePreview()"><?=htmlspecialchars($art['content'] ?? '')?></textarea>
  <div id="aPreview" class="md" style="display:none;background:#141414;border:1px solid #222;border-radius:8px;padding:16px;min-height:300px;line-height:1.8"></div>
  <div style="margin:10px 0">
    <?php if ($id>0 || $isAdmin): ?>
    <label class="chk"><input type="checkbox" id="aPublic" <?=($art['is_public']??0)?'checked':''?>> 设为公开</label>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <label class="chk"><input type="checkbox" id="aAnn" <?=($art['is_announcement']??0)?'checked':''?>> 设为公告（置顶）</label>
    <?php endif; ?>
    <label class="chk"><input type="checkbox" id="aSol" <?=(($art['is_solution']??0)||$preProblem)?'checked':''?> onchange="solNote()"> 添加为题解</label>
    <input id="solPid" placeholder="题目编号 (如 P1000)" value="<?=htmlspecialchars(($art['solution_problem'] ?? $preProblem) ?? '')?>" style="width:140px;display:inline-block;padding:6px 10px">
    <span id="solNote" style="font-size:11px;color:#888"></span>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="saveArticle()">保存</button>
    <a class="btn btn-line" href="articles.php">返回</a>
    <span id="saveMsg" style="font-size:12px;color:#999"></span>
  </div>
</div>
<script>
let prevTimer=null;
function switchTab(tab){
  const isPrev = tab==='prev';
  document.getElementById('aContent').style.display = isPrev?'none':'block';
  document.getElementById('aPreview').style.display = isPrev?'block':'none';
  document.getElementById('tabEdit').style.background = isPrev?'':'#1a3a5c';
  document.getElementById('tabEdit').style.color = isPrev?'':'#5af';
  document.getElementById('tabPrev').style.background = isPrev?'#1a3a5c':'';
  document.getElementById('tabPrev').style.color = isPrev?'#5af':'';
  if(isPrev) renderPreview();
}
function schedulePreview(){ clearTimeout(prevTimer); prevTimer=setTimeout(renderPreview, 500); }
function solNote(){
  const note=document.getElementById('solNote');
  const on=document.getElementById('aSol').checked;
  if(!on){ note.textContent=''; return; }
  note.textContent = <?=$isAdmin?'"管理员发布题解，直接通过审核"':'"提交后需管理员审核"'; ?>;
}
function renderPreview(){
  const el=document.getElementById('aPreview');
  const md=document.getElementById('aContent').value;
  el.textContent=md;
  el.innerHTML=marked.parse(el.textContent);
  if (typeof renderMathInElement === 'function') {
    renderMathInElement(el, {delimiters:[{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],throwOnError:false});
  }
  if (window.highlightCodeBlocks) highlightCodeBlocks(el);
}
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
  fd.append('is_solution',document.getElementById('aSol').checked?'1':'0');
  fd.append('solution_problem',document.getElementById('solPid').value.trim());
  try{
    const r=await fetch('api/article_save.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){ msg.textContent=d.message; setTimeout(()=>location.href='article.php?id='+d.id,800); }
    else{ msg.textContent=d.message||'保存失败'; btn.disabled=false; }
  }catch(e){ msg.textContent='保存失败: '+e.message; btn.disabled=false; }
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
