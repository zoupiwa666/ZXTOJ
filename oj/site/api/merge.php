<?php
// 分片上传 - 合并分片为完整文件
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$md5 = preg_replace('/[^a-f0-9]/i', '', $_POST['md5'] ?? ($raw['md5'] ?? ''));
$name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_POST['name'] ?? ($raw['name'] ?? 'package.zip')));
$total = intval($_POST['total'] ?? ($raw['total'] ?? 0));
if (strlen($md5) !== 32 || $total <= 0 || $total > 10000) {
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>'参数错误']); exit;
}
$dir = '/tmp/oj_uploads/'.$md5;
@mkdir('/tmp/oj_packages', 0777, true);
$merged = '/tmp/oj_packages/'.$md5.'_'.$name;
if (!is_dir($dir)) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'分片不存在']); exit; }

$out = fopen($merged, 'wb');
if (!$out) { http_response_code(500); echo json_encode(['ok'=>false,'message'=>'无法创建文件']); exit; }
for ($i = 0; $i < $total; $i++) {
    $f = $dir.'/chunk_'.$i;
    if (!file_exists($f)) { fclose($out); @unlink($merged); http_response_code(400); echo json_encode(['ok'=>false,'message'=>"缺少分片 $i"]); exit; }
    $in = fopen($f, 'rb');
    if (!$in) { fclose($out); @unlink($merged); http_response_code(500); echo json_encode(['ok'=>false,'message'=>'读取分片失败']); exit; }
    stream_copy_to_stream($in, $out);
    fclose($in);
}
fclose($out);
foreach (glob($dir.'/*') as $f) @unlink($f);
@rmdir($dir);
echo json_encode(['ok'=>true,'path'=>$merged,'size'=>filesize($merged)]);
