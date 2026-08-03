<?php
// 下载题目数据包（zip）——nginx X-Accel-Redirect 零拷贝直传，速度接近磁盘带宽
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/auth.php';
requireRole('admin');

$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['problem_id'] ?? '');
if ($pid === '') { http_response_code(400); die('参数错误'); }
$dir = "/data/problems/$pid";
if (!is_dir($dir)) { http_response_code(404); die('题目数据不存在'); }

$files = glob("$dir/*");
if (!$files) { http_response_code(404); die('题目数据为空'); }

// 清理该题目的旧数据包，避免堆积
foreach (glob("/tmp/oj_packages/{$pid}_data_*.zip") as $old) @unlink($old);

$tmp = '/tmp/oj_packages/' . $pid . '_data_' . time() . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); die('无法创建压缩包');
}
foreach ($files as $f) {
    if (!is_file($f)) continue;
    $name = basename($f);
    $zip->addFile($f, $name);
    // 不压缩（CM_STORE），打包速度最快，数据包多为文本几乎无压缩收益
    $zip->setCompressionName($name, ZipArchive::CM_STORE);
}
$zip->close();

// 交给 nginx 零拷贝直传（和 submission 数据下载同款方案）
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $pid . '_data.zip"');
header('Content-Length: ' . filesize($tmp));
header('X-Accel-Redirect: /oj_packages/' . basename($tmp));
exit;
