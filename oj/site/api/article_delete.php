<?php
// 文章删除（作者或管理员）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$id = intval($_POST['id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM articles WHERE id=?"); $s->execute([$id]);
$art = $s->fetch();
if (!$art) { echo json_encode(['ok'=>false,'message'=>'文章不存在']); exit; }
if (!isAdmin() && $art['author'] !== $me['username']) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'无权删除']); exit; }
$pdo->prepare("DELETE FROM articles WHERE id=?")->execute([$id]);
echo json_encode(['ok'=>true,'message'=>'已删除']);
