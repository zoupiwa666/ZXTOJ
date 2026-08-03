<?php
// 聊天 - 发送消息（单条最多1.5KB，发送后数据库只留最新20条）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
require_once __DIR__.'/../inc/chat_tables.php';
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$fid = intval($_POST['friend_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
if ($fid <= 0 || $content === '') { echo json_encode(['ok'=>false,'message'=>'消息不能为空']); exit; }
if (strlen($content) > 1500) { echo json_encode(['ok'=>false,'message'=>'单条消息不能超过1.5KB']); exit; }
$s = $pdo->prepare("SELECT 1 FROM chat_friends WHERE user_id=? AND friend_id=?");
$s->execute([$me['id'], $fid]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'你们还不是好友']); exit; }
$pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content) VALUES (?,?,?)")
    ->execute([$me['id'], $fid, $content]);
// 清理：只保留最新 20 条
$stmt = $pdo->prepare(
  "SELECT id FROM chat_messages
   WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
   ORDER BY id DESC LIMIT 1000 OFFSET 20");
$stmt->execute([$me['id'], $fid, $fid, $me['id']]);
$oldIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($oldIds as $oid) { $pdo->prepare("DELETE FROM chat_messages WHERE id=?")->execute([$oid]); }
echo json_encode(['ok'=>true]);
