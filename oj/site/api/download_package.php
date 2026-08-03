<?php
// 下载题目数据包（zip，直接下载，管理员可用）
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/auth.php';
requireRole('admin');

$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['problem_id'] ?? '');
if ($pid === '') { http_response_code(400); die('参数错误'); }
$dir = "/data/problems/$pid";
if (!is_dir($dir)) { http_response_code(404); die('题目数据不存在'); }

$files = glob("$dir/*");
if (!$files) { http_response_code(404); die('题目数据为空'); }

$tmp = '/tmp/oj_packages/' . $pid . '_data_' . time() . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); die('无法创建压缩包');
}
foreach ($files as $f) {
    if (is_file($f)) $zip->addFile($f, basename($f));
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $pid . '_data.zip"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
