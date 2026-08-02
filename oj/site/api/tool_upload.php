<?php
/**
 * Simple data package upload endpoint (for upload.bat / upload.sh)
 * Saves the package to /tmp/oj_packages/ and returns the server path for "Import by Path".
 *
 * Usage: curl -F "file=@xxx.zip" http://IP:PORT/api/tool_upload.php
 * Returns: {"ok":true,"path":"/tmp/oj_packages/xxx.zip",...}
 */
header('Content-Type: application/json; charset=utf-8');

$dir = '/tmp/oj_packages';
@mkdir($dir, 0777, true);

$field = isset($_FILES['file']) ? 'file' : (isset($_FILES['package']) ? 'package' : null);
if (!$field || empty($_FILES[$field]['tmp_name'])) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'message' => 'No file received (use curl -F "file=@xxx.zip")']));
}

$f = $_FILES[$field];
if ($f['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'message' => 'Upload failed, error code: ' . $f['error']]));
}
if ($f['size'] > 200 * 1024 * 1024) {
    http_response_code(413);
    die(json_encode(['ok' => false, 'message' => 'File too large (limit 200MB)']));
}

$name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($f['name']));
if ($name === '' || $name === '_') $name = 'package_' . time() . '.zip';
$path = $dir . '/' . time() . '_' . $name;

if (!move_uploaded_file($f['tmp_name'], $path)) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'message' => 'Failed to save file, check /tmp/oj_packages permissions']));
}

echo json_encode(['ok' => true, 'message' => 'Upload OK', 'path' => $path, 'size' => filesize($path)]);
