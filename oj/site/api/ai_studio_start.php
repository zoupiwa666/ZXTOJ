<?php
// AI 造数据工作台：创建会话（题目信息从 DB 读取，配置透传 datamaker）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
session_write_close();   // 释放 session 锁，避免高频轮询与其它请求串行排队
header('Content-Type: application/json; charset=utf-8');

$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['problem_id'] ?? '');
$key = trim($_POST['api_key'] ?? '');
if ($pid === '') { echo json_encode(['ok'=>false,'message'=>'缺少题目编号']); exit; }
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?"); $s->execute([$pid]);
$prob = $s->fetch();
if (!$prob) { echo json_encode(['ok'=>false,'message'=>'题目不存在']); exit; }

$keyFile = '/data/.deepseek_key';
if ($key === '' && file_exists($keyFile)) $key = trim(file_get_contents($keyFile));
if ($key === '') { echo json_encode(['ok'=>false,'message'=>'请输入 DeepSeek API Key']); exit; }
if (!empty($_POST['save_key'])) @file_put_contents($keyFile, $key);

$stdLang = $_POST['std_lang'] ?? 'python3';
if (!in_array($stdLang, ['python3','c','cpp14','cpp17','cpp20'])) $stdLang = 'python3';

$payload = [
    'problem_id' => $pid, 'api_key' => $key,
    'count' => min(max(intval($_POST['count'] ?? 10), 1), 50),
    'need_checker' => (($_POST['need_checker'] ?? '0') === '1'),
    'checker_req' => trim($_POST['checker_req'] ?? ''),
    'extra_req' => trim($_POST['extra_req'] ?? ''),
    'std_code' => $_POST['std_code'] ?? '',
    'std_lang' => $stdLang,
    'title' => $prob['title'], 'description' => $prob['description'] ?? '',
    'input_format' => $prob['input_format'] ?? '', 'output_format' => $prob['output_format'] ?? '',
    'hints' => $prob['hints'] ?? '',
    'time_limit' => floatval($prob['time_limit']), 'memory_limit' => intval($prob['memory_limit']),
];
$dm = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8000';
$ch = curl_init($dm . '/chat/start');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>20,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE)]);
$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($code !== 200) {
    $d = json_decode($resp, true);
    echo json_encode(['ok'=>false,'message'=>$d['detail'] ?? $resp ?? "datamaker 调用失败($code)"]); exit;
}
echo $resp;
