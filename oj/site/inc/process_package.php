<?php
if ($argc < 3) exit(1);
$filePath = $argv[1];
$pid = $argv[2];

require_once __DIR__.'/config.php';
require_once __DIR__.'/zip_parser.php';

$info = parsePackageLocally($filePath, basename($filePath));
if (empty($info['error'])) {
    $pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
    $stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score) VALUES (?,?,?,?,?)");
    foreach($info['test_cases'] as $i=>$tc) {
        $stmt->execute([$pid, $i+1, $tc['input'], $tc['expected_output'], floatval($tc['score'])]);
    }
    if(isset($info['time_limit'])) $pdo->prepare("UPDATE problems SET time_limit=? WHERE problem_id=?")->execute([floatval($info['time_limit']), $pid]);
    if(isset($info['memory_limit'])) $pdo->prepare("UPDATE problems SET memory_limit=? WHERE problem_id=?")->execute([intval($info['memory_limit']), $pid]);
}
// 清理
unlink($filePath);
