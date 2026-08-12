<?php
// 题目列表 JSON（供 OJ 客户端调用）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$role = $me['role'];
$isAdmin = in_array($role, ['admin','super_admin']);

$sql = "SELECT p.problem_id, p.title, p.time_limit, p.memory_limit, p.visibility, p.created_by,
        (SELECT COUNT(*) FROM submissions s WHERE s.problem_id=p.problem_id AND s.status='AC') AS ac_cnt,
        (SELECT COUNT(*) FROM submissions s WHERE s.problem_id=p.problem_id) AS sub_cnt
        FROM problems p";
$rows = $pdo->query($sql)->fetchAll();

$out = [];
foreach ($rows as $r) {
    if ($isAdmin || $r['visibility'] === 'public' || $r['created_by'] === $me['username']
        || canViewProblem($pdo, $r, $me['username'], $role)) {
        $out[] = [
            'problem_id' => $r['problem_id'],
            'title' => $r['title'],
            'time_limit' => floatval($r['time_limit']),
            'memory_limit' => intval($r['memory_limit']),
            'visibility' => $r['visibility'],
            'ac' => intval($r['ac_cnt']),
            'submissions' => intval($r['sub_cnt']),
        ];
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
