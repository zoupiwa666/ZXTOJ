<?php
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireRole('admin');

$path = $_POST['server_path'] ?? '';
// 支持 URL 下载
if (preg_match('/^https?:\/\//i', $path)) {
    $tmp = '/tmp/oj_packages/url_' . time() . '_' . basename(parse_url($path, PHP_URL_PATH));
    $fp = fopen($tmp, 'wb');
    $ch = curl_init($path);
    curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>120]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    $path = $tmp;
}
if (!$path || !file_exists($path)) { die(json_encode(['ok'=>false,'message'=>'文件不存在，请确认路径或链接可访问'])); }

require_once __DIR__.'/../inc/zip_parser.php';
$info = parsePackageLocally($path, basename($path));

if (!empty($info['error'])) { die(json_encode(['ok'=>false,'message'=>$info['error']])); }

$pid = $_POST['problem_id'] ?? '';
if (!$pid) { die(json_encode(['ok'=>false,'message'=>'缺少problem_id'])); }

// 写入文件到 /data/problems/{pid}/
$dir = "/data/problems/$pid";
@mkdir($dir, 0777, true);
// 清空旧文件
foreach (glob("$dir/*") as $f) @unlink($f);

// 更新数据库元数据
$pdo->prepare("DELETE FROM problem_testcases WHERE problem_id=?")->execute([$pid]);
$stmt = $pdo->prepare("INSERT INTO problem_testcases (problem_id,sort_order,input_text,output_text,score,file_path) VALUES (?,?,?,?,?,?)");
$i = 1;
foreach ($info['test_cases'] as $tc) {
    // 写文件
    file_put_contents("$dir/$i.in", cleanData($tc["input"]));
    file_put_contents("$dir/$i.out", cleanData($tc["expected_output"]));
    file_put_contents("$dir/$i.score", $tc['score']);
    // 数据库只存元数据
    $stmt->execute([$pid, $i, '', '', $tc['score'], "/data/problems/$pid/$i"]);
    $i++;
}
if (isset($info['time_limit'])) $pdo->prepare("UPDATE problems SET time_limit=? WHERE problem_id=?")->execute([floatval($info['time_limit']), $pid]);
if (isset($info['memory_limit'])) $pdo->prepare("UPDATE problems SET memory_limit=? WHERE problem_id=?")->execute([intval($info['memory_limit']), $pid]);

// interactor 落盘：交互题判定以 interactor.cpp 是否存在为准
if (!empty($info['interactor'])) {
    file_put_contents("$dir/interactor.cpp", $info['interactor']);
} else {
    @unlink("$dir/interactor.cpp");
}

// checker 落盘（Python 优先：有 checker.py 就不写 checker.cpp；只有 cpp 才写 cpp）
if (!empty($info['checker'])) {
    file_put_contents("$dir/checker.py", $info['checker']);
    @unlink("$dir/checker.cpp");
} elseif (!empty($info['checker_cpp'])) {
    file_put_contents("$dir/checker.cpp", $info['checker_cpp']);
    @unlink("$dir/checker.py");
}

// 写入 config.yaml，把时限等绑定到数据目录（评测时以 config.yaml 为准）
$cfgTl = floatval($info['time_limit'] ?? 2.0);
$cfgMl = intval($info['memory_limit'] ?? 128);
$cfgName = preg_replace('/[\r\n:]+/', ' ', $info['name'] ?? $pid);
file_put_contents("$dir/config.yaml", "name: $cfgName\ntime_limit: $cfgTl\nmemory_limit: $cfgMl\ntest_cases: ".($i-1)."\nscoring_mode: ".($info['scoring_mode'] ?? 'default')."\ninteractive: ".(!empty($info['interactor']) ? 'true' : 'false')."\n");

echo json_encode(['ok'=>true, 'message'=>"导入成功！".($i-1)."个测试点，数据已写入 /data/problems/$pid/"]);
if (isset($tmp) && file_exists($tmp)) @unlink($tmp);
