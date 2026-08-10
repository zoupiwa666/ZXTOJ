<?php
// AI 造数据工作台：应用已生成数据到数据库（同步 problem_testcases / problems）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['problem_id'] ?? '');
if ($pid === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$dir = "/data/problems/$pid";
if (!is_dir($dir)) { echo json_encode(['ok'=>false,'message'=>'数据目录不存在']); exit; }
$n = 0;
foreach (glob("$dir/*.in") as $f) { $n = max($n, intval(basename($f, '.in'))); }
if ($n === 0) { echo json_encode(['ok'=>false,'message'=>'没有测试数据，请先生成']); exit; }
$pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
$scoreEach = round(100 / $n, 2);
$stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score,file_path) VALUES (?,?,?,?,?,?)");
for ($i = 1; $i <= $n; $i++) $stmt->execute([$pid, $i, '', '', $scoreEach, "$dir/$i"]);
$p = $pdo->prepare("SELECT time_limit, memory_limit FROM problems WHERE problem_id=?"); $p->execute([$pid]);
$prob = $p->fetch();
if ($prob) $pdo->prepare("UPDATE problems SET time_limit=?, memory_limit=? WHERE problem_id=?")
    ->execute([floatval($prob['time_limit']), intval($prob['memory_limit']), $pid]);
echo json_encode(['ok'=>true, 'message'=>"已应用 $n 个测试点到题目 $pid"]);
