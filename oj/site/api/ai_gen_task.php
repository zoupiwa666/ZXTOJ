<?php
// AI 自动造数据 - 后台任务执行器（由 ai_gen.php 通过 nohup 异步启动）
// 职责：读任务JSON -> 调 DeepSeek 生成代码 -> 服务器运行生成器/解法产出测试点
//       -> 写 /data/problems/{pid}/ -> 同步 DB -> 更新状态文件
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$taskFile = $argv[1] ?? '';
if (!$taskFile || !file_exists($taskFile)) { fwrite(STDERR, "no task file\n"); exit(1); }
$task = json_decode(file_get_contents($taskFile), true);
if (!$task) { fwrite(STDERR, "bad task json\n"); exit(1); }

$taskId    = $task['task_id'];
$pid       = $task['pid'];
$apiKey    = $task['api_key'];
$n         = intval($task['count'] ?? 10);
$needCk    = !empty($task['need_checker']);
$ckReq     = trim($task['checker_req'] ?? '');
$statusFile = "/tmp/ai_gen/$taskId.status";

function setStatus($status, $step, $progress, $message) {
    global $statusFile;
    @file_put_contents($statusFile, json_encode(
        ['status'=>$status, 'step'=>$step, 'progress'=>$progress, 'message'=>$message],
        JSON_UNESCAPED_UNICODE
    ));
}

require __DIR__.'/../inc/config.php';   // 提供 $pdo

// ---------- 1. 读题目信息 ----------
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id=?"); $s->execute([$pid]);
$prob = $s->fetch();
if (!$prob) { setStatus('error', '失败', 0, '题目不存在'); exit; }

// ---------- 2. 调 DeepSeek 生成 生成器/解法/checker/config ----------
setStatus('running', 'DeepSeek 生成代码中', 5, '正在让 DeepSeek 根据题面编写数据生成器与标准解法...');

$desc = "题目编号: {$prob['problem_id']}\n题目名称: {$prob['title']}\n"
      . "时间限制: {$prob['time_limit']} 秒\n内存限制: {$prob['memory_limit']} MB\n"
      . "题面: " . ($prob['description'] ?? '') . "\n"
      . "输入格式: " . ($prob['input_format'] ?? '') . "\n"
      . "输出格式: " . ($prob['output_format'] ?? '') . "\n"
      . "提示: " . ($prob['hints'] ?? '') . "\n";

$ckField = '';
if ($needCk) {
    $req = $ckReq !== '' ? $ckReq : '按题意标准比对，必要时放宽浮点误差';
    $ckField = ",\"checker_code\":\"Python3 特殊判题 checker 代码。必须定义函数 check(input, output, expected)，参数均为字符串：input=测试输入、output=选手输出、expected=标准答案。返回 True/False，或返回 (是否通过:bool, 提示信息:str, 得分占比:float)。要求：{$req}。不要写 main 或读文件\"";
}

$prompt = "你是 OJ 出题助手。请根据以下题目信息，生成用于构造测试数据的三段 Python3 代码。\n\n$desc\n"
    . "请严格只返回一个 JSON 对象（不要 markdown 代码块、不要任何解释文字），包含：\n"
    . "{\"gen_code\":\"Python3 数据生成器代码。每次运行向 stdout 输出一组随机、合法的输入数据（覆盖边界与大数据），不要输出任何多余内容。要求每组运行结果具有随机区分度，并覆盖 $n 组数据规模的多样性\""
    . ",\"sol_code\":\"Python3 标准解法代码。从 stdin 读取输入，向 stdout 输出正确答案，不要输出多余内容\""
    . ",\"config_yaml\":\"yaml 文本，含 name/time_limit/memory_limit 字段\""
    . $ckField . "}\n"
    . "共需生成 {$n} 组测试数据。";

$ch = curl_init('https://api.deepseek.com/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => '你是专业的 OJ 出题助手，严格只输出合法 JSON。'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.6,
    ]),
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $err = json_decode($resp, true);
    $msg = $err['error']['message'] ?? ('DeepSeek API 调用失败 (HTTP ' . $httpCode . ')');
    setStatus('error', 'DeepSeek 调用失败', 0, $msg);
    exit;
}
$data = json_decode($resp, true);
$content = $data['choices'][0]['message']['content'] ?? '';
// 容错提取 JSON：去围栏 -> 取首个 { 到末个 }
$content = trim($content);
$content = preg_replace('/^```(?:json)?\s*/i', '', $content);
$content = preg_replace('/\s*```$/', '', $content);
if ($content === '' || $content[0] !== '{') {
    $a = strpos($content, '{'); $b = strrpos($content, '}');
    if ($a !== false && $b !== false && $b > $a) $content = substr($content, $a, $b - $a + 1);
}
$gen = json_decode($content, true);
if (!is_array($gen)) { setStatus('error', '解析失败', 0, 'DeepSeek 返回内容无法解析为 JSON'); exit; }

