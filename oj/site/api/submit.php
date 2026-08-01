<?php
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/auth.php';
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { http_response_code(400); die(json_encode(['error'=>'Invalid JSON'])); }

$lang = $data['language'] ?? ''; $code = $data['code'] ?? ''; $pid = $data['problem_id'] ?? '';
$tl = floatval($data['time_limit'] ?? 2.0); $ml = intval($data['memory_limit'] ?? 128);
$username = currentUser()['username'];

// 只查元数据，不加载数据
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, SUM(score) as total FROM problem_testcases WHERE problem_id=?");
$stmt->execute([$pid]); $info = $stmt->fetch();
$totalTests = intval($info['cnt'] ?? 0);
$maxScore = floatval($info['total'] ?? 100);
if ($totalTests == 0) { http_response_code(404); die('{"error":"No test data"}'); }

// 插入记录，状态 waiting
$pdo->prepare("INSERT INTO submissions (username,problem_id,language,code,status,max_score,total_tests) VALUES (?,?,?,?,'waiting',?,?)")
    ->execute([$username, $pid, $lang, $code, $maxScore, $totalTests]);
$submissionId = $pdo->lastInsertId();

// 立即返回，不做任何 judge 调用
echo json_encode(['submission_id' => $submissionId]);
