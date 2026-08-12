<?php
// 保存题目（新建/编辑，供客户端）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require_once __DIR__.'/../inc/zip_parser.php';   // simpleYaml（config 同步用）
requireRole('admin');
session_write_close();
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['ok'=>false,'message'=>'Invalid JSON']); exit; }
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['problem_id'] ?? '');
$title = trim($data['title'] ?? ''); 
if ($pid === '' || $title === '') { echo json_encode(['ok'=>false,'message'=>'缺少题目编号或标题']); exit; }
$bg = $data['background'] ?? ''; $desc = $data['description'] ?? '';
$inf = $data['input_format'] ?? ''; $outf = $data['output_format'] ?? ''; $hint = $data['hints'] ?? '';
$vis = in_array($data['visibility'] ?? 'public', ['public','hidden']) ? $data['visibility'] : 'public';
$tl = floatval($data['time_limit'] ?? 2.0); $ml = intval($data['memory_limit'] ?? 128);
$me = currentUser();

$s = $pdo->prepare("SELECT id FROM problems WHERE problem_id=?"); $s->execute([$pid]);
if ($s->fetch()) {
    $pdo->prepare("UPDATE problems SET title=?,background=?,description=?,input_format=?,output_format=?,hints=?,time_limit=?,memory_limit=?,visibility=? WHERE problem_id=?")
        ->execute([$title,$bg,$desc,$inf,$outf,$hint,$tl,$ml,$vis,$pid]);
    $created = false;
} else {
    $pdo->prepare("INSERT INTO problems (problem_id,title,background,description,input_format,output_format,hints,time_limit,memory_limit,created_by,visibility) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$pid,$title,$bg,$desc,$inf,$outf,$hint,$tl,$ml,$me['username'],$vis]);
    $created = true;
}
// 同步 config.yaml name（数据存在时）
$dDir = "/data/problems/$pid";
if (is_dir($dDir) && count(glob("$dDir/*.in")) > 0) {
    @mkdir($dDir, 0777, true);
    $cPath = "$dDir/config.yaml";
    $cfgOld = file_exists($cPath) ? simpleYaml(file_get_contents($cPath)) : [];
    $out = "name: $title\ntime_limit: $tl\nmemory_limit: $ml\ntest_cases: " . count(glob("$dDir/*.in")) . "\nscoring_mode: " . ($cfgOld['scoring_mode'] ?? 'default') . "\n";
    file_put_contents($cPath, $out);
}
echo json_encode(['ok'=>true, 'created'=>$created, 'problem_id'=>$pid]);
