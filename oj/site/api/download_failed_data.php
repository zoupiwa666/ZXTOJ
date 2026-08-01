<?php
// 下载提交中所有非AC测试点的数据（输入/期望输出/用户输出/评测信息）
// 权限：仅提交者本人或管理员；AC 的提交不允许下载
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('缺少提交ID'); }

$stmt = $pdo->prepare("SELECT * FROM submissions WHERE id=?");
$stmt->execute([$id]);
$sub = $stmt->fetch();
if (!$sub) { http_response_code(404); die('提交不存在'); }

$user = currentUser();
if (!$user) { http_response_code(403); die('未登录'); }
$isAdmin = in_array($user['role'], ['super_admin', 'admin']);
if ($sub['username'] !== $user['username'] && !$isAdmin) {
    http_response_code(403); die('只能下载自己提交的数据');
}

$status = strtoupper($sub['status']);
if ($status === 'AC') { die('该提交已 AC，没有非AC测试点可下载'); }
if (!in_array($status, ['WA','TLE','MLE','RE','OLE','CE','SE'])) { die('提交尚未评测完成，请稍后再试'); }

$details = json_decode($sub['details'] ?? '[]', true) ?: [];
$failed = [];
foreach ($details as $r) {
    if (empty($r['passed'])) $failed[] = $r;
}
if (!$failed) { die('未找到非AC测试点（可能是编译错误，无测试数据）'); }

$pid = $sub['problem_id'];
$dataDir = "/data/problems/$pid";

$zip = new ZipArchive();
$tmpFile = tempnam(sys_get_temp_dir(), 'ojdl') . '.zip';
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); die('无法创建压缩包');
}

$base = $pid . '_sub' . $id . '_failed';
$lines = [];
$lines[] = "题目: $pid";
$lines[] = "提交ID: $id";
$lines[] = "用户: {$sub['username']}";
$lines[] = "语言: {$sub['language']}";
$lines[] = "状态: {$sub['status']}";
$lines[] = "得分: {$sub['score']} / {$sub['max_score']}";
$lines[] = "非AC测试点: " . count($failed) . " 个";
$lines[] = "";
$lines[] = "文件说明:";
$lines[] = "  XX.in        -> 该测试点的输入数据";
$lines[] = "  XX.out       -> 期望输出（标准答案）";
$lines[] = "  XX.user.out  -> 你的程序实际输出";
$lines[] = "  XX.info.txt  -> 该测试点评测信息（verdict/用时/内存等）";
$zip->addFromString("$base/report.txt", implode("\n", $lines) . "\n");

$n = 0;
foreach ($failed as $r) {
    $idx = intval($r['test_case_index'] ?? $n);
    $fn = $idx + 1;  // 数据文件名从 1 开始
    $n++;
    $prefix = "$base/" . str_pad((string)$fn, 2, '0', STR_PAD_LEFT);

    $inFile = "$dataDir/$fn.in";
    if (file_exists($inFile)) {
        $zip->addFile($inFile, "$prefix.in");
    } else {
        $zip->addFromString("$prefix.in", "（数据文件不存在）\n");
    }
    $outFile = "$dataDir/$fn.out";
    if (file_exists($outFile)) {
        $zip->addFile($outFile, "$prefix.out");
    } else {
        $zip->addFromString("$prefix.out", (string)($r['expected_output'] ?? '') . "\n");
    }
    $userOut = (string)($r['output'] ?? '');
    $zip->addFromString("$prefix.user.out", $userOut === '' ? "（空输出）\n" : $userOut);
    $info = [
        'verdict'     => $r['verdict'] ?? 'SE',
        'score'       => $r['score'] ?? 0,
        'time_used'   => $r['time_used'] ?? 0,
        'memory_used' => $r['memory_used'] ?? 0,
        'exit_code'   => $r['exit_code'] ?? null,
        'error'       => $r['error'] ?? '',
    ];
    $zip->addFromString("$prefix.info.txt", json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $base . '.zip"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
exit;
