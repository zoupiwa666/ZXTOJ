<?php
// AI 自动造数据 - 后台任务执行器（由 ai_gen.php 通过 nohup 异步启动）
// 职责：读任务JSON -> 转发给 zxt-datamaker 容器（调DeepSeek+运行生成器/标程+写盘）
//       -> 同步 DB -> 更新状态文件
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$taskFile = $argv[1] ?? '';
if (!$taskFile || !file_exists($taskFile)) { fwrite(STDERR, "no task file\n"); exit(1); }
$task = json_decode(file_get_contents($taskFile), true);
if (!$task) { fwrite(STDERR, "bad task json\n"); exit(1); }

$taskId   = $task['task_id'];
$pid      = $task['pid'];
$apiKey   = $task['api_key'];
$n        = intval($task['count'] ?? 10);
$needCk   = !empty($task['need_checker']);
$ckReq    = trim($task['checker_req'] ?? '');
$stdCode  = $task['std_code'] ?? '';
$stdLang  = in_array($task['std_lang'] ?? 'python3', ['python3','c','cpp14','cpp17','cpp20']) ? $task['std_lang'] : 'python3';
$statusFile = "/tmp/ai_gen/$taskId.status";

function setStatus($status, $step, $progress, $message) {
    global $statusFile;
    @file_put_contents($statusFile, json_encode(
        ['status'=>$status, 'step'=>$step, 'progress'=>$progress, 'message'=>$message],
        JSON_UNESCAPED_UNICODE
    ));
}

require __DIR__.'/../inc/config.php';   // 提供 $pdo

// ---------- 1. 读题目信息 ----------
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?"); $s->execute([$pid]);
$prob = $s->fetch();
if (!$prob) { setStatus('error', '失败', 0, '题目不存在'); exit; }

// ---------- 2. 转发给 zxt-datamaker ----------
$dmUrl = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8001';
setStatus('running', 'zxt-datamaker 造数据中', 10, '已交给 zxt-datamaker：DeepSeek 生成代码 + 容器内运行...');

$payload = [
    'api_key' => $apiKey,
    'problem' => [
        'problem_id'    => $pid,
        'title'         => $prob['title'],
        'description'   => $prob['description'],
        'input_format'  => $prob['input_format'],
        'output_format' => $prob['output_format'],
        'hints'         => $prob['hints'],
        'time_limit'    => floatval($prob['time_limit']),
        'memory_limit'  => intval($prob['memory_limit']),
    ],
    'count'       => $n,
    'std_code'    => $stdCode,
    'std_lang'    => $stdLang,
    'need_checker'=> $needCk,
    'checker_req' => $ckReq,
];

$ch = curl_init($dmUrl . '/gen_data');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 600,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

$d = json_decode($resp, true);
if ($http !== 200 || empty($d['ok'])) {
    $msg = is_array($d) && !empty($d['detail']) ? $d['detail'] : ($d['message'] ?? '');
    $msg = $msg !== '' ? $msg : ("zxt-datamaker 调用失败 (HTTP $http" . ($curlErr !== '' ? ": $curlErr" : '') . ")");
    setStatus('error', '造数据失败', 0, $msg);
    exit;
}

$okN = intval($d['n'] ?? 0);
if ($okN <= 0) { setStatus('error', '造数据失败', 0, '未产出有效测试数据'); exit; }

// ---------- 3. 同步数据库 ----------
try {
    $pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
    $stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score,file_path) VALUES (?,?,?,?,?,?)");
    $scoreEach = round(100 / $okN, 2);
    for ($i = 1; $i <= $okN; $i++) {
        $stmt->execute([$pid, $i, '', '', $scoreEach, "/data/problems/$pid/$i"]);
    }
    $pdo->prepare("UPDATE problems SET time_limit=?, memory_limit=? WHERE problem_id=?")
        ->execute([floatval($prob['time_limit']), intval($prob['memory_limit']), $pid]);
} catch (Exception $e) {
    file_put_contents("/tmp/ai_gen/$taskId.db_err", $e->getMessage());
}

// ---------- 4. 完成 ----------
@unlink($taskFile);   // 任务文件含 API Key，用后即删
$stdSrc = ($stdCode !== '') ? '用户 std' : 'DeepSeek 生成 std';
$msg = "AI 造数据完成：成功 {$okN}/{$n} 组（{$stdSrc}）"
     . (!empty($d['checker']) ? '，已生成 checker' : '')
     . (!empty($d['errors']) ? '；失败 ' . count($d['errors']) . ' 组：' . implode('; ', array_slice($d['errors'], 0, 3)) : '');
setStatus('done', '完成', 100, $msg);
