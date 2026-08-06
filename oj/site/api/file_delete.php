<?php
// 我的文件：删除
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$id = intval($_POST['id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM user_files WHERE id=? AND username=?"); $s->execute([$id, $me['username']]);
$f = $s->fetch();
if (!$f) { echo json_encode(['ok'=>false,'message'=>'文件不存在或无权操作']); exit; }
@unlink('/data/userfiles/' . $me['username'] . '/' . $f['stored_name']);
$pdo->prepare("DELETE FROM user_files WHERE id=?")->execute([$id]);
echo json_encode(['ok'=>true, 'message'=>'已删除']);
