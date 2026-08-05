<?php
$pageTitle = '文章 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
require __DIR__.'/inc/article_tables.php';
requireLogin();
$me = currentUser();
$perm = article_perm($me['username']);
$isAdmin = isAdmin();
?>
<h1 class="page-title">📰 文章</h1>

<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <?php if ($isAdmin || $perm['can_publish'] == 1): ?>
  <a class="btn btn-sm" href="article_edit.php" style="background:#1a3a5c;color:#5af">+ 发布文章</a>
  <?php endif; ?>
  <?php if ($isAdmin): ?>
  <a class="btn btn-sm" href="article_perms.php">权限管理</a>
  <?php endif; ?>
  <span style="font-size:11px;color:#888">新文章默认私密，仅作者和管理员可见</span>
</div>

<?php
// 公告（人人可见，置顶）
$anns = $pdo->query("SELECT * FROM articles WHERE is_announcement=1 ORDER BY id DESC LIMIT 10")->fetchAll();
// 公开文章（需查看权限） + 我的私密文章
if ($perm['can_view'] == 1 || $isAdmin) {
    $arts = $pdo->prepare("SELECT * FROM articles WHERE is_announcement=0 AND (is_public=1 OR author=?) ORDER BY id DESC LIMIT 50");
    $arts->execute([$me['username']]);
    $arts = $arts->fetchAll();
} else {
    // 无查看权限：只看自己的
    $arts = $pdo->prepare("SELECT * FROM articles WHERE is_announcement=0 AND author=? ORDER BY id DESC LIMIT 50");
    $arts->execute([$me['username']]);
    $arts = $arts->fetchAll();
}
?>

<?php if ($anns): ?>
<h2 style="font-size:13px;color:#ffab00;margin:12px 0 8px;letter-spacing:1px">📢 公告</h2>
<?php foreach ($anns as $a): ?>
<div class="card" style="padding:14px 18px;border-left:3px solid #ffab00">
  <a href="article.php?id=<?=$a['id']?>" style="color:#ffab00;text-decoration:none;font-size:14px;font-weight:600"><?=htmlspecialchars($a['title'])?></a>
  <div style="font-size:11px;color:#666;margin-top:4px"><?=$a['author']?> · <?=date('Y-m-d H:i', strtotime($a['created_at']))?></div>
</div>
<?php endforeach; endif; ?>

<h2 style="font-size:13px;color:#fff;margin:16px 0 8px;letter-spacing:1px">📄 文章<?= $perm['can_view']==0 && !$isAdmin ? '（仅我的）' : '' ?></h2>
<?php if (!$arts): ?>
<div style="padding:40px;text-align:center;color:#666;font-size:12px">暂无文章</div>
<?php else: foreach ($arts as $a): ?>
<div class="card" style="padding:14px 18px">
  <a href="article.php?id=<?=$a['id']?>" style="color:#fff;text-decoration:none;font-size:14px;font-weight:600"><?=htmlspecialchars($a['title'])?></a>
  <span style="font-size:10px;color:<?=$a['is_public']?'#0c0':'#c90'?>;margin-left:8px"><?=$a['is_public']?'公开':'私密'?></span>
  <div style="font-size:11px;color:#666;margin-top:4px"><?=$a['author']?> · <?=date('Y-m-d H:i', strtotime($a['created_at']))?></div>
</div>
<?php endforeach; endif; ?>
<?php require __DIR__.'/inc/footer.php'; ?>
