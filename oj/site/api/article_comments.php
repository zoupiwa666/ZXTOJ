<?php
// 文章评论列表（分页，一页8条）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require_once __DIR__.'/../inc/sanitize.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$aid = intval($_GET['article_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$per = 8;
$s = $pdo->prepare("SELECT id FROM articles WHERE id=?"); $s->execute([$aid]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'文章不存在']); exit; }

$cnt = $pdo->prepare("SELECT COUNT(*) FROM article_comments WHERE article_id=?");
$cnt->execute([$aid]); $total = intval($cnt->fetchColumn());
$totalPages = max(1, intval(ceil($total / $per)));
$page = min($page, $totalPages);
$off = ($page - 1) * $per;

$st = $pdo->prepare("SELECT c.*, u.avatar, u.role, u.tag FROM article_comments c LEFT JOIN users u ON u.username = c.username COLLATE utf8mb4_uca1400_ai_ci WHERE c.article_id=? ORDER BY c.id DESC LIMIT $per OFFSET $off");
$st->execute([$aid]);
$cmts = $st->fetchAll();
foreach ($cmts as &$cm) { $cm['content'] = render_mentions($cm['content']); }
echo json_encode([
    'comments' => $cmts,
    'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
]);
