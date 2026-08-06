<?php
// 管理员：修改用户文章权限
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$username = trim($_POST['username'] ?? '');
$canView = intval($_POST['can_view'] ?? 0) ? 1 : 0;
$canPub  = intval($_POST['can_publish'] ?? 0) ? 1 : 0;
$canEdit = intval($_POST['can_edit'] ?? 0) ? 1 : 0;
if ($username === '') { echo json_encode(['ok'=>false,'message'=>'缺少用户名']); exit; }

// 用户必须存在
$s = $pdo->prepare("SELECT id FROM users WHERE username=?"); $s->execute([$username]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'用户不存在']); exit; }

$pdo->prepare("INSERT INTO article_permissions (username, can_view, can_publish, can_edit, updated_by) VALUES (?,?,?,?,?)
               ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_publish=VALUES(can_publish), can_edit=VALUES(can_edit), updated_by=VALUES(updated_by)")
    ->execute([$username, $canView, $canPub, $canEdit, currentUser()['username']]);
// Message 通知：权限变化
try {
    require_once __DIR__.'/../message/functions.php';
    msg_send($username, "你的文章权限已更新：查看=".($canView?'允许':'禁止')."，发布=".($canPub?'允许':'禁止')."，修改=".($canEdit?'允许':'禁止')."。");
} catch (Exception $e) {}
echo json_encode(['ok'=>true, 'message'=>"已更新 $username 的文章权限"]);
