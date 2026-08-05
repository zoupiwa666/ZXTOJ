<?php $pageTitle = 'Home - Zxt Super OJ'; require __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/article_tables.php';
$anns = [];
try { $anns = $pdo->query("SELECT * FROM articles WHERE is_announcement=1 ORDER BY id DESC LIMIT 5")->fetchAll(); } catch (Exception $e) {}
?>
<style>
.ann-section{margin-bottom:24px}
.ann-section h2{font-size:14px;color:#ffab00;letter-spacing:1px;margin-bottom:12px}
.ann-card{background:#141414;border:1px solid #333;border-left:3px solid #ffab00;padding:14px 18px;margin-bottom:10px;transition:border-color .15s,background .15s}
.ann-card:hover{border-color:#ffab00;background:#181818}
.ann-title{color:#ffab00;font-size:14px;font-weight:600;text-decoration:none}
.ann-title:hover{text-decoration:underline}
.ann-meta{font-size:11px;color:#666;margin-top:4px}
</style>

<?php if ($anns): ?>
<div class="ann-section">
  <h2>📢 公告</h2>
  <?php foreach ($anns as $a): ?>
  <div class="ann-card">
    <a class="ann-title" href="article.php?id=<?=$a['id']?>"><?=htmlspecialchars($a['title'])?></a>
    <div class="ann-meta"><?=htmlspecialchars($a['author'])?> · <?=date('Y-m-d H:i', strtotime($a['created_at']))?></div>
  </div>
  <?php endforeach; ?>
  <div style="text-align:right"><a href="articles.php" style="color:#5af;text-decoration:none;font-size:12px">更多 →</a></div>
</div>
<?php endif; ?>

<div style="padding:60px 0;text-align:center;color:#555;font-size:13px">欢迎使用 ZXT Super OJ ✨</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
