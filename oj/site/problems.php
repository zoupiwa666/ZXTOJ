<?php $pageTitle = '题库 - Zxt Super OJ'; require __DIR__ . '/inc/header.php';

// 当前用户每题状态
$userStatus = []; $userAC = [];
if (isLoggedIn()) {
    $uname = currentUser()['username'];
    $role = currentUser()['role'];
    $stmt = $pdo->prepare("SELECT s.problem_id, s.status, s.score FROM submissions s
        INNER JOIN (SELECT problem_id, MAX(id) AS max_id FROM submissions WHERE username=? GROUP BY problem_id) latest
        ON s.problem_id=latest.problem_id AND s.id=latest.max_id WHERE s.username=?");
    $stmt->execute([$uname, $uname]);
    foreach ($stmt->fetchAll() as $row) $userStatus[$row['problem_id']] = $row;
    $stmt = $pdo->prepare("SELECT DISTINCT problem_id FROM submissions WHERE username=? AND status='AC'");
    $stmt->execute([$uname]);
    foreach ($stmt->fetchAll() as $row) $userAC[$row['problem_id']] = true;

    // 可见题目查询
    if (in_array($role, ['super_admin','admin'])) {
        $problems = $pdo->query("SELECT * FROM problems ORDER BY id")->fetchAll();
    } else {
        // 全部题目后按 canViewProblem 过滤（支持 team->组）
        $all = $pdo->query("SELECT * FROM problems ORDER BY id")->fetchAll();
        $problems = array_filter($all, function($p) use ($pdo, $currentUser) {
            return canViewProblem($pdo, $p, $currentUser['username'], $currentUser['role']);
        });
        $problems = array_values($problems);
    echo "<!-- DEBUG: role=$role uname=$uname count=".count($problems)." -->";
    }
} else {
    $problems = $pdo->query("SELECT * FROM problems WHERE visibility='public' ORDER BY id")->fetchAll();
}

// 搜索：按题目名/编号过滤 + 关联度排序
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $qLower = mb_strtolower($q);
    $problems = array_values(array_filter($problems, function($p) use ($qLower) {
        return mb_stripos($p['problem_id'], $qLower) !== false
            || mb_stripos($p['title'], $qLower) !== false;
    }));
    usort($problems, function($a, $b) use ($qLower) {
        $score = function($p) use ($qLower) {
            $pid = mb_strtolower($p['problem_id']);
            $title = mb_strtolower($p['title']);
            if ($pid === $qLower) return 0;              // 编号完全匹配
            if (strpos($pid, $qLower) === 0) return 1;   // 编号前缀
            if (strpos($title, $qLower) === 0) return 2; // 标题前缀
            return 3;                                    // 其他包含
        };
        $sa = $score($a); $sb = $score($b);
        return $sa === $sb ? strcmp($a['problem_id'], $b['problem_id']) : $sa - $sb;
    });
}
?>
<style>
.p-table{width:100%;border-collapse:collapse;font-size:13px}
.p-table th,.p-table td{padding:10px 16px;text-align:left;border-bottom:1px solid #1a1a1a}
.p-table th{color:#888;font-weight:400;font-size:10px;text-transform:uppercase;letter-spacing:1px}
.p-table tr:hover td{background:#1a1a1a}
.p-table a{color:#ddd;text-decoration:none}.p-table a:hover{color:#fff}
.pid{color:#888}.pid b{color:#ddd}
.status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:8px;vertical-align:middle}
.dot-AC{background:#25ad40}.dot-WA{background:#ff4f4f}.dot-TLE{background:#ffab00}
.dot-RE{background:#f8603a}.dot-MLE{background:#d500f9}.dot-OLE{background:#0091ea}.dot-CE{background:#ff9100}
.status-tag{font-size:11px;font-weight:600}.st-AC{color:#25ad40}.st-WA{color:#ff4f4f}.st-TLE{color:#ffab00}.st-RE{color:#f8603a}
</style>
<h1 class="page-title">题库</h1>
<?php if (isAdmin()): ?><div style="margin-bottom:16px"><a href="edit.php" class="btn btn-sm">+ 新建题目</a></div><?php endif ?>
<form method="GET" action="problems.php" style="margin-bottom:16px;display:flex;gap:8px;max-width:420px">
  <input type="text" name="q" class="no-float" value="<?=htmlspecialchars($q)?>" placeholder="搜索题目名或编号..." style="flex:1;padding:8px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#ddd;font-size:13px;outline:none">
  <button class="btn btn-sm" type="submit">搜索</button>
  <?php if ($q !== ''): ?><a class="btn btn-sm btn-line" href="problems.php">清除</a><?php endif; ?>
</form>
<?php if ($q !== ''): ?><div style="font-size:12px;color:#888;margin-bottom:12px">搜索 "<?=htmlspecialchars($q)?>"：找到 <?=count($problems)?> 道题</div><?php endif; ?>
<table class="p-table">
<tr><th>编号</th><th>标题</th><th>创建者</th><th>时限</th><th>内存</th><?php if(isAdmin()):?><th></th><?php endif?></tr>
<?php foreach($problems as $p):
  $ac = isset($userAC[$p['problem_id']]);
  $us = $userStatus[$p['problem_id']] ?? null;
  $dot = ''; $tag = '';
  if ($ac) { $dot='<span class="status-dot dot-AC"></span>'; $tag='<span class="status-tag st-AC">AC</span>'; }
  elseif ($us) { $st=$us['status']; $dot='<span class="status-dot dot-'.$st.'"></span>'; $tag='<span class="status-tag st-'.$st.'">'.$st.'</span>'; }
?>
<tr>
  <td class="pid"><?=$dot?><b><?=$p['problem_id']?></b> <?=$tag?></td>
  <td><a href="problem.php?id=<?=$p['problem_id']?>"><?=htmlspecialchars($p['title'])?></a></td>
  <td><?= creator_display($p['created_by'] ?? null) ?></td>
  <td><?=$p['time_limit']?>s</td>
  <td><?=$p['memory_limit']?>MB</td>
  <?php if(isAdmin()):?><td><a href="edit.php?id=<?=$p['problem_id']?>" class="btn-sm">编辑</a></td><?php endif?>
</tr>
<?php endforeach; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
