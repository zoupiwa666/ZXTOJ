<?php
// 只保存文件，不解压
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');

$pid = $_POST['problem_id'] ?? '';
if (!$pid || empty($_FILES['package']['tmp_name'])) { http_response_code(400); die('{}'); }

$dir = '/tmp/oj_packages';
@mkdir($dir, 0777, true);
$path = $dir . '/' . $pid . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['package']['name']);
move_uploaded_file($_FILES['package']['tmp_name'], $path);

echo json_encode(['ok'=>true, 'message'=>'文件已保存', 'path'=>$path, 'size'=>filesize($path)]);
