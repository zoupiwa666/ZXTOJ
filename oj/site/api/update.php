<?php
require __DIR__ . '/../inc/config.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { http_response_code(400); die('{}'); }

$taskId = $data['task_id'] ?? '';
$result = $data['result'] ?? [];
if (!$taskId) { http_response_code(400); die('{}'); }

$s = $pdo->prepare("SELECT * FROM submissions WHERE judge_task_id = ?");
$s->execute([$taskId]); $sub = $s->fetch();
if (!$sub) { die('{}'); }

$results = $result['results'] ?? [];
$status = 'AC';
$totalScore = 0; $passed = 0; $sumTime = 0; $peakMem = 0;
$detailList = [];

foreach ($results as $r) {
    $totalScore += floatval($r['score'] ?? 0);
    $sumTime   += floatval($r['time_used'] ?? 0);
    $mem        = floatval($r['memory_used'] ?? 0);
    if ($mem > $peakMem) $peakMem = $mem;
    if (!empty($r['passed'])) $passed++;
    $detailList[] = $r;
    if (empty($r['passed']) && $status === 'AC') {
        $status = $r['verdict'] ?? 'WA';
    }
}
if ($passed === count($results) && $passed > 0) $status = 'AC';

$pdo->prepare("UPDATE submissions SET status=?, score=?, passed_tests=?, peak_memory=?, total_time=?, details=? WHERE id=?")
    ->execute([$status, $totalScore, $passed, $peakMem, round($sumTime, 3), json_encode($detailList), $sub['id']]);

echo json_encode(['ok' => true]);
