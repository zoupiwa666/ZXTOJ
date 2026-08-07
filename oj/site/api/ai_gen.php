<?php
// AI 自动造数据：DeepSeek 生成数据生成器/标准解法，服务器运行产出测试点
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');

$pid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['problem_id'] ?? '');
$key = trim($_POST['api_key'] ?? '');
$n = min(max(intval($_POST['count'] ?? 10), 1), 50);
$checkerReq = trim($_POST['checker_req'] ?? '');

if ($pid === '') { echo json_encode(['ok'=>false,'message'=>'缺少题目编号']); exit; }
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?"); $s->execute([$pid]);
$prob = $s->fetch();
if (!$prob) { echo json_encode(['ok'=>false,'message'=>'题目不存在']); exit; }

// API Key：优先用传入的，否则读已保存的
$keyFile = '/data/.deepseek_key';
if ($key === '' && file_exists($keyFile)) $key = trim(file_get_contents($keyFile));
if ($key === '') { echo json_encode(['ok'=>false,'message'=>'请输入 DeepSeek API Key']); exit; }
if ($_POST['save_key'] ?? 0) @file_put_contents($keyFile, $key);

// 组装题目信息 prompt
$desc = "题目: {$prob['title']}\n时间限制: {$prob['time_limit']}s\n内存限制: {$prob['memory_limit']}MB\n"
      . "题面: ".($prob['description'] ?? '')."\n"
      . "输入格式: ".($prob['input_format'] ?? '')."\n"
      . "输出格式: ".($prob['output_format'] ?? '')."\n"
      . "提示: ".($prob['hints'] ?? '')."\n";
$needCk = $checkerReq !== '' ? "\n需要特殊判题 checker，要求：$checkerReq" : "\n不需要 checker";

$prompt = "你是 OJ 出题助手。请根据题目信息生成测试数据生成器。\n$desc\n"
    . "请生成并只返回 JSON（不要任何其他文字、不要 markdown 代码块）：\n"
    . "{\"gen_code\":\"Python3 数据生成器代码，运行后向 stdout 输出一组随机合法输入，不要输出多余内容\","
    . "\"sol_code\":\"Python3 标准解法代码，从 stdin 读输入，向 stdout 输出答案\","
    . "\"config_yaml\":\"yaml 内容（name/time_limit/memory_limit）\""
    . ($needCk!=='' ? ",\"checker_code\":\"Python3 特殊判题 checker：参数 argv[1]=stdin输入文件 argv[2]=选手输出 argv[3]=答案文件，输出 AC 或 WA\"" : '')
    . "}\n生成 {$n} 组数据的生成器（保证每组输入随机且有区分度）$needCk";

// 调 DeepSeek
$ch = curl_init('https://api.deepseek.com/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>120,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Authorization: Bearer '.$key],
    CURLOPT_POSTFIELDS=>json_encode([
        'model'=>'deepseek-chat',
        'messages'=>[['role'=>'system','content'=>'你是专业的 OJ 出题助手，严格只输出 JSON'],['role'=>'user','content'=>$prompt]],
        'temperature'=>0.7,
    ]),
]);
$resp = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($httpCode !== 200) {
    $err = json_decode($resp, true);
    $msg = $err['error']['message'] ?? ('API 调用失败 (HTTP '.$httpCode.')');
    echo json_encode(['ok'=>false,'message'=>$msg]); exit;
}
$data = json_decode($resp, true);
$content = $data['choices'][0]['message']['content'] ?? '';
// 提取 JSON（去掉可能的 markdown 代码块围栏）
$content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
$content = preg_replace('/\s*```$/', '', $content);
$gen = json_decode($content, true);
if (!is_array($gen)) { echo json_encode(['ok'=>false,'message'=>'DeepSeek 返回格式无法解析']); exit; }

$genCode = $gen['gen_code'] ?? '';
$solCode = $gen['sol_code'] ?? '';
if ($genCode === '' || $solCode === '') { echo json_encode(['ok'=>false,'message'=>'生成器/解法代码缺失']); exit; }

// 保存并运行
$tmpDir = '/tmp/ai_gen/' . $pid . '_' . uniqid();
@mkdir($tmpDir, 0777, true);
file_put_contents("$tmpDir/gen.py", $genCode);
file_put_contents("$tmpDir/sol.py", $solCode);
$ckCode = ($needCk !== '' && !empty($gen['checker_code'])) ? $gen['checker_code'] : '';

$outDir = "/data/problems/$pid";
@mkdir($outDir, 0777, true);
$scores = round(100/$n, 2);
$errors = [];
for ($i = 1; $i <= $n; $i++) {
    // 生成输入
    exec("timeout 20 python3 $tmpDir/gen.py > '$outDir/$i.in' 2>/dev/null", $o, $rc);
    if ($rc !== 0) { $errors[] = "第{$i}组生成器执行失败"; continue; }
    // 运行解法生成期望输出
    exec("timeout 20 python3 $tmpDir/sol.py < '$outDir/$i.in' > '$outDir/$i.out' 2>/dev/null", $o, $rc);
    if ($rc !== 0) { $errors[] = "第{$i}组解法执行失败"; continue; }
    file_put_contents("$outDir/$i.score", $scores);
}
if ($i === 1 && $errors) { // 一组都没成
    echo json_encode(['ok'=>false,'message'=>'生成失败：'.implode('; ', $errors)]); exit;
}
// config.yaml（以实际生成为准）
$name = $gen['config_yaml'] ?? '';
$cfgTl = $prob['time_limit']; $cfgMl = $prob['memory_limit'];
$cfgName = preg_replace('/[\r\n:]+/', ' ', $prob['title']);
if (preg_match('/name\s*:\s*(.+)/', $name, $m)) $cfgName = trim($m[1]);
$scoresArr = implode(', ', array_fill(0, $n, $scores));
file_put_contents("$outDir/config.yaml", "name: $cfgName\ntime_limit: $cfgTl\nmemory_limit: $cfgMl\ntest_cases: $n\nscores: [$scoresArr]\n");
if ($ckCode !== '') file_put_contents("$outDir/checker.py", $ckCode);

// 清理临时
array_map('unlink', glob("$tmpDir/*")); @rmdir($tmpDir);

$ok = $n - count($errors);
echo json_encode(['ok'=>true, 'message'=>"AI 造数据完成：成功 $ok 组".($errors?'（失败 '.count($errors).' 组：'.implode('; ', array_slice($errors,0,3)).'）':''), 'n'=>$ok, 'checker'=>$ckCode!=='' ]);
