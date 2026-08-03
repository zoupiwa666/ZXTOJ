<?php
// 聊天 - 添加好友
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
require_once __DIR__.'/../inc/chat_tables.php';
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$fid = intval($_POST['friend_id'] ?? 0);
if ($fid <= 0 || $fid == $me['id']) { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$s = $pdo->prepare("SELECT id FROM users WHERE id=?"); $s->execute([$fid]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'用户不存在']); exit; }
// 双向好友关系
$pdo->prepare("INSERT IGNORE INTO chat_friends (user_id, friend_id) VALUES (?,?),(?,?)")
    ->execute([$me['id'], $fid, $fid, $me['id']]);
echo json_encode(['ok'=>true,'message'=>'已添加为好友']);
