<?php
// 我的文件 - 合并分片（校验总空间256MB，移入用户目录）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$me = currentUser();
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$md5 = preg_replace('/[^a-f0-9]/i', '', $_POST['md5'] ?? ($raw['md5'] ?? ''));
$name = basename($_POST['name'] ?? ($raw['name'] ?? ''));
$total = intval($_POST['total'] ?? ($raw['total'] ?? 0));
$MAX_TOTAL = 256 * 1024 * 1024;
if (strlen($md5) !== 32 || $total <= 0 || $total > 20000) { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }

$dir = '/tmp/fuploads/' . $md5;
if (!is_dir($dir)) { echo json_encode(['ok'=>false,'message'=>'分片不存在']); exit; }
// 校验所有分片存在 + 计算总大小
$tmp = $dir.'/merged.tmp';
$out = fopen($tmp, 'wb');
if (!$out) { echo json_encode(['ok'=>false,'message'=>'无法创建临时文件']); exit; }
$size = 0;
for ($i = 0; $i < $total; $i++) {
    $f = $dir.'/chunk_'.$i;
    if (!file_exists($f)) { fclose($out); @unlink($tmp); echo json_encode(['ok'=>false,'message'=>"缺少分片 $i"]); exit; }
    $size += filesize($f);
    $in = fopen($f, 'rb');
    stream_copy_to_stream($in, $out);
    fclose($in);
}
fclose($out);
// 总空间校验
$used = $pdo->prepare("SELECT COALESCE(SUM(size),0) FROM user_files WHERE username=?");
$used->execute([$me['username']]);
$used = intval($used->fetchColumn());
if ($used + $size > $MAX_TOTAL) {
    @unlink($tmp);
    echo json_encode(['ok'=>false,'message'=>'总空间超限：已用 '.round($used/1048576).'MB，限制 256MB']); exit;
}
// 移入用户目录
$dir2 = '/data/userfiles/' . $me['username'];
@mkdir($dir2, 0777, true); @chmod($dir2, 0777);
$stored = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
if (!rename($tmp, $dir2.'/'.$stored)) {
    @unlink($tmp);
    echo json_encode(['ok'=>false,'message'=>'文件保存失败，请检查目录权限']); exit;
}
$pdo->prepare("INSERT INTO user_files (username, filename, stored_name, size) VALUES (?,?,?,?)")
    ->execute([$me['username'], $name, $stored, $size]);
// 清理分片
foreach (glob($dir.'/*') as $f) @unlink($f);
@rmdir($dir);
echo json_encode(['ok'=>true, 'message'=>'上传成功', 'used'=>$used+$size, 'max'=>$MAX_TOTAL]);
