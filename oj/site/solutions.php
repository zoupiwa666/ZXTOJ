<?php
$pageTitle = '题解 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
require __DIR__.'/inc/article_tables.php';
requireLogin();
$me = currentUser();
$isAdmin = isAdmin();
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['problem'] ?? '');
if ($pid === '') die('缺少题目编号');
$s = $pdo->prepare("SELECT problem_id, title, solution_open FROM problems WHERE problem_id=?");
$s->execute([$pid]); $prob = $s->fetch();
if (!$prob) die('题目不存在');

// 已通过的题解
$list = $pdo->prepare("SELECT * FROM articles WHERE solution_problem=? AND is_solution=1 AND solution_status='approved' ORDER BY id DESC");
$list->execute([$pid]); $sols = $list->fetchAll();
?>
<style>
.sol-layout{display:flex;gap:20px;align-items:flex-start}
.sol-main{flex:1;min-width:0}
.sol-title{font-size:26px;color:#fff;font-weight:700;letter-spacing:2px;display:flex;align-items:baseline;gap:12px}
.sol-title .cnt{font-size:13px;color:#888;font-weight:400;letter-spacing:1px}
.sol-side{width:240px;flex-shrink:0;background:#141414;border:1px solid #222;border-radius:10px;padding:16px;position:sticky;top:16px}
.sol-side h3{font-size:12px;color:#888;letter-spacing:1px;margin-bottom:12px}
.sol-card{background:#141414;border:1px solid #222;border-radius:8px;padding:14px 18px;margin-bottom:10px;transition:border-color .15s,background .15s}
.sol-card:hover{border-color:#2a5a8c;background:#181818}
.sol-card .st{font-size:14px;color:#fff;text-decoration:none;font-weight:600}
.sol-card .st:hover{color:#5af}
.sol-card .sm{font-size:11px;color:#666;margin-top:5px}
</style>

<h1 style="font-size:15px;color:#888;margin-bottom:14px;font-weight:400">
  <a href="problem.php?id=<?=htmlspecialchars($pid)?>" style="color:#5af;text-decoration:none"><?=htmlspecialchars($prob['title'])?></a>
  <span style="color:#444">/</span> 题解
</h1>

<div class="sol-layout">
  <div class="sol-main">
    <div class="sol-title">
      题解
      <span class="cnt">共 <?=count($sols)?> 篇题解</span>
    </div>
    <div style="height:1px;background:#222;margin:14px 0 18px"></div>

    <?php if (!$sols): ?>
    <div style="padding:50px 0;text-align:center;color:#666;font-size:13px">还没有题解，来写第一篇吧！</div>
    <?php else: foreach ($sols as $a): ?>
    <div class="sol-card">
      <a class="st" href="article.php?id=<?=$a['id']?>"><?=htmlspecialchars($a['title'])?></a>
      <div class="sm"><?= userBadge($a['author'], null, 16) ?> · <?=date('Y-m-d H:i', strtotime($a['created_at']))?></div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="sol-side">
    <h3><i class="fa-solid fa-book-open"></i> 题解操作</h3>
    <?php if ($prob['solution_open'] || $isAdmin): ?>
    <a class="btn" style="width:100%;text-align:center;background:#1a3a5c;color:#5af" href="article_edit.php?problem=<?=htmlspecialchars($pid)?>"><i class="fa-solid fa-pen"></i> 提交题解</a>
    <div style="font-size:10px;color:#666;margin-top:8px;text-align:center">用文章写题解，提交后管理员审核</div>
    <?php else: ?>
    <div style="text-align:center;color:#c90;font-size:12px;padding:10px 0"><i class="fa-solid fa-lock"></i> 管理员已关闭新题解提交</div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div style="margin-top:16px;border-top:1px solid #222;padding-top:12px">
      <div style="font-size:11px;color:#888;margin-bottom:8px">管理员设置</div>
      <button class="btn btn-sm" style="width:100%" onclick="toggleOpen()"><?=$prob['solution_open']?'关闭':'开启'?>新题解提交</button>
      <span id="tgMsg" style="display:block;font-size:11px;color:#999;margin-top:6px"></span>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
async function toggleOpen(){
  const msg=document.getElementById('tgMsg'); msg.textContent='处理中...';
  const fd=new FormData(); fd.append('problem_id',<?=json_encode($pid)?>);
  const r=await fetch('api/solution_toggle.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){ msg.textContent=d.message; setTimeout(()=>location.reload(),600); }
  else msg.textContent=d.message||'操作失败';
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