$genCode = $gen['gen_code'] ?? '';
$solCode = $gen['sol_code'] ?? '';
if ($genCode === '' || $solCode === '') { setStatus('error', '代码缺失', 0, 'DeepSeek 未返回完整的生成器/解法代码'); exit; }
$ckCode = ($needCk && !empty($gen['checker_code'])) ? $gen['checker_code'] : '';

// ---------- 3. 保存临时代码并运行 ----------
$tmpDir = "/tmp/ai_gen/gen_$taskId";
@mkdir($tmpDir, 0777, true);
file_put_contents("$tmpDir/gen.py", $genCode);
file_put_contents("$tmpDir/sol.py", $solCode);

$outDir = "/data/problems/$pid";
@mkdir($outDir, 0777, true);

$scoreEach = round(100 / $n, 2);
$errors = [];
// ---------- 4. 清理旧测试文件（只保留本次生成的） ----------
foreach (glob("$outDir/*") as $f) {
    $bn = basename($f);
    if (preg_match('/^\d+\.(in|out|score)$/', $bn)) @unlink($f);
}

$okCount = 0;
for ($i = 1; $i <= $n; $i++) {
    setStatus('running', '运行数据生成器', 20 + intval(70 * ($i - 1) / $n), "正在生成第 $i/$n 组数据...");
    // 生成输入
    exec("timeout 20 python3 $tmpDir/gen.py > '$outDir/$i.in' 2>/dev/null", $o, $rc);
    if ($rc !== 0) { $errors[] = "第{$i}组: 生成器执行失败"; continue; }
    // 运行标准解法生成期望输出
    exec("timeout 20 python3 $tmpDir/sol.py < '$outDir/$i.in' > '$outDir/$i.out' 2>/dev/null", $o, $rc);
    if ($rc !== 0) { $errors[] = "第{$i}组: 解法执行失败"; continue; }
    file_put_contents("$outDir/$i.score", $scoreEach);
    $okCount++;
}

if ($okCount === 0) {
    setStatus('error', '生成失败', 0, '全部生成失败：' . implode('; ', array_slice($errors, 0, 5)));
    @array_map('unlink', glob("$tmpDir/*")); @rmdir($tmpDir);
    exit;
}

// ---------- 4. checker：本次需要则写，否则删旧的 ----------
if ($needCk && $ckCode !== '') {
    file_put_contents("$outDir/checker.py", $ckCode);
} elseif (file_exists("$outDir/checker.py")) {
    @unlink("$outDir/checker.py");
}

// ---------- 5. config.yaml ----------
$cfgName = preg_replace('/[\r\n:]+/', ' ', $prob['title']);
if (!empty($gen['config_yaml'])) {
    $cy = $gen['config_yaml'];
    if (preg_match('/name\s*:\s*(.+)/', $cy, $m)) $cfgName = trim($m[1]);
}
$scoresArr = implode(', ', array_fill(0, $n, $scoreEach));
file_put_contents("$outDir/config.yaml",
    "name: $cfgName\ntime_limit: {$prob['time_limit']}\nmemory_limit: {$prob['memory_limit']}\ntest_cases: $n\nscores: [$scoresArr]\n");

// ---------- 6. 同步数据库 ----------
try {
    $pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
    $stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score,file_path) VALUES (?,?,?,?,?,?)");
    for ($i = 1; $i <= $n; $i++) {
        $stmt->execute([$pid, $i, '', '', $scoreEach, "/data/problems/$pid/$i"]);
    }
    $pdo->prepare("UPDATE problems SET time_limit=?, memory_limit=? WHERE problem_id=?")
        ->execute([floatval($prob['time_limit']), intval($prob['memory_limit']), $pid]);
} catch (Exception $e) {
    // DB 同步失败不影响已落盘的数据，仅记录
    file_put_contents("/tmp/ai_gen/$taskId.db_err", $e->getMessage());
}

// ---------- 7. 清理并完成 ----------
@array_map('unlink', glob("$tmpDir/*")); @rmdir($tmpDir);
@unlink($taskFile);   // 任务文件含 API Key，用后即删

$msg = "AI 造数据完成：成功 {$okCount}/{$n} 组" . ($errors ? '（失败 ' . count($errors) . ' 组：' . implode('; ', array_slice($errors, 0, 3)) . '）' : '');
if ($needCk && $ckCode !== '') $msg .= '，已生成 checker';
setStatus('done', '完成', 100, $msg);
