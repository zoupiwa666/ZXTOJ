<?php
// AI 造数据工作台：发送用户消息（多轮修改）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
session_write_close();   // 释放 session 锁，避免高频轮询与其它请求串行排队
header('Content-Type: application/json; charset=utf-8');
$sid = preg_replace('/[^a-f0-9]/', '', $_POST['session_id'] ?? '');
$msg = trim($_POST['user_msg'] ?? '');
if ($sid === '' || $msg === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$dm = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8000';
$ch = curl_init($dm . '/chat/message');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>10,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['session_id'=>$sid,'user_msg'=>$msg], JSON_UNESCAPED_UNICODE)]);
$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($code !== 200) { echo json_encode(['ok'=>false,'message'=>'会话不存在或已过期，请刷新页面重新开始']); exit; }
echo $resp;
