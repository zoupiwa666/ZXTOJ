<?php
require __DIR__ . '/inc/config.php';
$username = $_GET['name'] ?? '';
require __DIR__ . '/inc/auth.php';
$s = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$s->execute([$username]); $profile = $s->fetch();
if (!$profile) die("User not found");

$stats = $pdo->prepare("
    SELECT problem_id, 
           MAX(CASE WHEN status='AC' THEN score ELSE 0 END) AS best_score,
           MAX(max_score) AS max_score,
           COUNT(*) AS attempts,
           SUM(CASE WHEN status='AC' THEN 1 ELSE 0 END) AS ac_count
    FROM submissions 
    WHERE username = ?
    GROUP BY problem_id ORDER BY problem_id
");
$stats->execute([$username]);
$rows = $stats->fetchAll();

$acList = []; $tryList = [];
foreach ($rows as $r) {
    if ($r['ac_count'] > 0) $acList[] = $r;
    else $tryList[] = $r;
}

$totalSubs = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE username = ?");
$totalSubs->execute([$username]); $totalSubs = $totalSubs->fetchColumn();

$acSubs = $pdo->prepare("SELECT COUNT(DISTINCT problem_id) FROM submissions WHERE username = ? AND status='AC'");
$acSubs->execute([$username]); $acSubs = $acSubs->fetchColumn();

$isOwner = isLoggedIn() && currentUser()['id'] == $profile['id'];
$pageTitle = $username . ' - Zxt Super OJ';
require __DIR__ . '/inc/header.php';
?>
<style>
.profile-header{display:flex;gap:24px;align-items:start;margin-bottom:32px}
.avatar-box{width:50px;height:50px;border:1px solid #333;overflow:hidden;flex-shrink:0}
.avatar-box img{width:100%;height:100%;object-fit:cover}
.avatar-default{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#000;color:#999;font-size:20px;font-weight:700}
.profile-info{flex:1}
.profile-info h1{font-size:18px;color:#fff;font-weight:400;margin-bottom:4px;letter-spacing:1px}
.profile-info .motto{font-size:12px;color:#999;margin-bottom:12px;font-style:italic}
.profile-info .motto:empty::after{content:'暂无格言.'}
.profile-stats{display:flex;gap:24px;font-size:12px;color:#999}
.profile-stats b{color:#ccc}
.edit-link{font-size:11px;color:#999;text-decoration:none;border:1px solid #333;padding:3px 10px;margin-left:8px}
.edit-link:hover{color:#fff;border-color:#999}
.section-title{font-size:13px;color:#fff;font-weight:400;margin:24px 0 12px;letter-spacing:1px}
.p-table{width:100%;border-collapse:collapse;font-size:12px}
.p-table th,.p-table td{padding:8px 12px;text-align:left;border-bottom:1px solid #111}
.p-table th{color:#999;font-weight:400;font-size:10px;text-transform:uppercase;letter-spacing:1px}
.p-table tr:hover td{background:#1a1a1a}
.p-table a{color:#ccc;text-decoration:none}.p-table a:hover{color:#fff}
.ac-badge{color:#0c0;font-size:11px}.no-ac{color:#999;font-size:11px}
.empty{text-align:center;color:#999;padding:20px;font-size:12px}
</style>

<div class="profile-header">
  <div class="avatar-box">
    <?php if ($profile['avatar']): ?><img src="<?=htmlspecialchars($profile['avatar'])?>" alt="">
    <?php else: ?><div class="avatar-default"><?=strtoupper(substr($username,0,1))?></div><?php endif ?>
  </div>
  <div class="profile-info">
    <h1><?=htmlspecialchars($username)?><?php if ($isOwner): ?><a href="profile.php" class="edit-link">编辑</a><?php endif ?></h1>
    <div class="motto"><?=htmlspecialchars($profile['motto'])?></div>
    <div class="profile-stats">
      <span>提交数: <b><?=$totalSubs?></b></span>
      <span>通过: <b><?=$acSubs?></b></span>
      <span>角色: <b><?=$profile['role']?></b></span>
    </div>
  </div>
</div>

<?php if ($acList): ?>
<div class="section-title">已通过 (<?=count($acList)?>)</div>
<table class="p-table">
<tr><th>题目</th><th>分数</th><th>尝试次数</th></tr>
<?php foreach($acList as $p): ?>
<tr>
  <td><a href="problem.php?id=<?=$p['problem_id']?>"><?=$p['problem_id']?></a></td>
  <td><span class="ac-badge"><?=$p['best_score']?>/<?=$p['max_score']?></span></td>
  <td><?=$p['attempts']?></td>
</tr>
<?php endforeach ?>
</table>
<?php endif ?>

<?php if ($tryList): ?>
<div class="section-title">尝试中 (<?=count($tryList)?>)</div>
<table class="p-table">
<tr><th>题目</th><th>最高分</th><th>尝试次数</th></tr>
<?php foreach($tryList as $p): ?>
<tr>
  <td><a href="problem.php?id=<?=$p['problem_id']?>"><?=$p['problem_id']?></a></td>
  <td><?=$p['best_score']?>/<?=$p['max_score']?></td>
  <td><?=$p['attempts']?></td>
</tr>
<?php endforeach ?>
</table>
<?php endif ?>

<?php if (!$acList && !$tryList): ?>
<div class="empty">暂无提交.</div>
<?php endif ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
