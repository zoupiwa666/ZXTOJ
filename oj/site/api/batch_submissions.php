<?php
// 提交记录批量操作（管理员）：批量删除 / 批量重测
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$ids = json_decode($_POST['ids'] ?? '[]', true) ?: [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
if (!$action || empty($ids)) { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }

if ($action === 'delete') {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id IN ($in)");
    $stmt->execute($ids);
    echo json_encode(['ok'=>true,'message'=>'已删除 '.$stmt->rowCount().' 条提交记录']);
    exit;
}

if ($action === 'rejudge') {
    $ok = 0; $fail = 0; $detail = [];
    foreach ($ids as $sid) {
        $r = rejudgeOne($sid);
        $detail[] = $r;
        if ($r['ok']) $ok++; else $fail++;
    }
    echo json_encode(['ok'=>true,'message'=>"批量重测完成：$ok 条成功，$fail 条失败", 'detail'=>$detail]);
    exit;
}

echo json_encode(['ok'=>false,'message'=>'未知操作']);

function rejudgeOne($sid): array {
    global $pdo, $JUDGE_URL;
    $s = $pdo->prepare("SELECT * FROM submissions WHERE id=?"); $s->execute([$sid]);
    $sub = $s->fetch();
    if (!$sub) return ['id'=>$sid,'ok'=>false,'message'=>'提交不存在'];
    $p = $pdo->prepare("SELECT time_limit, memory_limit FROM problems WHERE problem_id=?");
    $p->execute([$sub['problem_id']]); $prob = $p->fetch();
    $tl = floatval($prob['time_limit'] ?? 2.0); $ml = intval($prob['memory_limit'] ?? 128);
    $pdo->prepare("UPDATE submissions SET status='judging', judge_task_id=NULL, details='[]', score=0, passed_tests=0, total_time=0, peak_memory=0 WHERE id=?")->execute([$sid]);
    $ch = curl_init($JUDGE_URL . '/judge_by_problem');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode([
            'problem_id'=>$sub['problem_id'], 'language'=>$sub['language'],
            'code'=>$sub['code'], 'time_limit'=>$tl, 'memory_limit'=>$ml,
        ]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>20,
    ]);
    $resp = json_decode(curl_exec($ch), true); curl_close($ch);
    $taskId = $resp['task_id'] ?? '';
    if (!$taskId) {
        $pdo->prepare("UPDATE submissions SET status='SE' WHERE id=?")->execute([$sid]);
        return ['id'=>$sid,'ok'=>false,'message'=>'评测机无响应'];
    }
    $pdo->prepare("UPDATE submissions SET judge_task_id=? WHERE id=?")->execute([$taskId, $sid]);
    $deadline = time() + 90; // 批量模式每个最多等 90 秒
    $result = null;
    while (time() < $deadline) {
        sleep(1);
        $ch = curl_init($JUDGE_URL . '/result/' . $taskId);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
        $result = json_decode(curl_exec($ch), true); curl_close($ch);
        if ($result && isset($result['status']) && !in_array($result['status'], ['pending','running'])) break;
    }
    if (!$result || !isset($result['status']) || in_array($result['status'], ['pending','running'])) {
        return ['id'=>$sid,'ok'=>false,'message'=>'评测超时，保持重测中'];
    }
    $results = $result['results'] ?? [];
    if ($result['status'] === 'compile_error') {
        $pdo->prepare("UPDATE submissions SET status='CE', score=0, passed_tests=0, peak_memory=0, total_time=0, details=? WHERE id=?")
            ->execute([json_encode([['verdict'=>'CE','passed'=>false,'score'=>0,'error'=>$result['compile_error'] ?? '编译错误']]), $sid]);
        return ['id'=>$sid,'ok'=>true,'status'=>'CE'];
    }
    $status = 'AC'; $totalScore = 0; $passed = 0; $sumTime = 0; $peakMem = 0;
    foreach ($results as $r) {
        $totalScore += floatval($r['score'] ?? 0);
        $sumTime    += floatval($r['time_used'] ?? 0);
        $mem         = floatval($r['memory_used'] ?? 0);
        if ($mem > $peakMem) $peakMem = $mem;
        if (!empty($r['passed'])) $passed++;
        if (empty($r['passed']) && $status === 'AC') $status = $r['verdict'] ?? 'WA';
    }
    if ($passed === count($results) && $passed > 0) $status = 'AC';
    if (count($results) === 0) $status = 'SE';
    $pdo->prepare("UPDATE submissions SET status=?, score=?, passed_tests=?, peak_memory=?, total_time=?, details=? WHERE id=?")
        ->execute([$status, $totalScore, $passed, $peakMem, round($sumTime,3), json_encode($results), $sid]);
    return ['id'=>$sid,'ok'=>true,'status'=>$status];
}
