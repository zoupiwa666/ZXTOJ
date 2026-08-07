<?php
// 提交记录批量操作（管理员）：批量删除 / 批量重测
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$ids = json_decode($_POST['ids'] ?? '[]', true) ?: [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
if (!$action || empty($ids)) { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }

if ($action === 'delete') {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id IN ($in)");
    $stmt->execute($ids);
    echo json_encode(['ok'=>true,'message'=>'已删除 '.$stmt->rowCount().' 条提交记录']);
    exit;
}

if ($action === 'rejudge') {
    // 异步批量重测：全部重置为 waiting 交回 worker 队列，立即返回，不占 PHP-FPM worker
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE submissions SET status='waiting', judge_task_id=NULL, details='[]', score=0, passed_tests=0, total_time=0, peak_memory=0 WHERE id IN ($in)");
    $stmt->execute($ids);
    echo json_encode(['ok'=>true,'message'=>'已提交 '.$stmt->rowCount().' 条重测，结果将自动更新']);
    exit;
}

echo json_encode(['ok'=>false,'message'=>'未知操作']);
