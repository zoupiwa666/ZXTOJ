<?php
// 我的文件：上传（每个用户总空间 256MB）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$MAX_TOTAL = 256 * 1024 * 1024; // 256MB

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'message'=>'未收到文件']); exit;
}
$f = $_FILES['file'];
$name = basename($f['name']);
$size = intval($f['size']);
if ($size <= 0) { echo json_encode(['ok'=>false,'message'=>'空文件']); exit; }

$used = $pdo->prepare("SELECT COALESCE(SUM(size),0) FROM user_files WHERE username=?");
$used->execute([$me['username']]);
$used = intval($used->fetchColumn());
if ($used + $size > $MAX_TOTAL) {
    echo json_encode(['ok'=>false,'message'=>'总空间超限：已用 '.round($used/1048576).'MB，限制 256MB']); exit;
}

$dir = '/data/userfiles/' . $me['username'];
@mkdir($dir, 0777, true);
@chmod($dir, 0777);
if (!is_dir($dir) || !is_writable($dir)) {
    echo json_encode(['ok'=>false,'message'=>'文件目录不可写，请检查 /data/userfiles 权限']); exit;
}
$stored = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
if (!move_uploaded_file($f['tmp_name'], "$dir/$stored")) {
    echo json_encode(['ok'=>false,'message'=>'文件保存失败，请检查目录权限']); exit;
}
$pdo->prepare("INSERT INTO user_files (username, filename, stored_name, size) VALUES (?,?,?,?)")
    ->execute([$me['username'], $name, $stored, $size]);
echo json_encode(['ok'=>true, 'message'=>'上传成功', 'used'=>$used+$size, 'max'=>$MAX_TOTAL]);
