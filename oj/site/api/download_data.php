<?php
// 非AC测试点数据直连下载（nginx X-Accel-Redirect 零拷贝，不经过 PHP 内存）
// 参数: submission_id, case(1-based 数据文件序号), type=in|out|user_out
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/auth.php';
requireLogin();

$sid  = intval($_GET['submission_id'] ?? 0);
$case = intval($_GET['case'] ?? 0);
$type = $_GET['type'] ?? '';
if (!$sid || $case < 1) { http_response_code(400); die('参数错误'); }
if (!in_array($type, ['in', 'out', 'user_out'])) { http_response_code(400); die('类型错误'); }

$stmt = $pdo->prepare("SELECT * FROM submissions WHERE id=?");
$stmt->execute([$sid]);
$sub = $stmt->fetch();
if (!$sub) { http_response_code(404); die('提交不存在'); }

$user = currentUser();
if (!$user) { http_response_code(403); die('未登录'); }
$isAdmin = in_array($user['role'], ['super_admin', 'admin']);
if ($sub['username'] !== $user['username'] && !$isAdmin) { http_response_code(403); die('只能下载自己提交的数据'); }

$status = strtoupper($sub['status']);
if ($status === 'AC') { die('该提交已 AC，没有非AC数据'); }

// 确认该测试点确实是非 AC
$details = json_decode($sub['details'] ?? '[]', true) ?: [];
$idx = $case - 1;
$target = null;
foreach ($details as $r) {
    if (intval($r['test_case_index'] ?? -1) === $idx) { $target = $r; break; }
}
if (!$target || !empty($target['passed'])) { http_response_code(403); die('该测试点已通过，不能下载'); }

$pid = $sub['problem_id'];

// 用户输出来自评测结果（内容小，直接输出）
if ($type === 'user_out') {
    $content = (string)($target['output'] ?? '');
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $pid . '_' . $case . '.user.out"');
    echo $content === '' ? "（空输出）\n" : $content;
    exit;
}

// 输入/期望输出：nginx sendfile 直连发送
$ext = $type === 'in' ? 'in' : 'out';
$file = "/data/problems/$pid/$case.$ext";
if (!file_exists($file)) { http_response_code(404); die('数据文件不存在'); }

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $pid . '_' . $case . '.' . $ext . '"');
header('X-Accel-Redirect: /oj_data/' . rawurlencode($pid) . '/' . $case . '.' . $ext);
exit;
