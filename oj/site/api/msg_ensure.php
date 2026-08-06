<?php
// 确保 Message 用户/好友关系（供前端调用）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../message/functions.php';
requireLogin();
msg_ensure();
echo json_encode(['ok'=>true]);
