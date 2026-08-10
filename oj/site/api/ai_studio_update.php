<?php
// AI 造数据工作台：会话中调整参数（转发 datamaker /chat/update）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
session_write_close();
header('Content-Type: application/json; charset=utf-8');
$sid = preg_replace('/[^a-f0-9]/', '', $_POST['session_id'] ?? '');
if ($sid === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$payload = ['session_id'=>$sid];
foreach (['count','need_checker','checker_req','extra_req','std_code','std_lang','api_key'] as $k) {
    if (isset($_POST[$k])) $payload[$k] = $_POST[$k];
}
if (count($payload) <= 1) { echo json_encode(['ok'=>false,'message'=>'没有要更新的参数']); exit; }
$dm = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8000';
$ch = curl_init($dm . '/chat/update');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>10,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE)]);
$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($code !== 200) { echo json_encode(['ok'=>false,'message'=>'会话不存在或已过期，请刷新重新开始']); exit; }
echo $resp;
