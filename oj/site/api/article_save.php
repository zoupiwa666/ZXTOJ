<?php
// 文章保存（创建/更新）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/sanitize.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$me = currentUser();
$perm = article_perm($me['username']);
$isAdmin = isAdmin();
$id = intval($_POST['id'] ?? 0);
$title = sanitize_name($_POST['title'] ?? '', 200);
$content = sanitize_text($_POST['content'] ?? '', 100000);

if ($id > 0) {
    // 更新
    $s = $pdo->prepare("SELECT * FROM articles WHERE id=?"); $s->execute([$id]);
    $art = $s->fetch();
    if (!$art) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'文章不存在']); exit; }
    if (!article_can_edit($art, $me['username'])) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'无修改权限']); exit; }
    if ($title === '') { echo json_encode(['ok'=>false,'message'=>'标题不能为空']); exit; }
    // 仅管理员能设置公告状态
    $isAnn = $isAdmin ? (($_POST['is_announcement'] ?? 0) ? 1 : 0) : $art['is_announcement'];
    $isPub = ($_POST['is_public'] ?? 0) ? 1 : 0;
    $pdo->prepare("UPDATE articles SET title=?, content=?, is_public=?, is_announcement=? WHERE id=?")
        ->execute([$title, $content, $isPub, $isAnn, $id]);
    echo json_encode(['ok'=>true, 'id'=>$id, 'message'=>'已保存']);
} else {
    // 创建（默认私密 is_public=0）
    if (!$isAdmin && $perm['can_publish'] != 1) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'你没有被授权发布文章']); exit; }
    if ($title === '') { echo json_encode(['ok'=>false,'message'=>'标题不能为空']); exit; }
    $isAnn = $isAdmin ? (($_POST['is_announcement'] ?? 0) ? 1 : 0) : 0;
    $isPub = ($_POST['is_public'] ?? 0) ? 1 : 0;
    $pdo->prepare("INSERT INTO articles (title, content, author, is_announcement, is_public) VALUES (?,?,?,?,?)")
        ->execute([$title, $content, $me['username'], $isAnn, $isPub]);
    echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'message'=>'已发布（默认私密）']);
}
