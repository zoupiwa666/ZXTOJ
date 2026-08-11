<?php
// AI 助手：应用 AI 在工作目录生成的数据到数据库（先让 datamaker 复制落盘，再同步 problem_testcases）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
session_write_close();
header('Content-Type: application/json; charset=utf-8');
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['problem_id'] ?? '');
$sid = preg_replace('/[^a-f0-9]/', '', $_POST['session_id'] ?? '');
if ($pid === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }

$dm = getenv('DATAMAKER_URL') ?: 'http://zxt-datamaker:8000';
if ($sid !== '') {
    // 优先：让 datamaker 把会话工作目录数据复制落盘
    $ch = curl_init($dm . '/chat/apply?session_id=' . $sid);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>15]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200) {
        $d = json_decode($resp, true);
        if (!empty($d['ok'])) {
            // 同步数据库
            $n = intval($d['n']);
            $pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
            $scoreEach = round(100 / $n, 2);
            $stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score,file_path) VALUES (?,?,?,?,?,?)");
            for ($i = 1; $i <= $n; $i++) $stmt->execute([$pid, $i, '', '', $scoreEach, "/data/problems/$pid/$i"]);
            $p = $pdo->prepare("SELECT time_limit, memory_limit FROM problems WHERE problem_id=?"); $p->execute([$pid]);
            $prob = $p->fetch();
            if ($prob) $pdo->prepare("UPDATE problems SET time_limit=?, memory_limit=? WHERE problem_id=?")
                ->execute([floatval($prob['time_limit']), intval($prob['memory_limit']), $pid]);
            echo json_encode(['ok'=>true, 'message'=>"已应用 {$n} 个测试点到题目 {$pid}"]); exit;
        }
        // datamaker 说没数据则 fallthrough 检查本地目录
    }
}
// 兜底：本地 /data/problems 已有数据
$dir = "/data/problems/$pid";
$n = 0;
if (is_dir($dir)) foreach (glob("$dir/*.in") as $f) $n = max($n, intval(basename($f, '.in')));
if ($n === 0) { echo json_encode(['ok'=>false,'message'=>'没有测试数据。若已让 AI 生成数据，请按 Ctrl+F5 刷新页面后重新点击「应用数据」；或先让 AI 调用 run_generator 生成']); exit; }
$pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
$scoreEach = round(100 / $n, 2);
$stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score,file_path) VALUES (?,?,?,?,?,?)");
for ($i = 1; $i <= $n; $i++) $stmt->execute([$pid, $i, '', '', $scoreEach, "$dir/$i"]);
echo json_encode(['ok'=>true, 'message'=>"已应用 $n 个测试点到题目 $pid"]);
