<?php
// 发表评论（Markdown，字体大小已钳制）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/sanitize.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$aid = intval($_POST['article_id'] ?? 0);
$content = sanitize_html(sanitize_text(trim($_POST['content'] ?? ''), 5000));
if ($aid <= 0 || $content === '') { echo json_encode(['ok'=>false,'message'=>'评论内容不能为空']); exit; }
$s = $pdo->prepare("SELECT id FROM articles WHERE id=?"); $s->execute([$aid]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'文章不存在']); exit; }
$pdo->prepare("INSERT INTO article_comments (article_id, username, content) VALUES (?,?,?)")
    ->execute([$aid, $me['username'], $content]);
echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'message'=>'评论成功']);
