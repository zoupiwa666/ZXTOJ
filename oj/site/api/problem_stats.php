<?php
// 题目提交统计 API：返回某题所有提交记录，支持排序
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/auth.php';
requireLogin();

$pid = $_GET['problem_id'] ?? '';
if (!$pid) { http_response_code(400); die(json_encode(['error' => '缺少 problem_id'])); }

// 排序白名单
$sortMap = [
    'id'     => 'id',
    'score'  => 'score',
    'time'   => 'total_time',
    'memory' => 'peak_memory',
    'date'   => 'created_at',
];
$sort = $sortMap[$_GET['sort'] ?? 'id'] ?? 'id';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$limit = min(max(intval($_GET['limit'] ?? 200), 1), 500);

$stmt = $pdo->prepare("SELECT s.id, s.username, s.language, s.status, s.score, s.total_time, s.peak_memory, s.created_at, u.avatar AS user_avatar
                       FROM submissions s LEFT JOIN users u ON u.username = s.username COLLATE utf8mb4_uca1400_ai_ci WHERE s.problem_id=? ORDER BY $sort $dir LIMIT $limit");
$stmt->execute([$pid]);
$rows = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode(['problem_id' => $pid, 'count' => count($rows), 'rows' => $rows], JSON_UNESCAPED_UNICODE);
