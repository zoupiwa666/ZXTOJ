<?php
// 分片上传 - 检查已存在的分片（支持断点续传）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$md5 = preg_replace('/[^a-f0-9]/i', '', $_POST['md5'] ?? ($raw['md5'] ?? ''));
$name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_POST['name'] ?? ($raw['name'] ?? 'package.zip')));
if (strlen($md5) !== 32) { echo json_encode(['instant'=>false,'exist'=>[],'path'=>'']); exit; }

$dir = '/tmp/oj_uploads/'.$md5;
$merged = '/tmp/oj_packages/'.$md5.'_'.$name;
if (file_exists($merged)) {
    echo json_encode(['instant'=>true,'exist'=>[],'path'=>$merged,'size'=>filesize($merged)]); exit;
}
$exist = [];
if (is_dir($dir)) {
    foreach (glob($dir.'/chunk_*') as $f) {
        $exist[] = (int)substr(basename($f), 6);
    }
}
sort($exist);
echo json_encode(['instant'=>false,'exist'=>$exist,'path'=>'']);
