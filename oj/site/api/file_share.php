<?php
// 分享下载：有 token 即可下载（无需登录）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/article_tables.php';
$token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token'] ?? '');
if (strlen($token) < 10) { http_response_code(404); die('链接无效'); }
$s = $pdo->prepare("SELECT * FROM user_files WHERE share_token=?"); $s->execute([$token]);
$f = $s->fetch();
if (!$f) { http_response_code(404); die('链接无效或已失效'); }
$path = '/data/userfiles/' . $f['username'] . '/' . $f['stored_name'];
if (!file_exists($path)) { http_response_code(404); die('文件已丢失'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($f['filename']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Accel-Redirect: /user_files/' . rawurlencode($f['username']) . '/' . rawurlencode($f['stored_name']));
exit;
