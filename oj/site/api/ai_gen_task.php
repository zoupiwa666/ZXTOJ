<?php
// AI 自动造数据 - 后台任务执行器（由 ai_gen.php 异步启动）
// 职责：组请求体 -> 调 zxt-datamaker 容器执行造数据 -> 同步 DB -> 更新状态
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$taskFile = $argv[1] ?? '';
if (!$taskFile || !file_exists($taskFile)) { fwrite(STDERR, "no task file\n"); exit(1); }
$task = json_decode(file_get_contents($taskFile), true);
if (!$task) { fwrite(STDERR, "bad task json\n"); exit(1); }

$taskId     = $task['task_id'];
$pid        = $task['pid'];
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

// ---------- 2. 调 zxt-datamaker ----------
setStatus('running', '调用 zxt-datamaker 造数据', 10, '已提交给独立造数据容器...');

$datamaker = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8000';
$payload = [
    'problem_id'    => $pid,
    'api_key'       => $task['api_key'],
    'count'         => intval($task['count']),
    'need_checker'  => !empty($task['need_checker']),
    'checker_req'   => $task['checker_req'] ?? '',
    'extra_req'     => $task['extra_req'] ?? '',
    'std_code'      => $task['std_code'] ?? '',
    'std_lang'      => $task['std_lang'] ?? 'python3',
    'title'         => $prob['title'],
    'description'   => $prob['description'] ?? '',
    'input_format'  => $prob['input_format'] ?? '',
    'output_format' => $prob['output_format'] ?? '',
    'hints'         => $prob['hints'] ?? '',
    'time_limit'    => floatval($prob['time_limit']),
    'memory_limit'  => intval($prob['memory_limit']),
];

$ch = curl_init($datamaker . '/gen_data');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    $msg = '造数据容器调用失败';
    if ($resp) {
        $d = json_decode($resp, true);
        $msg = $d['detail'] ?? $d['message'] ?? $resp;
    } elseif ($curlErr) {
        $msg .= "：$curlErr（检查 zxt-datamaker 容器是否已启动）";
    } else {
        $msg .= "（HTTP $httpCode）";
    }
    setStatus('error', '失败', 0, $msg);
    exit;
}
$d = json_decode($resp, true);
if (empty($d['ok'])) { setStatus('error', '失败', 0, $d['message'] ?? '造数据失败'); exit; }

// ---------- 3. 同步数据库 ----------
try {
    $n = intval($d['n'] ?? 0);
    if ($n > 0) {
        $scoreEach = round(100 / $n, 2);
        $pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
        $stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score,file_path) VALUES (?,?,?,?,?,?)");
        for ($i = 1; $i <= $n; $i++) {
            $stmt->execute([$pid, $i, '', '', $scoreEach, "/data/problems/$pid/$i"]);
        }
        $pdo->prepare("UPDATE problems SET time_limit=?, memory_limit=? WHERE problem_id=?")
            ->execute([floatval($prob['time_limit']), intval($prob['memory_limit']), $pid]);
    }
} catch (Exception $e) {
    file_put_contents("/tmp/ai_gen/$taskId.db_err", $e->getMessage());
}

// ---------- 4. 清理并完成 ----------
@unlink($taskFile);   // 任务文件含 API Key，用后即删
setStatus('done', '完成', 100, $d['message']);
