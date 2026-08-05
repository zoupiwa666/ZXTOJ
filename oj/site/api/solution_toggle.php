<?php
// 管理员：切换题目是否允许提交新题解
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/article_tables.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');
$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['problem_id'] ?? '');
if ($pid === '') { echo json_encode(['ok'=>false,'message'=>'参数错误']); exit; }
$s = $pdo->prepare("SELECT solution_open FROM problems WHERE problem_id=?"); $s->execute([$pid]);
$cur = $s->fetchColumn();
if ($cur === false) { echo json_encode(['ok'=>false,'message'=>'题目不存在']); exit; }
$new = $cur ? 0 : 1;
$pdo->prepare("UPDATE problems SET solution_open=? WHERE problem_id=?")->execute([$new, $pid]);
echo json_encode(['ok'=>true, 'message'=>$new?'已开启新题解提交':'已关闭新题解提交', 'open'=>$new]);
