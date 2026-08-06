<?php
// 未读消息数（用于右上角红点）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
// 确保 is_read 字段存在
try { $pdo->exec("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
$me = currentUser();
$s = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE receiver_id=? AND is_read=0");
$s->execute([$me['id']]);
echo json_encode(['unread'=>(int)$s->fetchColumn()]);
