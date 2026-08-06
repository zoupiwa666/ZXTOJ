<?php
// 我的文件 - 分片上传（单分片）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$md5 = preg_replace('/[^a-f0-9]/i', '', $_POST['md5'] ?? '');
$index = intval($_POST['index'] ?? -1);
if (strlen($md5) !== 32 || $index < 0 || $index > 20000 || empty($_FILES['file']['tmp_name'])) {
    http_response_code(400); echo json_encode(['ok'=>false]); exit;
}
$dir = '/tmp/fuploads/' . $md5;
@mkdir($dir, 0777, true);
if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir.'/chunk_'.$index)) {
    http_response_code(500); echo json_encode(['ok'=>false]); exit;
}
echo json_encode(['ok'=>true]);
