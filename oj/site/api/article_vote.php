<?php
// 文章点赞/点踩（value: 1=赞, -1=踩, 0=取消）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$aid = intval($_POST['article_id'] ?? 0);
$val = intval($_POST['value'] ?? 0);
if ($aid <= 0 || !in_array($val, [1,-1,0])) { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$s = $pdo->prepare("SELECT id FROM articles WHERE id=?"); $s->execute([$aid]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'文章不存在']); exit; }

if ($val === 0) {
    $pdo->prepare("DELETE FROM article_likes WHERE article_id=? AND username=?")->execute([$aid, $me['username']]);
} else {
    $pdo->prepare("INSERT INTO article_likes (article_id, username, value) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE value=?")
        ->execute([$aid, $me['username'], $val, $val]);
}
// 返回统计
$st = $pdo->prepare("SELECT SUM(value=1) AS likes, SUM(value=-1) AS dislikes FROM article_likes WHERE article_id=?");
$st->execute([$aid]); $r = $st->fetch();
echo json_encode(['ok'=>true, 'likes'=>intval($r['likes']??0), 'dislikes'=>intval($r['dislikes']??0)]);
