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
$isSol = ($_POST['is_solution'] ?? 0) ? 1 : 0;
$solPid = sanitize_name($_POST['solution_problem'] ?? '', 20);
if ($isSol) {
    $ck = $pdo->prepare("SELECT problem_id FROM problems WHERE problem_id=?"); $ck->execute([$solPid]);
    if (!$ck->fetch()) { echo json_encode(['ok'=>false,'message'=>'题目不存在']); exit; }
}
// 普通用户内容上限 100KB，管理员放宽到 1MB
if ($isAdmin) {
    $content = sanitize_text($_POST['content'] ?? '', 1024*1024);
} else {
    if (strlen($_POST['content'] ?? '') > 100*1024) {
        echo json_encode(['ok'=>false,'message'=>'文章内容不能超过100KB']); exit;
    }
    $content = sanitize_text($_POST['content'] ?? '', 100*1024);
}

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
    // 题解状态：管理员直接通过；普通用户保持待审核
    $solStatus = $art['solution_status'] ?? null;
    if ($isSol && $isAdmin) $solStatus = 'approved';
    elseif ($isSol && !$isAdmin) $solStatus = $solStatus ?: 'pending';
    if (!$isSol) { $solPid = null; $solStatus = null; }
    $pdo->prepare("UPDATE articles SET title=?, content=?, is_public=?, is_announcement=?, is_solution=?, solution_problem=?, solution_status=? WHERE id=?")
        ->execute([$title, $content, $isPub, $isAnn, $isSol, $solPid, $solStatus, $id]);
    echo json_encode(['ok'=>true, 'id'=>$id, 'message'=>'已保存']);
} else {
    // 创建（默认私密 is_public=0）
    if (!$isAdmin && $perm['can_publish'] != 1) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'你没有被授权发布文章']); exit; }
    if ($title === '') { echo json_encode(['ok'=>false,'message'=>'标题不能为空']); exit; }
    $isAnn = $isAdmin ? (($_POST['is_announcement'] ?? 0) ? 1 : 0) : 0;
    $isPub = ($_POST['is_public'] ?? 0) ? 1 : 0;
    // 题解：管理员直接 approved，普通用户 pending 待审核
    $solStatus = $isSol ? ($isAdmin ? 'approved' : 'pending') : null;
    $pdo->prepare("INSERT INTO articles (title, content, author, is_announcement, is_public, is_solution, solution_problem, solution_status) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$title, $content, $me['username'], $isAnn, $isPub, $isSol, $solPid, $solStatus]);
    echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'message'=>'已发布（默认私密）']);
}
