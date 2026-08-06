<?php
// 聊天 - 获取与某好友的消息（最多10条，自动清掉10条之前的）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require_once __DIR__.'/../inc/sanitize.php';
requireLogin();
require_once __DIR__.'/../inc/chat_tables.php';
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$fid = intval($_GET['friend_id'] ?? 0);
if ($fid <= 0) { echo json_encode(['messages'=>[]]); exit; }
$stmt = $pdo->prepare(
  "SELECT id, sender_id, content, created_at FROM chat_messages
   WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
   ORDER BY id DESC LIMIT 10");
$stmt->execute([$me['id'], $fid, $fid, $me['id']]);
$msgs = array_reverse($stmt->fetchAll());
// 标记本会话消息已读
$pdo->prepare("UPDATE chat_messages SET is_read=1 WHERE receiver_id=? AND sender_id=? AND is_read=0")->execute([$me['id'], $fid]);
foreach ($msgs as &$m) { $m['content'] = render_mentions($m['content']); }
// 数据库里只保留最新 10 条，更早的直接删除
if (!empty($msgs)) {
    $keepId = $msgs[0]['id'];
    $del = $pdo->prepare(
      "DELETE FROM chat_messages
       WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND id < ?");
    $del->execute([$me['id'], $fid, $fid, $me['id'], $keepId]);
}
echo json_encode(['messages'=>$msgs]);
