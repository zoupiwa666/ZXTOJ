<?php
require __DIR__.'/inc/config.php'; require __DIR__.'/inc/auth.php'; requireRole('admin');
require_once __DIR__.'/inc/zip_parser.php';   // simpleYaml 用于 config.yaml 解析
$pid = $_GET['id'] ?? ''; $isNew = empty($pid); $msg = ''; $problem = null;

if (!$isNew) {
    $stmt = $pdo->prepare("SELECT * FROM problems WHERE problem_id = ?"); $stmt->execute([$pid]);
    $problem = $stmt->fetch();
    if (!$problem) die("Not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_problem') {
        $title=$_POST['title']??'';$bg=$_POST['background']??'';$desc=$_POST['description']??'';
        $inf=$_POST['input_format']??'';$outf=$_POST['output_format']??'';$hint=$_POST['hints']??'';
        $vis=$_POST['visibility']??'public';
        if ($isNew) {
            $newPid=trim($_POST['problem_id']??'');
            if(!$newPid){$msg='需要题目编号';goto end;}
            $s=$pdo->prepare("SELECT id FROM problems WHERE problem_id=?");$s->execute([$newPid]);
            if($s->fetch()){$msg='编号已存在';goto end;}
            $pdo->prepare("INSERT INTO problems (problem_id,title,background,description,input_format,output_format,hints,created_by,visibility) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$newPid,$title,$bg,$desc,$inf,$outf,$hint,currentUser()['username'],$vis]);
            $pid=$newPid;$isNew=false;
        } else {
            $pdo->prepare("UPDATE problems SET title=?,background=?,description=?,input_format=?,output_format=?,hints=?,visibility=? WHERE problem_id=?")
                ->execute([$title,$bg,$desc,$inf,$outf,$hint,$vis,$pid]);
        }
        // 自动同步 config.yaml：name 跟随题面标题；数据存在则生成/更新（保留 tl/ml/评分等字段）
        if (!$isNew) {
            $dDir = "/data/problems/$pid";
            if (is_dir($dDir) && count(glob("$dDir/*.in")) > 0) {
                $cPath = "$dDir/config.yaml";
                if (file_exists($cPath)) {
                    $cfgOld = simpleYaml(file_get_contents($cPath));
                    $cfgOld['name'] = $title;
                    $out = "name: $title\n";
                    foreach (['time_limit','memory_limit','test_cases','scoring_mode'] as $k) {
                        if (isset($cfgOld[$k]) && $cfgOld[$k] !== null) $out .= "$k: {$cfgOld[$k]}\n";
                    }
                    file_put_contents($cPath, $out);
                } else {
                    $n = count(glob("$dDir/*.in"));
                    file_put_contents($cPath, "name: $title\ntime_limit: {$problem['time_limit']}\nmemory_limit: {$problem['memory_limit']}\ntest_cases: $n\nscoring_mode: default\n");
                }
            }
        }
        $msg='已保存。';
    }
    elseif ($action === 'save_samples' && !$isNew) {
        $pdo->prepare("DELETE FROM problem_samples WHERE problem_id=?")->execute([$pid]);
        $ins=$_POST['s_input']??[];$outs=$_POST['s_output']??[];
        for($i=0;$i<count($ins);$i++){if(trim($ins[$i])===''&&trim($outs[$i])==='')continue;
            $pdo->prepare("INSERT INTO problem_samples (problem_id,sort_order,input_text,output_text) VALUES (?,?,?,?)")->execute([$pid,$i+1,$ins[$i],$outs[$i]]);}
        $msg='样例已保存。';
    }
    elseif ($action === 'save_config' && !$isNew) {
        // config.yaml 可视化保存：同步写文件 + 更新 problems 表
        $name = trim($_POST['cfg_name']??'') ?: $problem['title'];
        $tl = floatval($_POST['cfg_tl'] ?? 2.0);
        $ml = intval($_POST['cfg_ml'] ?? 128);
        $mode = in_array($_POST['cfg_mode'] ?? 'default', ['default']) ? $_POST['cfg_mode'] : 'default';
        $dataDir = "/data/problems/$pid";
        @mkdir($dataDir, 0777, true);
        $n = count(glob("$dataDir/*.in"));
        if ($n < 1) { $msg='暂无测试数据，无法保存 config.yaml（先生成或导入数据）'; goto end; }
        file_put_contents("$dataDir/config.yaml", "name: $name\ntime_limit: $tl\nmemory_limit: $ml\ntest_cases: $n\nscoring_mode: $mode\n");
        $pdo->prepare("UPDATE problems SET time_limit=?, memory_limit=? WHERE problem_id=?")
            ->execute([$tl, $ml, $pid]);
        $msg='config.yaml 已保存并同步。';
        $problem['time_limit']=$tl; $problem['memory_limit']=$ml;
    }
    elseif ($action === 'save_checker' && !$isNew) {
        // 前端 checker 编辑器：写 checker.py（Python）或 checker.cpp（testlib），切换类型时清理旧文件
        $type = $_POST['checker_type'] ?? 'none';
        $code = $_POST['checker_code'] ?? '';
        $dataDir = "/data/problems/$pid";
        @mkdir($dataDir, 0777, true);
        if ($type === 'py') {
            if (trim($code) === '') { $msg='Python checker 代码不能为空'; goto end; }
            file_put_contents("$dataDir/checker.py", $code);
            @unlink("$dataDir/checker.cpp");
            $msg='checker.py 已保存（Python 模式）。';
        } elseif ($type === 'cpp') {
            if (trim($code) === '') { $msg='C++ checker 代码不能为空'; goto end; }
            file_put_contents("$dataDir/checker.cpp", $code);
            @unlink("$dataDir/checker.py");
            $msg='checker.cpp 已保存（testlib 模式）。';
        } else {
            @unlink("$dataDir/checker.py"); @unlink("$dataDir/checker.cpp");
            $msg='已移除 checker（标准比对）。';
        }
    }
    elseif ($action === 'grant_user' && !$isNew) {
        $user=trim($_POST['grant_username']??'');
        if($user){$pdo->prepare("INSERT IGNORE INTO problem_permissions (problem_id,username,granted_by) VALUES (?,?,?)")->execute([$pid,$user,currentUser()['username']]);$msg='已授权。';}
    }
    elseif ($action === 'revoke_user' && !$isNew) {
        $uid=intval($_POST['perm_id']??0);
        $pdo->prepare("DELETE FROM problem_permissions WHERE id=? AND problem_id=?")->execute([$uid,$pid]);$msg='已撤销。';
    }
    end:
    if(!$isNew){$stmt=$pdo->prepare("SELECT * FROM problems WHERE problem_id=?");$stmt->execute([$pid]);$problem=$stmt->fetch();}
}

if(!$isNew){
    $stmt=$pdo->prepare("SELECT * FROM problem_samples WHERE problem_id=? ORDER BY sort_order");$stmt->execute([$pid]);$samples=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM problem_testcases WHERE problem_id=?");$stmt->execute([$pid]);$tcCount=$stmt->fetchColumn();
    // config.yaml 读取（可视化）
    $dataDir = "/data/problems/$pid";
    $cfg = ['name'=>$problem['title'], 'time_limit'=>$problem['time_limit'], 'memory_limit'=>$problem['memory_limit'], 'scoring_mode'=>'default'];
    $cfgPath = "$dataDir/config.yaml";
    if (file_exists($cfgPath)) {
        $parsed = simpleYaml(file_get_contents($cfgPath));
        if (is_array($parsed)) foreach ($parsed as $k=>$v) $cfg[$k] = $v;
    }
    $inCount = is_dir($dataDir) ? count(glob("$dataDir/*.in")) : 0;
    $hasPyCk = file_exists("$dataDir/checker.py");
    $hasCppCk = file_exists("$dataDir/checker.cpp");
    $pyCkCode = $hasPyCk ? file_get_contents("$dataDir/checker.py") : '';
    $cppCkCode = $hasCppCk ? file_get_contents("$dataDir/checker.cpp") : '';
}
$pageTitle=($isNew?'新建':'编辑').'题目 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
?>
<style>
input,textarea,select{width:100%;padding:8px 10px;background:#000;border:1px solid #333;color:#ddd;font-size:13px;resize:vertical;font-family:inherit;outline:none;margin-bottom:6px}
input:focus,textarea:focus,select:focus{border-color:#666}
.btn{padding:8px 24px;background:#2a2a2a;color:#ddd;border:none;font-size:12px;cursor:pointer;letter-spacing:1px;font-family:inherit}
.btn:hover{background:#3a3a3a;color:#fff}
.btn-sm{padding:4px 12px;font-size:11px}
.btn-danger{background:#400;color:#c00;border:1px solid #600}
.card{background:#1e1e1e;border:1px solid #2a2a2a;padding:20px;margin-bottom:16px}
.card h3{font-size:13px;color:#fff;font-weight:400;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #222}
.row{display:flex;gap:12px}.row>div{flex:1;min-width:120px}
label{font-size:11px;color:#999;display:block;margin-bottom:2px}
.file-zone{border:1px dashed #333;padding:20px;text-align:center;cursor:pointer}.file-zone:hover{border-color:#666}.file-zone input{display:none}
.msg{padding:8px 12px;border:1px solid #0c0;color:#0c0;margin-bottom:16px;font-size:12px}
.tabs{display:flex;gap:0;border-bottom:1px solid #222;margin-bottom:20px}
.tabs .tab{padding:10px 26px;color:#999;cursor:pointer;font-size:13px;border-bottom:2px solid transparent;transition:all .15s;user-select:none;letter-spacing:1px}
.tabs .tab:hover{color:#fff}
.tabs .tab.active{color:#fff;border-bottom-color:#5af}
.tab-panel{display:none}
.tab-panel.active{display:block}
.cfg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.cfg-grid .full{grid-column:1/-1}
.kv{display:flex;justify-content:space-between;font-size:12px;color:#999;padding:6px 0;border-bottom:1px dashed #222}
.kv b{color:#ccc}
</style>
<a href="problems.php" style="font-size:12px;color:#999;text-decoration:none;display:block;margin-bottom:16px">← 返回题库</a>
<h1 class="page-title"><?=$isNew?'新建题目':'编辑: '.$pid?></h1>
<?php if($msg):?><div class="msg"><?=$msg?></div><?php endif?>

<div class="tabs">
  <div class="tab active" data-tab="statement" onclick="showTab('statement')"><i class="fa-solid fa-file-lines"></i> 题面配置</div>
  <div class="tab" data-tab="data" onclick="showTab('data')"><i class="fa-solid fa-database"></i> 数据 + config.yaml</div>
  <div class="tab" data-tab="perm" onclick="showTab('perm')"><i class="fa-solid fa-lock"></i> 权限配置</div>
  <div class="tab" data-tab="ai" onclick="showTab('ai')"><i class="fa-solid fa-robot"></i> AI 功能</div>
</div>

<!-- ========== Tab1 题面 ========== -->
<div id="tab-statement" class="tab-panel active">
<form method="POST"><input type="hidden" name="action" value="save_problem">
<div class="card"><h3><i class="fa-solid fa-circle-info"></i> 题目信息</h3>
<?php if($isNew):?><label>题目编号</label><input name="problem_id" placeholder="例如: P1000" required><?php endif?>
<label>标题</label><input name="title" value="<?=htmlspecialchars($problem['title']??'')?>" required>
<div class="row">
  <div><label>可见性</label><select name="visibility"><option value="public" <?=($problem['visibility']??'public')==='public'?'selected':''?>>公开</option><option value="hidden" <?=($problem['visibility']??'')==='hidden'?'selected':''?>>隐藏</option></select></div>
  <?php if(!$isNew):?><div><label>时间 / 内存限制</label><div style="font-size:12px;color:#999;padding:8px 10px;background:#111;border:1px solid #222"><?=$cfg['time_limit']?>s / <?=$cfg['memory_limit']?>MB（在「数据 + config.yaml」栏目修改）</div></div><?php endif?>
</div>
<?php foreach(['background'=>'题目背景','description'=>'题目描述','input_format'=>'输入格式','output_format'=>'输出格式','hints'=>'提示'] as $k=>$lb):?>
<label><?=$lb?> (Markdown + LaTeX)</label><textarea name="<?=$k?>" rows="3"><?=htmlspecialchars($problem[$k]??'')?></textarea>
<?php endforeach?>
<button class="btn">保存题面</button>
</div></form>

<?php if(!$isNew):?>
<form method="POST"><input type="hidden" name="action" value="save_samples">
<div class="card"><h3><i class="fa-solid fa-list-ol"></i> 样例</h3>
<div id="samples">
<?php foreach($samples as $i=>$s):?>
<div style="background:#141414;border:1px solid #222;padding:12px;margin-bottom:8px">
<div style="display:flex;justify-content:space-between;margin-bottom:6px"><b style="font-size:12px">样例 #<?=$i+1?></b><button type="button" class="btn-sm btn-danger" onclick="this.closest('div').remove()">删除</button></div>
<div class="row"><div><label>输入</label><textarea name="s_input[]" rows="3"><?=htmlspecialchars($s['input_text'])?></textarea></div><div><label>输出</label><textarea name="s_output[]" rows="3"><?=htmlspecialchars($s['output_text'])?></textarea></div></div></div>
<?php endforeach?>
</div>
<button type="button" class="btn" style="background:transparent;border:1px solid #333;margin-bottom:12px" onclick="addSample()">+ 添加样例</button><br>
<button class="btn">保存样例</button>
</div></form>
<?php endif?>
</div>

<!-- ========== Tab2 数据 + config.yaml ========== -->
<div id="tab-data" class="tab-panel">
<?php if($isNew):?><div class="card"><h3>提示</h3><div style="font-size:12px;color:#999">请先在「题面配置」保存题目，再到这里配置数据与 config.yaml。</div></div>
<?php else:?>
<div class="card"><h3>config.yaml（可视化，保存后与文件双向同步）</h3>
<form method="POST"><input type="hidden" name="action" value="save_config">
<div class="cfg-grid">
  <div><label>题目名称 (name)</label><input name="cfg_name" value="<?=htmlspecialchars($cfg['name']??'')?>"></div>
  <div><label>评分模式 (scoring_mode)</label><select name="cfg_mode">
    <option value="default" <?=($cfg['scoring_mode']??'default')==='default'?'selected':''?>>default（默认平分）</option>
  </select></div>
  <div><label>时间限制 (s)</label><input type="number" step="0.5" name="cfg_tl" value="<?=$cfg['time_limit']??2.0?>"></div>
  <div><label>内存限制 (MB)</label><input type="number" name="cfg_ml" value="<?=$cfg['memory_limit']??128?>"></div>
  <div class="full"><label>测试点数量 (test_cases)</label>
    <div style="font-size:13px;color:#5af;padding:8px 10px;background:#111;border:1px solid #222"><?=$inCount?> 组（由实际数据文件自动统计，保存时写入 config.yaml）</div>
  </div>
  <div class="full"><label>测试点数量 (test_cases)</label>
    <div style="font-size:13px;color:#5af;padding:8px 10px;background:#111;border:1px solid #222"><?=$inCount?> 组（由实际数据文件自动统计，保存时写入 config.yaml）</div>
  </div>
</div>
<button class="btn" style="margin-top:8px"><i class="fa-solid fa-floppy-disk"></i> 保存 config.yaml</button>
</form>
</div>

<div class="card"><h3><i class="fa-solid fa-clipboard-check"></i> checker 编辑器（特殊判题）</h3>
<form method="POST"><input type="hidden" name="action" value="save_checker">
<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
  <select name="checker_type" id="ckType" style="width:220px" onchange="switchCkType()">
    <option value="none" <?=($hasPyCk||$hasCppCk)?'':'selected'?>>无 checker（标准比对）</option>
    <option value="py" <?=$hasPyCk?'selected':''?>>Python（check 函数）</option>
    <option value="cpp" <?=$hasCppCk?'selected':''?>>C++（testlib.h）</option>
  </select>
  <span id="ckStatus" style="font-size:12px;color:<?=($hasPyCk||$hasCppCk)?'#2ecc71':'#666'?>">
    <?=($hasPyCk?'已配置 checker.py':($hasCppCk?'已配置 checker.cpp':'未配置'))?>
  </span>
</div>
<textarea name="checker_code" id="ckCode" rows="12" style="font-family:monospace;font-size:12px" placeholder="选择类型后在此编写 checker 代码..."></textarea>
<div style="font-size:11px;color:#666;margin:4px 0" id="ckHint"></div>
<button class="btn" style="margin-top:8px"><i class="fa-solid fa-floppy-disk"></i> 保存 checker</button>
</form>
</div>

<div class="card"><h3><i class="fa-solid fa-file-import"></i> 导入数据包（已有 <span id="tcCount"><?=$tcCount?></span> 个测试点 / 目录 <?=$inCount?> 组文件）</h3>
<label class="file-zone" id="dz"><div style="font-size:20px">+</div><div style="font-size:12px">拖拽或点击上传 .zip / .tar.gz</div><div style="font-size:11px;color:#999" id="fn">未选择</div><input type="file" name="package" accept=".zip,.tar.gz,.tgz,.tar" id="pf"></label>
<button class="btn" style="margin-top:12px" onclick="uploadPackage()" id="importBtn">标准上传</button> <button class="btn" style="margin-top:12px;background:#1a3a5c;color:#5af" onclick="directUpload()" id="directBtn">直传</button>
<div style="margin-top:12px;display:flex;gap:8px">
  <input id="serverPath" placeholder="服务器路径或下载链接: /tmp/a.zip 或 https://...zip" style="flex:1;font-size:12px" onkeydown="if(event.key==='Enter')importServerPath()">
  <button class="btn btn-sm" onclick="importServerPath()">路径导入</button>
</div>
<div id="pathStatus" style="margin-top:4px;font-size:12px;color:#999"></div>
<progress id="progressBar" value="0" max="100" style="width:100%;height:4px;display:none;margin-top:8px;accent-color:#5af;border:none;background:#222"></progress>
<div id="progressText" style="font-size:11px;color:#5af;text-align:center;display:none"></div>
<div id="importStatus" style="margin-top:4px;font-size:12px;color:#999"></div>
</div>

<div class="card"><h3><i class="fa-solid fa-folder-open"></i> 数据文件概览</h3>
<?php if($inCount>0):?>
<div class="kv"><span>目录</span><b><?=htmlspecialchars($dataDir)?></b></div>
<div class="kv"><span>测试点</span><b><?=$inCount?> 组（1.in ~ <?=$inCount?>.in）</b></div>
<div class="kv"><span>config.yaml</span><b><?=file_exists("$dataDir/config.yaml")?'已存在':'缺失（保存上方表单自动生成）'?></b></div>
<div class="kv"><span>首个测试点大小</span><b><?=file_exists("$dataDir/1.in")?number_format(filesize("$dataDir/1.in")).'B':'—'?></b></div>
<?php else:?><div class="empty" style="color:#999;font-size:12px">暂无测试数据。</div><?php endif?>
</div>
<?php endif?>
</div>

<!-- ========== Tab3 权限 ========== -->
<div id="tab-perm" class="tab-panel">
<?php if($isNew):?><div class="card"><h3>提示</h3><div style="font-size:12px;color:#999">请先保存题目。</div></div>
<?php else:?>
<form method="POST"><input type="hidden" name="action" value="grant_user">
<div class="card"><h3><i class="fa-solid fa-key"></i> 访问权限</h3>
<div style="display:flex;gap:8px;margin-bottom:12px"><input name="grant_username" placeholder="用户名 或 team->组名" style="flex:1"><button class="btn btn-sm">授权</button></div>
<?php $perms=$pdo->prepare("SELECT * FROM problem_permissions WHERE problem_id=?");$perms->execute([$pid]);$plist=$perms->fetchAll();if($plist):?>
<table style="width:100%;font-size:11px;color:#888">
<?php foreach($plist as $pm):?>
<tr><td><?=htmlspecialchars($pm['username'])?></td><td style="color:#666">by <?=$pm['granted_by']?></td><td>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="revoke_user"><input type="hidden" name="perm_id" value="<?=$pm['id']?>"><button class="btn-sm btn-danger">撤销</button></form></td></tr>
<?php endforeach?>
</table>
<?php else:?><div style="font-size:12px;color:#666">公开题目无需授权；隐藏题目需授权给用户或组才能查看/提交。</div><?php endif?>
</div></form>
<?php endif?>
</div>

<!-- ========== Tab4 AI 功能 ========== -->
<div id="tab-ai" class="tab-panel">
<?php if($isNew):?><div class="card"><h3>提示</h3><div style="font-size:12px;color:#999">请先保存题目。</div></div>
<?php else:?>
<div class="card"><h3><i class="fa-solid fa-robot"></i> AI 助手（聊天式造数据）</h3>
<div style="font-size:12px;color:#999;margin-bottom:12px">像聊天一样让 AI 生成/修改测试数据：AI 掌握文件读写与专用工具（生成器/标准解法/checker 自检），可自由对话。</div>
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <a class="btn" style="background:#1a3a5c;color:#5af;text-decoration:none" href="ai_studio.php?pid=<?=urlencode($pid)?>"><i class="fa-solid fa-comments"></i> 打开 AI 助手</a>
  <a class="btn" style="background:#2a1a4c;color:#a9f;text-decoration:none" href="ai_studio.php?pid=<?=urlencode($pid)?>">（会话 URL 恢复，可多轮修改）</a>
</div>
</div>
<div class="card"><h3><i class="fa-solid fa-gauge-high"></i> 数据与 config 快速状态</h3>
<div class="kv"><span>测试点</span><b><?=$inCount?> 组</b></div>
<div class="kv"><span>时间限制</span><b><?=$cfg['time_limit']?>s</b></div>
<div class="kv"><span>内存限制</span><b><?=$cfg['memory_limit']?>MB</b></div>
<div class="kv"><span>checker</span><b><?=($hasPyCk||$hasCppCk)?'<i class="fa-solid fa-circle-check" style="color:#2ecc71"></i> 已配置（'.($hasCppCk?'checker.cpp':'checker.py').'）':'未配置'?></b></div>
</div>
<?php endif?>
</div>

<script>
function showTab(name){
  document.querySelectorAll('.tabs .tab').forEach(t=>t.classList.toggle('active', t.dataset.tab===name));
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.toggle('active', p.id==='tab-'+name));
  if(name==='data' && !window._dataLoaded) refreshData();
}
function addSample(){const n=document.querySelectorAll('#samples>div').length;const d=document.createElement('div');d.style.cssText='background:#141414;border:1px solid #222;padding:12px;margin-bottom:8px';d.innerHTML='<div style="display:flex;justify-content:space-between;margin-bottom:6px"><b style="font-size:12px">样例 #'+(n+1)+'</b><button class="btn-sm btn-danger" onclick="this.closest(\'div\').remove()">删除</button></div><div class="row"><div><label>输入</label><textarea name="s_input[]" rows="3"></textarea></div><div><label>输出</label><textarea name="s_output[]" rows="3"></textarea></div></div>';document.getElementById('samples').appendChild(d)}
if(!document.querySelectorAll('#samples>div').length)addSample();
function refreshData(){ window._dataLoaded=true; }
const CK_PY = <?=json_encode($pyCkCode??'')?>;
const CK_CPP = <?=json_encode($cppCkCode??'')?>;
const CK_TMPL_PY = `def check(input, output, expected):
    # input=测试输入 output=选手输出 expected=标准答案
    # 返回 True/False，或 (是否通过:bool, 提示:str, 得分占比:float)
    return output.strip() == expected.strip()`;
const CK_TMPL_CPP = `#include "testlib.h"
int main(int argc, char* argv[]) {
    registerTestlibCmd(argc, argv);
    // ans=标准答案 ouf=选手输出 inf=输入
    std::string ja = ans.readString();
    std::string pa = ouf.readString();
    if (ja != pa) quitf(_wa, "expected '%s' found '%s'", ja.c_str(), pa.c_str());
    quitf(_ok, "ok");
}`;
function switchCkType(){
  const t = document.getElementById('ckType').value;
  const code = document.getElementById('ckCode');
  const hint = document.getElementById('ckHint');
  const st = document.getElementById('ckStatus');
  if(t === 'py'){ code.value = CK_PY || CK_TMPL_PY; hint.textContent='Python checker：定义 check(input, output, expected) 函数，返回 True/False 或 (bool, 提示, 分数占比)'; st.textContent = CK_PY ? '已配置 checker.py' : '（未保存，填写后保存生效）'; }
  else if(t === 'cpp'){ code.value = CK_CPP || CK_TMPL_CPP; hint.textContent='C++ testlib checker：registerTestlibCmd 后比较 ans/ouf，quitf(_ok/_wa) 判定'; st.textContent = CK_CPP ? '已配置 checker.cpp' : '（未保存，填写后保存生效）'; }
  else { code.value = ''; hint.textContent='标准比对：逐行比较选手输出与标准答案'; st.textContent = '未配置'; }
}
switchCkType();
const dz=document.getElementById('dz'),pf=document.getElementById('pf'),fn=document.getElementById('fn');if(dz){dz.addEventListener('click',()=>pf.click());pf.addEventListener('change',()=>fn.textContent=pf.files[0]?.name||'未选择');}
function uploadPackage(){
 const f=document.getElementById('pf').files[0];if(!f)return;
 const b=document.getElementById('importBtn');b.disabled=true;
 const st=document.getElementById('importStatus');
 const pb=document.getElementById('progressBar');
 const pt=document.getElementById('progressText');
 st.innerHTML='';pb.style.display='block';pt.style.display='block';pt.textContent='0%';pb.value=0;
 const fd=new FormData();fd.append('package',f);fd.append('problem_id','<?=$pid?>');
 const xhr=new XMLHttpRequest();
 xhr.upload.onprogress=function(e){ if(e.lengthComputable){const pct=Math.round(e.loaded/e.total*100);pb.value=pct;pt.textContent=pct+'%';} };
 xhr.onload=function(){
  pb.style.display='none';pt.style.display='none';
  try{const d=JSON.parse(xhr.responseText);
   if(d.ok&&d.path){document.getElementById('serverPath').value=d.path;st.innerHTML='<span style="color:#0c0">已保存</span>';importServerPath();}
   else{st.innerHTML='<span style="color:#c00">'+d.message+'</span>';}
  }catch(e){st.innerHTML='<span style="color:#c00">错误</span>'}
  b.disabled=false;
 };
 xhr.onerror=function(){pb.style.display='none';pt.style.display='none';st.innerHTML='<span style="color:#c00">上传中断</span>';b.disabled=false;};
 xhr.timeout=600000;xhr.open('POST','api/upload_package.php');xhr.send(fd);
}
async function importServerPath(){
 const path=document.getElementById('serverPath').value.trim();if(!path)return;
 const st=document.getElementById('pathStatus');st.innerHTML='处理中...';
 try{
  const fd=new FormData();fd.append("server_path",path);fd.append("problem_id","<?=$pid?>");
  const r=await fetch('api/import_by_path.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){st.innerHTML='<span style="color:#0c0">'+d.message+'</span> 3秒后刷新...';setTimeout(()=>location.reload(),3000);}
  else{st.innerHTML='<span style="color:#c00">'+d.message+'</span>';}
 }catch(e){st.innerHTML='<span style="color:#c00">失败</span>';}
}
const CHUNK_SIZE=5*1024*1024, MAX_CONCURRENT=3, UPLOAD_URL='http://156.239.236.66:1227';
async function directUpload(){
 const f=document.getElementById("pf").files[0];if(!f)return;
 const b=document.getElementById("directBtn");b.disabled=true;b.textContent="准备...";
 const st=document.getElementById("importStatus"),pb=document.getElementById("progressBar"),pt=document.getElementById("progressText");
 st.innerHTML="";pb.style.display="block";pt.style.display="block";pb.max=f.size;
 const md5=await calcMD5(f).catch(e=>{st.innerHTML='<span style="color:#c00">MD5失败</span>';b.disabled=false;b.textContent='直传';throw e}); if(!md5)return;
 b.textContent="检查...";
 const ck=await fetch(UPLOAD_URL+'/check',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({md5,name:f.name})});
 const cj=await ck.json();
 if(cj.instant){pb.value=f.size;pt.textContent="秒传!";st.innerHTML='<span style="color:#0c0">秒传成功</span>';document.getElementById("serverPath").value=cj.path;importServerPath();b.disabled=false;b.textContent="直传";return}
 const exist=new Set(cj.exist.map(x=>parseInt(x)));
 const total=Math.ceil(f.size/CHUNK_SIZE);
 let uploaded=exist.size*CHUNK_SIZE;
 pb.value=uploaded;pt.textContent=Math.round(uploaded/f.size*100)+"%";
 const tasks=[];
 for(let i=0;i<total;i++){if(!exist.has(i))tasks.push(i)}
 let done=0;
 async function uploadChunk(i){
  const start=i*CHUNK_SIZE,end=Math.min(start+CHUNK_SIZE,f.size);
  const blob=f.slice(start,end);
  const fd=new FormData();fd.append('file',blob);fd.append('md5',md5);fd.append('index',i);
  for(let retry=0;retry<3;retry++){
   try{
    const r=await fetch(UPLOAD_URL+'/chunk',{method:'POST',body:fd});
    if(r.ok){done++;uploaded+=blob.size;pb.value=uploaded;pt.textContent=Math.round(uploaded/f.size*100)+"% "+done+"/"+total;return}
   }catch(e){await new Promise(r=>setTimeout(r,1000*Math.pow(2,retry)))}
  }
  throw new Error("chunk "+i+" failed")
 }
 b.textContent="上传中...";
 await Promise.all(tasks.slice(0,MAX_CONCURRENT).map(i=>uploadChunk(i)));
 const mr=await fetch(UPLOAD_URL+'/merge',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({md5,name:f.name,total})});
 const mj=await mr.json();
 if(mj.path){document.getElementById("serverPath").value=mj.path;st.innerHTML='<span style="color:#0c0">完成</span>';importServerPath()}
 else{st.innerHTML='<span style="color:#c00">合并失败</span>'}
 b.disabled=false;b.textContent="直传";
}
async function calcMD5(file){
 return new Promise(resolve=>{
  const chunks=Math.ceil(file.size/2097152),spark=new SparkMD5.ArrayBuffer;
  let idx=0;const reader=new FileReader;
  reader.onload=e=>{spark.append(e.target.result);idx++;if(idx<chunks)loadNext();else resolve(spark.end())};
  function loadNext(){reader.readAsArrayBuffer(file.slice(idx*2097152,(idx+1)*2097152))}
  loadNext()
 })
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
