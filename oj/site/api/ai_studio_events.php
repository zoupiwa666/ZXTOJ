<?php
// AI 造数据工作台：轮询会话事件（流式输出）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
session_write_close();   // 释放 session 锁，避免高频轮询与其它请求串行排队
header('Content-Type: application/json; charset=utf-8');
$sid = preg_replace('/[^a-f0-9]/', '', $_GET['session_id'] ?? '');
$since = max(intval($_GET['since'] ?? 0), 0);
if ($sid === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$dm = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8000';
$ch = curl_init($dm . "/chat/events?session_id=$sid&since=$since");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($code !== 200) { echo json_encode(['ok'=>false,'message'=>'会话不存在']); exit; }
echo $resp;
