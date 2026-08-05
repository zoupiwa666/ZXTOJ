<?php
require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/auth.php';
requireLogin();

$pid = $_GET['problem_id'] ?? ($_GET['id'] ?? '');
if (!$pid) die("缺少题目编号");
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?");
$s->execute([$pid]); $problem = $s->fetch();
if (!$problem) die("题目不存在");

// 排序白名单：默认按用时升序
$sortMap = ['time'=>'s.total_time','memory'=>'s.peak_memory','score'=>'s.score','date'=>'s.id'];
$sortCol = $sortMap[$_GET['sort'] ?? 'time'] ?? 's.total_time';
$sortDir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

// 每人最后一次 AC 提交
$sql = "SELECT s.id, s.username, s.language, s.status, s.score, s.total_time, s.peak_memory, s.created_at
        FROM submissions s
        JOIN (SELECT username, MAX(id) AS mid FROM submissions
              WHERE problem_id=? AND status='AC' GROUP BY username) t
          ON s.id = t.mid
        ORDER BY $sortCol $sortDir, s.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$pid]);
$rows = $stmt->fetchAll();

$pageTitle = '统计 - ' . $problem['title'] . ' - Zxt Super OJ';
require __DIR__ . '/inc/header.php';
?>
<style>
.stat-wrap{max-width:900px;margin:0 auto}
.stat-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px}
.stat-head h1{font-size:16px;color:#fff;font-weight:400}
.stat-head .meta{font-size:11px;color:#888}
.data-table{width:100%;border-collapse:collapse;font-size:12px}
.data-table th,.data-table td{padding:8px 12px;text-align:left;border-bottom:1px solid #1a1a1a}
.data-table th{color:#888;font-weight:400;font-size:10px;text-transform:uppercase;letter-spacing:1px;background:#141414;position:sticky;top:0}
.data-table th a{color:#5af;text-decoration:none}
.data-table tr:hover td{background:#1a1a1a}
.data-table a{color:#ccc;text-decoration:none}.data-table a:hover{color:#fff}
.ac-badge{color:#25ad40;font-weight:700}
.time-cell,.mem-cell{font-family:Consolas,'Courier New',monospace;font-size:11px;color:#aaa}
.submitted{color:#666;font-size:11px}
.back{display:inline-block;padding:6px 16px;background:#2a2a2a;color:#ccc;text-decoration:none;font-size:12px;margin-bottom:16px}
.back:hover{background:#3a3a3a;color:#fff}
.empty{text-align:center;color:#666;padding:60px}
</style>

<div class="stat-wrap">
<a class="back" href="problem.php?id=<?=$pid?>">← 返回题目</a>
<div class="stat-head">
  <h1><i class="fa-solid fa-chart-bar"></i> <?=htmlspecialchars($problem['title'])?>  - AC 统计 <span class="meta"><?=$pid?> · 每人最后一次 AC 提交 · 共 <?=count($rows)?> 人</span></h1>
</div>

<table class="data-table">
<thead><tr>
  <th>状态</th><th>用户</th>
  <th><a href="?problem_id=<?=$pid?>&sort=time&dir=<?=((($_GET['sort']??'time')==='time' && ($_GET['dir']??'asc')==='asc')?'desc':'asc')?>">用时⇅</a></th>
  <th><a href="?problem_id=<?=$pid?>&sort=memory&dir=<?=((($_GET['sort']??'time')==='memory' && ($_GET['dir']??'asc')==='asc')?'desc':'asc')?>">内存⇅</a></th>
  <th><a href="?problem_id=<?=$pid?>&sort=score&dir=<?=((($_GET['sort']??'time')==='score' && ($_GET['dir']??'asc')==='asc')?'desc':'asc')?>">分数⇅</a></th>
  <th>语言</th>
  <th><a href="?problem_id=<?=$pid?>&sort=date&dir=<?=((($_GET['sort']??'time')==='date' && ($_GET['dir']??'asc')==='asc')?'desc':'asc')?>">AC 时间⇅</a></th>
</tr></thead>
<tbody>
<?php foreach($rows as $r):
  $t = floatval($r['total_time']);
  $timeStr = $t >= 1 ? number_format($t,3).'s' : number_format($t*1000).'ms';
?>
<tr>
  <td><a href="submission.php?id=<?=$r['id']?>" class="ac-badge">AC</a></td>
  <td><?= userBadge($r['username']) ?></td>
  <td class="time-cell"><?=$timeStr?></td>
  <td class="mem-cell"><?=number_format($r['peak_memory'],1)?> MB</td>
  <td style="color:#fff;font-weight:700"><?=$r['score']?></td>
  <td style="color:#888"><?=$r['language']?></td>
  <td class="submitted"><?=date('Y-m-d H:i',strtotime($r['created_at']))?></td>
</tr>
<?php endforeach; if(!$rows): ?>
<tr><td colspan="7" class="empty">暂无 AC 记录</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
