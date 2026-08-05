<?php
// 管理员：为用户设置标签（只能设置权限小于自己的用户，或自己）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/sanitize.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$me = currentUser();
$target = trim($_POST['username'] ?? '');
$tag = sanitize_name($_POST['tag'] ?? '', 5);   // 纯文字，最多5字
$roleLevel = ['super_admin'=>3, 'admin'=>2, 'user'=>1];
$myLevel = $roleLevel[$me['role']] ?? 1;

if ($target === '') { echo json_encode(['ok'=>false,'message'=>'缺少用户名']); exit; }
$s = $pdo->prepare("SELECT id, role, tag FROM users WHERE username=?"); $s->execute([$target]);
$tu = $s->fetch();
if (!$tu) { echo json_encode(['ok'=>false,'message'=>'用户不存在']); exit; }

// 权限检查：目标权限必须小于自己（自己除外）
$targetLevel = $roleLevel[$tu['role']] ?? 1;
if ($target !== $me['username'] && $targetLevel >= $myLevel) {
    echo json_encode(['ok'=>false,'message'=>'只能给权限小于自己的用户设置标签']); exit;
}

// 清空标签（空字符串）或设置
$pdo->prepare("UPDATE users SET tag=? WHERE id=?")->execute([$tag, $tu['id']]);
echo json_encode(['ok'=>true, 'message'=>($tag===''?'已清除标签':'标签已设置为「'.$tag.'」'), 'tag'=>$tag]);
