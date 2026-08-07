<?php
require __DIR__ . '/inc/config.php'; require __DIR__ . '/inc/auth.php'; requireLogin();
$id = intval($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
$s->execute([$id]); $sub = $s->fetch();
if (!$sub) die("Not found");
// stuck judging 自动修复
if (in_array($sub['status'], ['waiting','judging']) && $sub['judge_task_id']) {
    $ch = curl_init($JUDGE_URL . '/result/' . $sub['judge_task_id']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
    $resp = curl_exec($ch); $jResult = json_decode($resp, true);
    curl_close($ch);
    if ($jResult && isset($jResult['status']) && $jResult['status'] !== 'running' && $jResult['status'] !== 'pending') {
        // 更新提交
        $_POST_orig = $_POST;
        $_POST = ['task_id' => $sub['judge_task_id'], 'result' => $jResult];
        // 直接调 update 逻辑...
        $results = $jResult['results'] ?? [];
        $status = 'AC'; $totalScore = 0; $passed = 0; $sumTime = 0; $peakMem = 0;
        foreach ($results as $r) {
            $totalScore += floatval($r['score'] ?? 0);
            $sumTime += floatval($r['time_used'] ?? 0);
            $mem = floatval($r['memory_used'] ?? 0);
            if ($mem > $peakMem) $peakMem = $mem;
            if (!empty($r['passed'])) $passed++;
            if (empty($r['passed']) && $status === 'AC') $status = $r['verdict'] ?? 'WA';
        }
        if (count($results) > 0 && $passed === count($results)) $status = 'AC';
        // results 为空无法判定，不覆盖状态（保持 waiting/judging，由 worker 接管），避免误标 SE
        if (count($results) === 0) { /* 跳过更新 */ }
        else {
            $pdo->prepare("UPDATE submissions SET status=?,score=?,passed_tests=?,peak_memory=?,total_time=?,details=? WHERE id=?")
                ->execute([$status, $totalScore, $passed, $peakMem, round($sumTime,3), json_encode($results), $sub['id']]);
        }
        $_POST = $_POST_orig;
        // 重新查询
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
        $stmt->execute([$id]); $sub = $stmt->fetch();
    }
}
$details = json_decode($sub['details'] ?? '[]', true) ?: [];
$sc = strtolower($sub['status']);
$pageTitle = "提交 #$id - Zxt Super OJ";
require __DIR__ . '/inc/header.php';
?>
<style>
.sub-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.sub-header h1{font-size:16px;color:#fff;font-weight:400;letter-spacing:1px}
.meta-grid{display:grid;grid-template-columns:auto auto;gap:4px 24px;font-size:12px;text-align:right}
.meta-grid .label{color:#999}
.meta-grid .val{color:#ccc}
.s-AC{color:#0c0}.s-WA{color:#c00}.s-TLE{color:#c90}.s-RE{color:#f60}
.s-MLE{color:#c0c}.s-OLE{color:#09c}.s-CE{color:#c60}.s-judging{color:#09f}.s-waiting{color:#999}.s-compiling{color:#ffab00}
.code-block{background:#1a1a1a;border:1px solid #222;padding:14px 16px;margin-bottom:24px;position:relative}
.code-block .lang-tag{position:absolute;top:0;right:0;background:#222;color:#999;font-size:10px;padding:2px 10px;letter-spacing:1px}
.code-block pre{font-family:Consolas,'Courier New',monospace;font-size:12px;color:#aaa;white-space:pre-wrap;line-height:1.5;margin:0}

.tc-list{}
.tc-item{border:1px solid #222;margin-bottom:2px}
.tc-item.ac{border-left:2px solid #0c0}.tc-item.wa{border-left:2px solid #c00}.tc-item.tle{border-left:2px solid #c90}
.tc-item.re{border-left:2px solid #f60}.tc-item.mle{border-left:2px solid #c0c}.tc-item.ole{border-left:2px solid #09c}
.tc-head{padding:10px 14px;display:flex;align-items:center;gap:14px;cursor:pointer;user-select:none;background:#1a1a1a;transition:background .1s}
.tc-head:hover{background:#0f0f0f}
.tc-head .arrow{color:#999;font-size:10px;transition:.15s;width:12px;text-align:center}
.tc-item.open .tc-head .arrow{transform:rotate(90deg)}
.tc-verdict{font-size:11px;font-weight:600;letter-spacing:1px;min-width:32px}
.tc-verdict.ac{color:#0c0}.tc-verdict.wa{color:#c00}.tc-verdict.tle{color:#c90}.tc-verdict.re{color:#f60}.tc-verdict.mle{color:#c0c}.tc-verdict.ole{color:#09c}
.tc-info{flex:1;font-size:11px;color:#999}
.tc-info b{color:#aaa;margin-right:12px}
.tc-score{font-size:12px;color:#ccc;min-width:60px;text-align:right}
.tc-body{display:none;padding:12px 14px 12px 40px;border-top:1px solid #111;background:#151515;font-size:12px}
.tc-item.open .tc-body{display:block}
.tc-body .row{display:flex;gap:16px;margin-bottom:8px}
.tc-body .row>div{flex:1}
.tc-body label{font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;display:block}
.tc-body pre{font-family:Consolas,'Courier New',monospace;font-size:12px;color:#aaa;background:#000;border:1px solid #222;padding:8px 10px;white-space:pre-wrap;word-break:break-all;max-height:200px;overflow-y:auto;line-height:1.4}
.tc-body .error-msg{color:#c00;font-size:11px;margin-top:6px;white-space:pre-wrap}
</style>

<div class="sub-header">
  <h1>提交 #<?=$id?></h1>
  <?php if (isAdmin()): ?>
  <button class="btn btn-sm" style="margin-left:auto" onclick="rejudge(<?=$id?>, this)">重测</button>
  <?php endif; ?>
  <div class="meta-grid">
    <span class="label">用户</span><span class="val"><?= userBadge($sub['username']) ?></span>
    <span class="label">题目</span><span class="val"><a href="problem.php?id=<?=$sub['problem_id']?>" style="color:#ccc;text-decoration:none"><?=$sub['problem_id']?></a></span>
    <span class="label">状态</span><span class="val s-<?=$sc?>"><b><?=$sub['status']?></b></span>
    <span class="label">分数</span><span class="val"><?=$sub['score']?> / <?=$sub['max_score']?></span>
    <span class="label">用时</span><span class="val"><?=number_format($sub['total_time'],3)?>s</span>
    <span class="label">内存</span><span class="val"><?=number_format($sub['peak_memory'],1)?>MB</span>
    <span class="label">语言</span><span class="val"><?=$sub['language']?></span>
    <span class="label">提交于</span><span class="val"><?=date('m-d H:i:s',strtotime($sub['created_at']))?></span>
  </div>
</div>

<div style="position:relative;margin-bottom:24px">
  <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 12px;background:#1a1a1a;border:1px solid #222;border-bottom:none;font-size:11px;color:#888">
    <span><?=$sub['language']?></span>
    <button class="copy-btn" onclick="copyCode(this)" style="position:static;opacity:1;font-size:11px">复制</button>
  </div>
  <pre style="margin:0;border:1px solid #222;border-top:none;background:#111"><code class="language-<?=str_replace(["python3","cpp17","cpp20","cpp14"],["python","cpp","cpp","cpp"],$sub["language"])?>"><?=htmlspecialchars($sub["code"])?></code></pre>
</div>

<div id="liveScore" style="font-size:16px;color:#fff;margin-bottom:8px"></div>
<h2 style="font-size:13px;color:#fff;font-weight:400;margin-bottom:10px;letter-spacing:1px">测试详情 (<?=count($details)?>)</h2>
<div class="tc-list">
<?php foreach ($details as $i => $r): 
  $v = strtolower($r['verdict'] ?? 'se');
  $passed = !empty($r['passed']);
  $tcClass = $passed ? 'ac' : $v;
?>
<div class="tc-item <?=$tcClass?>">
  <div class="tc-head" onclick="this.parentElement.classList.toggle('open')">
    <span class="arrow">▶</span>
    <span class="tc-verdict <?=$tcClass?>"><?=$r['verdict']??'SE'?></span>
    <div class="tc-info">#<?=$i+1?> | <b><?=number_format($r['time_used']??0,3)?>s</b> <b><?=number_format($r['memory_used']??0,1)?>MB</b></div>
    <span class="tc-score">+<?=$r['score']??0?></span>
  </div>
  <div class="tc-body">
    <?php if (!empty($r['output']) || !empty($r['expected_output'])): ?>
    <div class="row">
      <div style="position:relative"><button class="copy-btn" onclick="copyCode(this)">复制</button><label>输出</label><pre><?=htmlspecialchars($r['output']??'(empty)')?></pre></div>
      <div style="position:relative"><button class="copy-btn" onclick="copyCode(this)">复制</button><label>预期</label><pre><?=htmlspecialchars($r['expected_output']??'(empty)')?></pre></div>
    </div>
    <?php endif ?>
    <?php if (!empty($r['error'])): ?>
    <div><label>评测信息</label><div class="error-msg"><?=htmlspecialchars($r['error'])?></div></div>
    <?php endif ?>
    <div style="color:#888;font-size:10px;margin-top:6px">退出码: <?=$r['exit_code']??'?'?></div>
    <?php if (!$passed && in_array(strtoupper($sub['status']), ['WA','TLE','MLE','RE','OLE','CE','SE'])): ?>
    <div style="margin-top:10px;padding-top:8px;border-top:1px solid #222">
      <span style="color:#888;font-size:10px">数据下载:</span>
      <a href="api/download_data.php?submission_id=<?=$id?>&case=<?=$i+1?>&type=in" style="color:#5af;text-decoration:none;font-size:11px;margin-left:8px">输入</a>
      <a href="api/download_data.php?submission_id=<?=$id?>&case=<?=$i+1?>&type=out" style="color:#5af;text-decoration:none;font-size:11px;margin-left:10px">期望输出</a>
      <a href="api/download_data.php?submission_id=<?=$id?>&case=<?=$i+1?>&type=user_out" style="color:#5af;text-decoration:none;font-size:11px;margin-left:10px">我的输出</a>
    </div>
    <?php endif ?>
  </div>
</div>
<?php endforeach ?>
</div>
<?php if (!$details): ?>
<div id="tcLoading" style="padding:20px;color:#666;font-size:12px">评测中，请稍候...</div>
<?php endif; ?>
</div>

<script>
const sid = <?=$id?>;
let shownCases = <?= count($details) ?>;
let lastStatus = '';
let reloaded = false;
async function pollStatus(){
 try{
  const r = await fetch('api/submission_status.php?id='+sid);
  const d = await r.json();
  const scoreEl = document.getElementById('liveScore');
  if(scoreEl && d.score !== undefined) scoreEl.textContent = d.score+' / '+d.max_score;
  if(d.details){
   let list = [];
   try{ list = JSON.parse(d.details).filter(x=>x); }catch(e){}
   for(const x of list){
    if(x.test_case_index !== undefined && x.test_case_index >= shownCases){
     addCaseRow(x);
     shownCases = x.test_case_index + 1;
    }
   }
   const loading = document.getElementById('tcLoading');
   if(loading && list.length > 0) loading.remove();
  }
  const cur = d.status || '';
  const isFinal = (cur !== 'judging' && cur !== 'waiting' && cur !== 'compiling' && cur !== '');
  // 评测完成：自动刷新展示完整详情
  if(lastStatus && (lastStatus==='judging'||lastStatus==='waiting'||lastStatus==='compiling') && isFinal && !reloaded){
   reloaded = true;
   setTimeout(()=>location.reload(), 500);
   return;
  }
  lastStatus = cur;
  if(!isFinal || (d.total_tests && shownCases < d.total_tests)){
   setTimeout(pollStatus, 1500);
  }
 }catch(e){ setTimeout(pollStatus, 2000); }
}
async function rejudge(sid, btn){
 if(!confirm('确定重新评测这份提交？将覆盖当前结果。')) return;
 btn.disabled = true; btn.textContent = '重测中...';
 const fd = new FormData(); fd.append('submission_id', sid);
 try{
  const r = await fetch('api/rejudge.php', {method:'POST', body: fd});
  const d = await r.json();
  if(d.ok){ btn.textContent = '完成，刷新中...'; setTimeout(()=>location.reload(), 800); }
  else { ztAlert(d.message || '重测失败'); btn.disabled=false; btn.textContent='重测'; }
 }catch(e){ ztAlert('重测失败: '+e.message); btn.disabled=false; btn.textContent='重测'; }
}
function addCaseRow(x){
 if(document.getElementById('lc-'+x.test_case_index)) return;
 const v = (x.verdict||'SE').toLowerCase();
 const cls = x.passed ? 'ac' : v;
 const div = document.createElement('div');
 div.id = 'lc-'+x.test_case_index;
 div.className = 'tc-item '+cls;
 div.innerHTML = '<div class="tc-head"><span class="tc-verdict '+cls+'">'+(x.verdict||'SE')+'</span><div class="tc-info">#'+(x.test_case_index+1)+' | '+(x.time_used||0).toFixed(3)+'s</div><span class="tc-score">+'+(x.score||0)+'</span></div>';
 const list = document.querySelector('.tc-list');
 if(list) list.appendChild(div);
}
pollStatus();
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
