<?php
// AI 造数据工作台：一次性获取会话完整历史
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
session_write_close();
header('Content-Type: application/json; charset=utf-8');
$sid = preg_replace('/[^a-f0-9]/', '', $_GET['session_id'] ?? '');
if ($sid === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$dm = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8000';
$ch = curl_init($dm . "/chat/history?session_id=$sid");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($code !== 200) { echo json_encode(['ok'=>false,'message'=>'会话不存在或已过期']); exit; }
echo $resp;
