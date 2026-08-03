<?php
// 聊天 - 按用户名搜索用户
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
require_once __DIR__.'/../inc/chat_tables.php';
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$kw = trim($_POST['kw'] ?? '');
if ($kw === '') { echo json_encode(['users'=>[]]); exit; }
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username LIKE ? AND id <> ? LIMIT 20");
$stmt->execute(['%'.$kw.'%', $me['id']]);
$users = $stmt->fetchAll();
$fstmt = $pdo->prepare("SELECT friend_id FROM chat_friends WHERE user_id=?");
$fstmt->execute([$me['id']]);
$friends = array_flip(array_map('intval', $fstmt->fetchAll(PDO::FETCH_COLUMN)));
foreach ($users as &$u) { $u['is_friend'] = isset($friends[intval($u['id'])]); }
echo json_encode(['users'=>$users]);
