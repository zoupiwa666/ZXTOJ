<?php
// 用户名片悬停卡片数据（头像 + 格言）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$name = trim($_GET['name'] ?? '');
if ($name === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$s = $pdo->prepare("SELECT username, avatar, motto, role FROM users WHERE username=?");
$s->execute([$name]);
$u = $s->fetch();
if (!$u) { echo json_encode(['ok'=>false,'message'=>'用户不存在']); exit; }
echo json_encode(['ok'=>true, 'username'=>$u['username'], 'avatar'=>$u['avatar'], 'motto'=>$u['motto'] ?? '', 'role'=>$u['role']]);
