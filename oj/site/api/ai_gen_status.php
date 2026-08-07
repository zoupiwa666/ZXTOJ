<?php
// AI 自动造数据 - 任务状态轮询
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$taskId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['task_id'] ?? '');
if ($taskId === '') { echo json_encode(['ok'=>false,'message'=>'缺少 task_id']); exit; }

$sf = "/tmp/ai_gen/$taskId.status";
if (!file_exists($sf)) {
    echo json_encode(['ok'=>false, 'status'=>'missing', 'message'=>'任务不存在或已清理']);
    exit;
}
echo file_get_contents($sf);
