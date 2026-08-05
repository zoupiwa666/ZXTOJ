<?php
// 管理员审核题解（approve / reject）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');
$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
if (!in_array($action, ['approve','reject'])) { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$s = $pdo->prepare("SELECT * FROM articles WHERE id=?"); $s->execute([$id]);
$art = $s->fetch();
if (!$art) { echo json_encode(['ok'=>false,'message'=>'文章不存在']); exit; }
if ($art['is_solution'] != 1) { echo json_encode(['ok'=>false,'message'=>'这不是题解']); exit; }
$status = $action === 'approve' ? 'approved' : 'rejected';
$pdo->prepare("UPDATE articles SET solution_status=? WHERE id=?")->execute([$status, $id]);
echo json_encode(['ok'=>true, 'message'=>$action==='approve'?'已通过，题解已发布到题目页':'已拒绝']);
