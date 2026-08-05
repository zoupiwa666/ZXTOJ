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
$sortMap = ['id'=>'id','score'=>'score','time'=>'total_time','memory'=>'peak_memory','date'=>'created_at'];
$sortCol = $sortMap[$_GET['sort'] ?? 'id'] ?? 'id';
$sortDir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$sql .= " ORDER BY $sortCol $sortDir, id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$colorMap = ['AC'=>'#25ad40','WA'=>'#ff4f4f','TLE'=>'#ffab00','RE'=>'#f8603a','MLE'=>'#d500f9','OLE'=>'#0091ea','CE'=>'#ff9100','SE'=>'#999','judging'=>'#09f','waiting'=>'#666','compiling'=>'#ffab00'];
$labelMap = ['AC'=>'Accepted','WA'=>'Wrong Answer','TLE'=>'Time Exceeded','RE'=>'Runtime Error','MLE'=>'Memory Exceeded','OLE'=>'Output Exceeded','CE'=>'Compile Error','SE'=>'System Error','judging'=>'Judging','waiting'=>'Waiting','compiling'=>'编译中'];
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
.time-cell,.mem-cell{font-family:Consolas,'Courier New',monospace;font-size:11px;color:#aaa}
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

<?php if (isAdmin()): ?>
<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
  <span style="font-size:12px;color:#888">批量操作:</span>
  <button class="btn btn-sm" onclick="batchAction('rejudge')">批量重测</button>
  <button class="btn btn-sm btn-danger" onclick="batchAction('delete')">批量删除</button>
  <span id="batchMsg" style="font-size:12px;color:#999"></span>
</div>
<?php endif; ?>

<table class="data-table">
<thead><tr>
<?php if (isAdmin()): ?><th style="width:30px"><input type="checkbox" id="checkAll" onclick="toggleAll(this)" style="width:auto"></th><?php endif; ?>
<th>状态</th><th>题目</th><th>递交者</th>
<th><a href="?<?=http_build_query(array_merge($_GET,['sort'=>'time','dir'=>((($_GET['sort']??'')==='time' && ($_GET['dir']??'desc')==='asc')?'desc':'asc')]))?>" style="color:#888;text-decoration:none">用时⇅</a></th>
<th><a href="?<?=http_build_query(array_merge($_GET,['sort'=>'memory','dir'=>((($_GET['sort']??'')==='memory' && ($_GET['dir']??'desc')==='asc')?'desc':'asc')]))?>" style="color:#888;text-decoration:none">内存⇅</a></th>
<th><a href="?<?=http_build_query(array_merge($_GET,['sort'=>'score','dir'=>((($_GET['sort']??'')==='score' && ($_GET['dir']??'desc')==='asc')?'desc':'asc')]))?>" style="color:#888;text-decoration:none">分数⇅</a></th>
<th>语言</th>
<th><a href="?<?=http_build_query(array_merge($_GET,['sort'=>'date','dir'=>((($_GET['sort']??'')==='date' && ($_GET['dir']??'desc')==='asc')?'desc':'asc')]))?>" style="color:#888;text-decoration:none">递交时间⇅</a></th>
</tr></thead>
<tbody>
<?php foreach($rows as $r): 
  $sc = $r['status']; $color = $colorMap[$sc] ?? '#999'; $label = $labelMap[$sc] ?? $sc;
  $isPass = $sc === 'AC';
  $timeStr = $r["total_time"] >= 1 ? number_format($r['total_time'],3).'s' : number_format($r['total_time']*1000).'ms';
  $memStr = number_format($r['peak_memory'],1).' MB';
?>
<tr>
  <?php if (isAdmin()): ?><td style="text-align:center"><input type="checkbox" class="row-check" value="<?=$r['id']?>" style="width:auto"></td><?php endif; ?>
  <td><a href="submission.php?id=<?=$r['id']?>" class="status-cell" style="color:<?=$color?>">
    <span class="status-score"><?=intval($r['score'])?></span> <?=$label?>
  </a></td>
  <td><a href="problem.php?id=<?=$r['problem_id']?>"><b><?=$r['problem_id']?></b></a></td>
  <td><?= userBadge($r['username']) ?></td>
  <td class="time-cell"><?=$timeStr?></td>
  <td class="mem-cell"><?=$memStr?></td>
  <td><?=$r['language']?></td>
  <td class="submitted"><?=date('Y-m-d H:i',strtotime($r['created_at']))?></td>
</tr>
<?php endforeach; if(!$rows): ?>
<tr><td colspan="<?= isAdmin() ? 8 : 7 ?>" style="text-align:center;color:#666;padding:60px">暂无提交记录</td></tr>
<?php endif; ?>
</tbody>
</table>

<?php if (isAdmin()): ?>
<script>
function toggleAll(cb){ document.querySelectorAll('.row-check').forEach(x=>x.checked=cb.checked); }
async function batchAction(action){
  const boxes = [...document.querySelectorAll('.row-check:checked')];
  if(!boxes.length){ alert('请先勾选要处理的提交记录'); return; }
  const ids = boxes.map(x=>x.value);
  const isDel = action==='delete';
  if(!confirm('确定对 '+ids.length+' 条提交'+(isDel?'执行删除？此操作不可恢复！':'执行重测？将覆盖当前结果。'))) return;
  const msg = document.getElementById('batchMsg');
  msg.textContent = '处理中，请稍候...';
  const fd = new FormData();
  fd.append('action', action);
  fd.append('ids', JSON.stringify(ids));
  try{
    const r = await fetch('api/batch_submissions.php', {method:'POST', body:fd});
    const d = await r.json();
    if(d.ok){ msg.textContent = d.message; setTimeout(()=>location.reload(), 1200); }
    else { msg.textContent = d.message || '操作失败'; }
  }catch(e){ msg.textContent = '操作失败: '+e.message; }
}
</script>
<?php endif; ?>

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
