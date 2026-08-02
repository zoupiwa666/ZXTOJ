<?php
/**
 * 简易数据包上传接口（供 upload.bat / upload.sh 调用）
 * 将数据包保存到 /tmp/oj_packages/，返回服务器路径供「路径导入」使用
 *
 * 用法: curl -F "file=@xxx.zip" http://IP:端口/api/tool_upload.php
 * 返回: {"ok":true,"path":"/tmp/oj_packages/xxx.zip",...}
 */
header('Content-Type: application/json; charset=utf-8');

$dir = '/tmp/oj_packages';
@mkdir($dir, 0777, true);

$field = isset($_FILES['file']) ? 'file' : (isset($_FILES['package']) ? 'package' : null);
if (!$field || empty($_FILES[$field]['tmp_name'])) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'message' => '未收到文件（请使用 curl -F "file=@xxx.zip" 上传）']));
}

$f = $_FILES[$field];
if ($f['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'message' => '上传失败，错误码: ' . $f['error']]));
}
if ($f['size'] > 200 * 1024 * 1024) {
    http_response_code(413);
    die(json_encode(['ok' => false, 'message' => '文件过大（上限 200MB）']));
}

$name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($f['name']));
if ($name === '' || $name === '_') $name = 'package_' . time() . '.zip';
$path = $dir . '/' . time() . '_' . $name;

if (!move_uploaded_file($f['tmp_name'], $path)) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'message' => '文件保存失败，请检查 /tmp/oj_packages 权限']));
}

echo json_encode(['ok' => true, 'message' => '上传成功', 'path' => $path, 'size' => filesize($path)]);
