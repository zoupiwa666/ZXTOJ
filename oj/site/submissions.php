<?php $pageTitle = '提交记录 - Zxt Super OJ'; require __DIR__ . '/inc/header.php';
$where = []; $params = [];
if (!empty($_GET['problem'])) { $where[] = "problem_id = ?"; $params[] = $_GET['problem']; }
if (!empty($_GET['user']))    { $where[] = "username = ?"; $params[] = $_GET['user']; }
if (!empty($_GET['lang']))    { $where[] = "language = ?"; $params[] = $_GET['lang']; }
if (!empty($_GET['status']))  { $where[] = "status = ?"; $params[] = $_GET['status']; }
if (!empty($_GET['group'])) {
    $grp = $_GET['group'];
    $where[] = "username IN (SELECT username FROM user_group_members m JOIN user_groups g ON m.group_id=g.id WHERE g.name=?)";
    $params[] = $grp;
}
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page - 1) * $perPage;
$sql = "SELECT * FROM submissions"; if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$total = $pdo->prepare("SELECT COUNT(*) FROM submissions" . ($where ? " WHERE ".implode(" AND ",$where) : ""));
$total->execute($params); $total = $total->fetchColumn();
$totalPages = ceil($total / $perPage);
$sql .= " ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$colorMap = ['AC'=>'#25ad40','WA'=>'#ff4f4f','TLE'=>'#ffab00','RE'=>'#f8603a','MLE'=>'#d500f9','OLE'=>'#0091ea','CE'=>'#ff9100','SE'=>'#999','judging'=>'#09f','waiting'=>'#666'];
$labelMap = ['AC'=>'Accepted','WA'=>'Wrong Answer','TLE'=>'Time Exceeded','RE'=>'Runtime Error','MLE'=>'Memory Exceeded','OLE'=>'Output Exceeded','CE'=>'Compile Error','SE'=>'System Error','judging'=>'Judging','waiting'=>'Waiting'];
?>
<style>
.filter-bar{background:#141414;border:1px solid #222;padding:16px 20px;margin-bottom:16px}
.filter-bar h3{font-size:13px;color:#fff;font-weight:400;margin-bottom:12px;letter-spacing:1px;display:flex;justify-content:space-between}
.filter-row{display:flex;gap:12px;flex-wrap:wrap;align-items:end}
.filter-row>div{flex:1;min-width:120px}
.filter-row label{font-size:10px;color:#888;display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:1px}
.filter-row input,.filter-row select{width:100%;padding:6px 10px;background:#0a0a0a;border:1px solid #2a2a2a;color:#ccc;font-size:12px;font-family:inherit;outline:none}
.filter-row input:focus,.filter-row select:focus{border-color:#444}
.btn-sm{padding:6px 18px;background:#2a2a2a;color:#ccc;border:none;font-size:11px;cursor:pointer;font-family:inherit;letter-spacing:1px}
.btn-sm:hover{background:#3a3a3a;color:#fff}
.data-table{width:100%;border-collapse:collapse;font-size:12px}
.data-table th,.data-table td{padding:8px 12px;text-align:left;border-bottom:1px solid #1a1a1a}
.data-table th{color:#888;font-weight:400;font-size:10px;text-transform:uppercase;letter-spacing:1px;background:#141414;position:sticky;top:0;z-index:1}
.data-table tr:hover td{background:#1a1a1a}
.data-table a{color:#ccc;text-decoration:none}.data-table a:hover{color:#fff}
.status-cell{font-weight:600;font-size:11px;white-space:nowrap}
.status-score{font-size:13px;font-weight:700}
.time-cell,.mem-cell{font-family:monospace;font-size:11px;color:#aaa}
.submitted{color:#666;font-size:11px}
.pager{display:flex;gap:6px;justify-content:center;margin-top:16px}
.pager a,.pager span{padding:6px 14px;border:1px solid #222;color:#888;text-decoration:none;font-size:12px;background:#141414}
.pager a:hover{color:#fff;border-color:#444}.pager .current{color:#fff;border-color:#444}
</style>

<h1 class="page-title">提交记录</h1>

<div class="filter-bar">
<h3>过滤 <span style="font-size:11px">共 <?=$total?> 条记录</span></h3>
<form method="GET">
<div class="filter-row">
  <div><label>用户</label><input name="user" value="<?=htmlspecialchars($_GET['user']??'')?>" placeholder="用户名"></div>
  <div><label>题目</label><input name="problem" value="<?=htmlspecialchars($_GET['problem']??'')?>" placeholder="题目编号"></div>
  <div><label>语言</label>
    <select name="lang"><option value="">全部</option>
      <option value="python3" <?=($_GET['lang']??'')=='python3'?'selected':''?>>Python 3</option>
      <option value="cpp17" <?=($_GET['lang']??'')=='cpp17'?'selected':''?>>C++17</option>
      <option value="cpp20" <?=($_GET['lang']??'')=='cpp20'?'selected':''?>>C++20</option>
      <option value="cpp14" <?=($_GET['lang']??'')=='cpp14'?'selected':''?>>C++14</option>
      <option value="c" <?=($_GET['lang']??'')=='c'?'selected':''?>>C</option>
    </select>
  </div>
  <div><label>用户组</label>
    <select name="group"><option value="">全部</option>
      <?php foreach($pdo->query("SELECT * FROM user_groups ORDER BY name") as $grp): ?>
        <option value="<?=$grp['name']?>" <?=($_GET['group']??'')===$grp['name']?'selected':''?>><?=htmlspecialchars($grp['name'])?></option>
      <?php endforeach?>
    </select>
  </div>
  <div><label>状态</label>
    <select name="status"><option value="">全部</option>
      <?php foreach(['AC','WA','TLE','RE','MLE','OLE','CE','judging','waiting'] as $st): ?>
        <option value="<?=$st?>" <?=($_GET['status']??'')==$st?'selected':''?>><?=$labelMap[$st]?></option>
      <?php endforeach?>
    </select>
  </div>
  <div style="display:flex;gap:8px;align-items:end">
    <button class="btn-sm">筛选</button>
    <a href="submissions.php" class="btn-sm" style="background:transparent;border:1px solid #333;text-decoration:none">重置</a>
  </div>
</div>
</form>
</div>

<table class="data-table">
<thead><tr><th>状态</th><th>题目</th><th>递交者</th><th>用时</th><th>内存</th><th>语言</th><th>递交时间</th></tr></thead>
<tbody>
<?php foreach($rows as $r): 
  $sc = $r['status']; $color = $colorMap[$sc] ?? '#999'; $label = $labelMap[$sc] ?? $sc;
  $isPass = $sc === 'AC';
  $timeStr = $r["total_time"] >= 1 ? number_format($r['total_time'],3).'s' : number_format($r['total_time']*1000).'ms';
  $memStr = number_format($r['peak_memory'],1).' MiB';
?>
<tr>
  <td><a href="submission.php?id=<?=$r['id']?>" class="status-cell" style="color:<?=$color?>">
    <span class="status-score"><?=intval($r['score'])?></span> <?=$label?>
  </a></td>
  <td><a href="problem.php?id=<?=$r['problem_id']?>"><b><?=$r['problem_id']?></b></a></td>
  <td><a href="user.php?name=<?=urlencode($r['username'])?>"><?=htmlspecialchars($r['username'])?></a></td>
  <td class="time-cell"><?=$timeStr?></td>
  <td class="mem-cell"><?=$memStr?></td>
  <td><?=$r['language']?></td>
  <td class="submitted"><?=date('Y-m-d H:i',strtotime($r['created_at']))?></td>
</tr>
<?php endforeach; if(!$rows): ?>
<tr><td colspan="7" style="text-align:center;color:#666;padding:60px">暂无提交记录</td></tr>
<?php endif; ?>
</tbody>
</table>

<?php if($totalPages > 1): ?>
<div class="pager">
  <?php if($page > 1): $qp = $_GET; $qp['page'] = $page-1; ?><a href="?<?=http_build_query($qp)?>">上一页</a><?php endif?>
  <?php for($p=1;$p<=$totalPages;$p++): $qp=$_GET;$qp['page']=$p; ?>
    <?php if($p==$page): ?><span class="current"><?=$p?></span>
    <?php else: ?><a href="?<?=http_build_query($qp)?>"><?=$p?></a><?php endif?>
  <?php endfor?>
  <?php if($page < $totalPages): $qp = $_GET; $qp['page'] = $page+1; ?><a href="?<?=http_build_query($qp)?>">下一页</a><?php endif?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
