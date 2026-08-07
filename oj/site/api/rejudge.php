<?php
// 重测：管理员对已提交代码重新评测，结果覆盖到同一提交（异步，不占 PHP-FPM worker）
// 原理：将提交重置为 waiting，交回 oj_worker.py 队列异步评测，前端轮询自动刷新结果
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$sid = intval($_POST['submission_id'] ?? 0);
if ($sid <= 0) { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }

$s = $pdo->prepare("SELECT id FROM submissions WHERE id=?");
$s->execute([$sid]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'提交不存在']); exit; }

// 重置为 waiting，worker 会在数秒内自动接管评测（不再同步轮询，立即返回）
$pdo->prepare("UPDATE submissions SET status='waiting', judge_task_id=NULL, details='[]', score=0, passed_tests=0, total_time=0, peak_memory=0 WHERE id=?")
    ->execute([$sid]);

echo json_encode(['ok'=>true,'message'=>'已提交重测，结果将自动更新']);
