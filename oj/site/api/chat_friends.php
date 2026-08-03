<?php
// 聊天 - 好友列表（含最后一条消息预览）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
require_once __DIR__.'/../inc/chat_tables.php';
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$stmt = $pdo->prepare(
  "SELECT u.id, u.username, u.avatar, u.role,
     (SELECT m.content FROM chat_messages m
       WHERE (m.sender_id=cf.friend_id AND m.receiver_id=cf.user_id)
          OR (m.sender_id=cf.user_id AND m.receiver_id=cf.friend_id)
       ORDER BY m.id DESC LIMIT 1) AS last_msg,
     (SELECT m.created_at FROM chat_messages m
       WHERE (m.sender_id=cf.friend_id AND m.receiver_id=cf.user_id)
          OR (m.sender_id=cf.user_id AND m.receiver_id=cf.friend_id)
       ORDER BY m.id DESC LIMIT 1) AS last_time
   FROM chat_friends cf JOIN users u ON u.id = cf.friend_id
   WHERE cf.user_id = ? ORDER BY last_time DESC");
$stmt->execute([$me['id']]);
echo json_encode(['friends'=>$stmt->fetchAll()]);
