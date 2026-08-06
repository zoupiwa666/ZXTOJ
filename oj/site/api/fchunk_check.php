<?php
// 我的文件 - 分片上传检查（断点续传）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$md5 = preg_replace('/[^a-f0-9]/i', '', $_POST['md5'] ?? ($raw['md5'] ?? ''));
if (strlen($md5) !== 32) { echo json_encode(['instant'=>false,'exist'=>[],'path'=>'']); exit; }
$dir = '/tmp/fuploads/' . $md5;
$exist = [];
if (is_dir($dir)) {
    foreach (glob($dir.'/chunk_*') as $f) $exist[] = (int)substr(basename($f), 6);
    sort($exist);
}
echo json_encode(['instant'=>false,'exist'=>$exist,'path'=>'']);
