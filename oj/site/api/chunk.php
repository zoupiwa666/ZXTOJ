<?php
// 分片上传 - 接收单个分片
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$md5 = preg_replace('/[^a-f0-9]/i', '', $_POST['md5'] ?? '');
$index = intval($_POST['index'] ?? -1);
if (strlen($md5) !== 32 || $index < 0 || empty($_FILES['file']['tmp_name'])) {
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>'参数错误']); exit;
}
$dir = '/tmp/oj_uploads/'.$md5;
@mkdir($dir, 0777, true);
$dest = $dir.'/chunk_'.$index;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    http_response_code(500); echo json_encode(['ok'=>false,'message'=>'保存分片失败']); exit;
}
echo json_encode(['ok'=>true]);
