<?php
// AI 自动造数据（异步）：校验参数 -> 落任务文件 -> nohup 后台执行 -> 立即返回 task_id
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['problem_id'] ?? '');
$key = trim($_POST['api_key'] ?? '');
$n = min(max(intval($_POST['count'] ?? 10), 1), 50);
$needCk = (($_POST['need_checker'] ?? '0') === '1');
$checkerReq = trim($_POST['checker_req'] ?? '');
$extraReq = trim($_POST['extra_req'] ?? '');
$stdCode = $_POST['std_code'] ?? '';
$stdLang = $_POST['std_lang'] ?? 'python3';
if (!in_array($stdLang, ['python3','c','cpp14','cpp17','cpp20'])) $stdLang = 'python3';

if ($pid === '') { echo json_encode(['ok'=>false,'message'=>'缺少题目编号']); exit; }
$s = $pdo->prepare("SELECT problem_id FROM problems WHERE problem_id=?"); $s->execute([$pid]);
if (!$s->fetch()) { echo json_encode(['ok'=>false,'message'=>'题目不存在']); exit; }

// API Key：优先用传入的，否则读已保存的
$keyFile = '/data/.deepseek_key';
if ($key === '' && file_exists($keyFile)) $key = trim(file_get_contents($keyFile));
if ($key === '') { echo json_encode(['ok'=>false,'message'=>'请输入 DeepSeek API Key']); exit; }
if (!empty($_POST['save_key'])) @file_put_contents($keyFile, $key);

$taskId = bin2hex(random_bytes(6));
@mkdir('/tmp/ai_gen', 0777, true);

// 任务文件（含 API Key，权限 0600，任务结束即删）
$taskFile = "/tmp/ai_gen/$taskId.json";
file_put_contents($taskFile, json_encode([
    'task_id' => $taskId, 'pid' => $pid, 'api_key' => $key,
    'count' => $n, 'need_checker' => $needCk, 'checker_req' => $checkerReq, 'extra_req' => $extraReq,
    'std_code' => $stdCode, 'std_lang' => $stdLang,
    'created' => time(),
], JSON_UNESCAPED_UNICODE));
chmod($taskFile, 0600);

// 状态文件
file_put_contents("/tmp/ai_gen/$taskId.status", json_encode(
    ['status'=>'queued', 'step'=>'排队中', 'progress'=>0, 'message'=>'任务已创建，等待执行'],
    JSON_UNESCAPED_UNICODE
));

// 后台执行（不受 nginx/PHP-FPM 超时限制）
$phpBin = '/usr/local/bin/php'; // php-fpm 下 PHP_BINARY 指向 php-fpm 自身，必须用 CLI 路径
exec('setsid nohup ' . escapeshellarg($phpBin) . ' ' . escapeshellarg(__DIR__ . '/ai_gen_task.php') . ' ' . escapeshellarg($taskFile) . ' > /dev/null 2>&1 &');

echo json_encode(['ok'=>true, 'task_id'=>$taskId, 'message'=>'AI 造数据任务已启动']);
