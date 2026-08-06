<?php
// =============================================
//  Message 系统消息模块
//  负责：Message 用户、好友关系、系统消息发送、未读字段
// =============================================

// 确保 Message 用户存在、is_read 字段存在、所有用户都是 Message 的好友
function msg_ensure(): int {
    global $pdo;
    // 1) Message 用户（随机密码不可登录，防冒充）
    $s = $pdo->prepare("SELECT id FROM users WHERE username='Message'");
    $s->execute();
    $mid = $s->fetchColumn();
    if (!$mid) {
        $pw = bin2hex(random_bytes(16));
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('Message', ?, 'user')")->execute([$hash]);
        $mid = $pdo->lastInsertId();
    }
    // 2) is_read 字段（旧消息默认已读，避免误报红点）
    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
    $pdo->exec("UPDATE chat_messages SET is_read=1 WHERE is_read=0 AND sender_id <> " . intval($mid));
    // 3) 所有用户与 Message 互为好友
    $users = $pdo->query("SELECT id FROM users WHERE id <> " . intval($mid))->fetchAll();
    foreach ($users as $u) {
        $pdo->prepare("INSERT IGNORE INTO chat_friends (user_id, friend_id) VALUES (?,?),(?,?)")
            ->execute([$u['id'], $mid, $mid, $u['id']]);
    }
    return (int)$mid;
}

// 给指定用户发送系统消息（sender=Message）
function msg_send(string $toUsername, string $content): bool {
    global $pdo;
    $mid = msg_ensure();
    $t = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $t->execute([$toUsername]);
    $tid = $t->fetchColumn();
    if (!$tid) return false;
    $pdo->prepare("INSERT IGNORE INTO chat_friends (user_id, friend_id) VALUES (?,?),(?,?)")
        ->execute([$tid, $mid, $mid, $tid]);
    $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, is_read) VALUES (?,?,?,0)")
        ->execute([$mid, $tid, $content]);
    return true;
}

// 批量发送系统消息（逗号/换行分隔用户名）
function msg_send_batch(string $usernames, string $content): array {
    $names = preg_split('/[\s,，、;；]+/', trim($usernames));
    $ok = 0; $fail = 0;
    foreach ($names as $n) {
        if ($n === '') continue;
        msg_send($n, $content) ? $ok++ : $fail++;
    }
    return ['ok'=>$ok, 'fail'=>$fail];
}
