<?php
// 我的文件：生成/获取分享令牌（仅本人）
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
if (empty($f['share_token'])) {
    $token = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE user_files SET share_token=? WHERE id=?")->execute([$token, $id]);
    $f['share_token'] = $token;
}
echo json_encode(['ok'=>true, 'url'=>'api/file_share.php?token='.$f['share_token']]);
