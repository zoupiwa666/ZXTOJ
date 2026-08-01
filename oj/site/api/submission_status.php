<?php
require __DIR__ . '/../inc/config.php';
$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT id,status,score,max_score,passed_tests,total_tests,total_time,peak_memory,details FROM submissions WHERE id=?");
$stmt->execute([$id]); $s = $stmt->fetch();
if (!$s) { die('{}'); }
echo json_encode($s);
