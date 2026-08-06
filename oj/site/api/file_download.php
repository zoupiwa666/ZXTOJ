<?php
// 我的文件：下载（仅本人）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
$me = currentUser();
$id = intval($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM user_files WHERE id=? AND username=?"); $s->execute([$id, $me['username']]);
$f = $s->fetch();
if (!$f) { http_response_code(404); die('文件不存在'); }
$path = '/data/userfiles/' . $me['username'] . '/' . $f['stored_name'];
if (!file_exists($path)) { http_response_code(404); die('文件已丢失'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($f['filename']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
