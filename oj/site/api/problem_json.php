<?php
// 题目详情 JSON（供客户端）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id'] ?? '');
if ($pid === '') { echo json_encode(['ok'=>false,'message'=>'缺少 id']); exit; }
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?"); $s->execute([$pid]);
$p = $s->fetch();
if (!$p) { echo json_encode(['ok'=>false,'message'=>'题目不存在']); exit; }
$me = currentUser();
if (!canViewProblem($pdo, $p, $me['username'], $me['role'])) { echo json_encode(['ok'=>false,'message'=>'无权查看']); exit; }
$sm = $pdo->prepare("SELECT sort_order,input_text,output_text FROM problem_samples WHERE problem_id=? ORDER BY sort_order");
$sm->execute([$pid]);
echo json_encode([
    'ok' => true,
    'problem_id' => $p['problem_id'],
    'title' => $p['title'],
    'background' => $p['background'] ?? '',
    'description' => $p['description'] ?? '',
    'input_format' => $p['input_format'] ?? '',
    'output_format' => $p['output_format'] ?? '',
    'hints' => $p['hints'] ?? '',
    'time_limit' => floatval($p['time_limit']),
    'memory_limit' => intval($p['memory_limit']),
    'visibility' => $p['visibility'],
    'samples' => $sm->fetchAll(),
], JSON_UNESCAPED_UNICODE);
