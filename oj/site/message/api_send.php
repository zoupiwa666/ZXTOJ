<?php
// 管理员：批量发送系统消息
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/sanitize.php';
require __DIR__.'/functions.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');
$to = trim($_POST['to'] ?? '');
$content = sanitize_text(trim($_POST['content'] ?? ''), 2000);
if ($to === '' || $content === '') { echo json_encode(['ok'=>false,'message'=>'收件人或内容不能为空']); exit; }
$r = msg_send_batch($to, $content);
echo json_encode(['ok'=>true, 'message'=>"发送完成：成功 {$r['ok']} 人，失败 {$r['fail']} 人"]);
