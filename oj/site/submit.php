<?php
require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/auth.php';
requireLogin();

$pid = $_GET['id'] ?? '';
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id = ?");
$s->execute([$pid]); $problem = $s->fetch();
if (!$problem) die("题目不存在");

$s = $pdo->prepare("SELECT COUNT(*) FROM problem_testcases WHERE problem_id = ?");
$s->execute([$pid]); $hasData = $s->fetchColumn() > 0;

$pageTitle = '提交 - ' . $problem['title'] . ' - Zxt Super OJ';
require __DIR__ . '/inc/header.php';
?>
<style>
.submit-layout{display:flex;gap:24px}
.problem-side{flex:1}
.submit-side{width:380px;flex-shrink:0}
.preview-box{background:#141414;border:1px solid #222;padding:16px;margin-bottom:12px}
.preview-box h3{font-size:13px;color:#fff;font-weight:400;margin-bottom:8px}
.preview-box .meta{font-size:11px;color:#888;margin-bottom:8px}
select{width:auto;padding:8px 12px;background:#0a0a0a;border:1px solid #2a2a2a;color:#ddd;font-size:13px;font-family:inherit;outline:none;margin-bottom:12px}
select:focus{border-color:#444}
textarea{width:100%;min-height:300px;padding:12px;background:#0a0a0a;border:1px solid #2a2a2a;color:#ddd;font-family:Consolas,'Courier New',monospace;font-size:13px;resize:vertical;outline:none;margin-bottom:12px}
textarea#code{min-height:400px}  /* 代码编辑框加长（覆盖浮动输入框的 60px 限制） */
textarea:focus{border-color:#444}
.btn-submit{width:100%;padding:12px;background:#2a2a2a;color:#ccc;border:none;font-size:14px;font-weight:600;letter-spacing:1px;cursor:pointer;font-family:inherit}
.btn-submit:hover{background:#3a3a3a;color:#fff}
.btn-submit:disabled{opacity:.4;cursor:not-allowed}
.result-card{padding:8px 12px;margin:4px 0;font-size:12px;display:flex;align-items:center;gap:10px;border-left:3px solid transparent}
.result-card.ac{border-left-color:#25ad40}.result-card.wa{border-left-color:#ff4f4f}.result-card.tle{border-left-color:#ffab00}
.result-card.re{border-left-color:#f8603a}.result-card.mle{border-left-color:#d500f9}.result-card.ole{border-left-color:#0091ea}
.verdict{font-weight:700;font-size:11px;letter-spacing:1px;min-width:32px}
.verdict-ac{color:#25ad40}.verdict-wa{color:#ff4f4f}.verdict-tle{color:#ffab00}.verdict-re{color:#f8603a}.verdict-mle{color:#d500f9}.verdict-ole{color:#0091ea}
.score-final{text-align:center;font-size:18px;color:#fff;margin:12px 0}
.spinner{display:inline-block;width:12px;height:12px;border:1px solid #333;border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}
</style>

<div class="submit-layout">
<div class="problem-side">
  <div class="preview-box">
    <h3><?=htmlspecialchars($problem['title'])?></h3>
    <div class="meta"><span><?=$pid?></span> | <span><?=$problem['time_limit']?>s</span> | <span><?=$problem['memory_limit']?>MB</span></div>
    <div style="font-size:12px;color:#aaa;line-height:1.7">
      <?=nl2br(htmlspecialchars(mb_substr(strip_tags($problem['description']),0,300)))?>...
    </div>
  </div>
  <a href="problem.php?id=<?=$pid?>" style="font-size:12px;color:#888;text-decoration:none">← 返回题目</a>
</div>
<div class="submit-side">
  <?php if ($hasData): ?>
  <select id="lang"><option value="python3">Python 3</option><option value="cpp17">C++17</option><option value="cpp20">C++20</option><option value="cpp14">C++14</option><option value="c">C</option></select>
  <textarea id="code" placeholder="在此粘贴代码...">print(input())</textarea>
  <div style="display:flex;gap:8px;margin-bottom:12px">
  <button class="btn-submit" onclick="submitCode()" id="submitBtn" style="flex:1">提交评测</button>
  <button class="btn-submit" onclick="toggleStats()" id="statsBtn" style="width:110px;background:#1a3a5c;color:#5af;border:1px solid #2a5a8c"><i class="fa-solid fa-chart-bar"></i> 统计</button>
  </div>
  <div id="statsPanel" style="display:none;margin-bottom:16px;background:#141414;border:1px solid #2a2a2a;padding:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <b style="font-size:12px;color:#fff"><i class="fa-solid fa-chart-bar"></i> 本题提交统计</b><span style="font-size:11px;color:#888" id="statsCount"></span>
    </div>
    <div id="statsLoading" style="color:#888;font-size:12px;padding:16px;text-align:center">加载中...</div>
    <div id="statsTableWrap" style="display:none;overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:11px">
        <thead><tr>
          <th style="text-align:left;color:#888;padding:6px 8px;border-bottom:1px solid #222">状态</th>
          <th style="text-align:left;color:#888;padding:6px 8px;border-bottom:1px solid #222">用户</th>
          <th style="text-align:left;color:#888;padding:6px 8px;border-bottom:1px solid #222"><a href="javascript:void(0)" onclick="loadStats('time')" style="color:#5af;text-decoration:none">用时⇅</a></th>
          <th style="text-align:left;color:#888;padding:6px 8px;border-bottom:1px solid #222"><a href="javascript:void(0)" onclick="loadStats('memory')" style="color:#5af;text-decoration:none">内存⇅</a></th>
          <th style="text-align:left;color:#888;padding:6px 8px;border-bottom:1px solid #222"><a href="javascript:void(0)" onclick="loadStats('score')" style="color:#5af;text-decoration:none">分数⇅</a></th>
          <th style="text-align:left;color:#888;padding:6px 8px;border-bottom:1px solid #222">语言</th>
          <th style="text-align:left;color:#888;padding:6px 8px;border-bottom:1px solid #222"><a href="javascript:void(0)" onclick="loadStats('date')" style="color:#5af;text-decoration:none">时间⇅</a></th>
        </tr></thead>
        <tbody id="statsBody"></tbody>
      </table>
    </div>
  </div>
  <div id="resultArea" style="margin-top:16px">
    <div id="streamStatus" style="display:none;text-align:center;color:#888;padding:8px;font-size:12px"><span class="spinner"></span><span id="streamMsg"></span></div>
    <div id="scoreFinal" class="score-final"></div>
    <div id="results"></div>
    <div id="errBox" style="color:#ff4f4f;font-size:11px;margin-top:8px"></div>
  </div>
  <?php else: ?>
  <div style="text-align:center;color:#888;padding:60px">暂无测试数据</div>
  <?php endif; ?>
</div>
</div>

<script>
let statsSort='id', statsDir='desc';
async function toggleStats(){
 const p=document.getElementById('statsPanel');
 if(p.style.display==='none'){ p.style.display='block'; document.getElementById('statsLoading').style.display='block'; document.getElementById('statsTableWrap').style.display='none'; loadStats(); }
 else p.style.display='none';
}
async function loadStats(sort){
 if(sort){ if(statsSort===sort){ statsDir = statsDir==='desc'?'asc':'desc'; } else { statsSort=sort; statsDir='desc'; } }
 document.getElementById('statsLoading').style.display='block';
 document.getElementById('statsTableWrap').style.display='none';
 try{
  const r=await fetch('api/problem_stats.php?problem_id=<?=$pid?>&sort='+statsSort+'&dir='+statsDir);
  const d=await r.json();
  document.getElementById('statsCount').textContent='共 '+d.count+' 条';
  const tb=document.getElementById('statsBody'); tb.innerHTML='';
  const colorMap={'AC':'#25ad40','WA':'#ff4f4f','TLE':'#ffab00','RE':'#f8603a','MLE':'#d500f9','OLE':'#0091ea','CE':'#ff9100','SE':'#999','judging':'#09f','waiting':'#666'};
  (d.rows||[]).forEach(x=>{
   const tr=document.createElement('tr');
   const t=parseFloat(x.total_time)||0;
   tr.innerHTML='<td style="padding:6px 8px;border-bottom:1px solid #1a1a1a"><a href="submission.php?id='+x.id+'" style="color:'+(colorMap[x.status]||'#ccc')+';text-decoration:none;font-weight:600">'+x.status+'</a></td>'
    +'<td style="padding:6px 8px;border-bottom:1px solid #1a1a1a"><a href="user.php?name='+encodeURIComponent(x.username)+'" style="color:#ccc;text-decoration:none;display:inline-flex;align-items:center;gap:5px">'+(x.user_avatar?'<img src="'+x.user_avatar+'" style="width:18px;height:18px;border-radius:50%;object-fit:cover">':'<span style="width:18px;height:18px;border-radius:50%;background:#2a3a5c;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700">'+(x.username[0]||'?').toUpperCase()+'</span>')+x.username+'</a></td>'
    +'<td style="padding:6px 8px;border-bottom:1px solid #1a1a1a;font-family:Consolas,monospace;color:#aaa">'+(t>=1?t.toFixed(3)+'s':(t*1000).toFixed(0)+'ms')+'</td>'
    +'<td style="padding:6px 8px;border-bottom:1px solid #1a1a1a;font-family:Consolas,monospace;color:#aaa">'+Number(x.peak_memory).toFixed(1)+' MB</td>'
    +'<td style="padding:6px 8px;border-bottom:1px solid #1a1a1a;color:#fff;font-weight:700">'+x.score+'</td>'
    +'<td style="padding:6px 8px;border-bottom:1px solid #1a1a1a;color:#888">'+x.language+'</td>'
    +'<td style="padding:6px 8px;border-bottom:1px solid #1a1a1a;color:#666;font-size:10px">'+x.created_at+'</td>';
   tb.appendChild(tr);
  });
  if(!d.rows||!d.rows.length) tb.innerHTML='<tr><td colspan="7" style="text-align:center;color:#666;padding:20px">暂无提交记录</td></tr>';
  document.getElementById('statsLoading').style.display='none';
  document.getElementById('statsTableWrap').style.display='block';
 }catch(e){
  document.getElementById('statsLoading').textContent='加载失败: '+e.message;
 }
}
async function submitCode(){
 const b=document.getElementById('submitBtn');b.disabled=true;b.textContent='评测中...';
document.getElementById('results').innerHTML='';document.getElementById('scoreFinal').textContent='';document.getElementById('errBox').innerHTML='';
const ss=document.getElementById('streamStatus');ss.style.display='block';document.getElementById('streamMsg').textContent='等待中...';
try{
 const body={language:document.getElementById('lang').value,code:document.getElementById('code').value,
  problem_id:'<?=$pid?>',time_limit:<?=$problem['time_limit']?>,memory_limit:<?=$problem['memory_limit']?>};
 const r1=await fetch('api/submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
 const j1=await r1.json();window.location.href='submission.php?id='+j1.submission_id;return;
 const es=new EventSource('<?=$JUDGE_URL?>/stream/'+taskId);
 es.onmessage=function(e){const r=JSON.parse(e.data);if(r._interim){document.getElementById('streamMsg').textContent=r._interim;return}addResult(r)};
 es.addEventListener('done',function(e){es.close();ss.style.display='none';
  fetch('<?=$JUDGE_URL?>/result/'+taskId).then(r=>r.json()).then(d=>{
   document.getElementById('scoreFinal').innerHTML=d.score+' / '+(d.max_score||100)+'<br><a href=\"submission.php?id='+subId+'\" style=\"font-size:12px;color:#888\">查看记录 #'+subId+'</a>';
   fetch('api/update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({task_id:taskId,result:d})});
  });
 });
 es.onerror=function(){es.close();ss.style.display='none';
  fetch('<?=$JUDGE_URL?>/result/'+taskId).then(r=>r.json()).then(d=>{
   document.getElementById('scoreFinal').innerHTML=d.score+' / '+(d.max_score||100)+'<br><a href=\"submission.php?id='+subId+'\" style=\"font-size:12px;color:#888\">查看记录 #'+subId+'</a>';
   fetch('api/update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({task_id:taskId,result:d})});
  });
 };
}catch(e){document.getElementById('errBox').innerHTML='错误: '+e.message}
finally{b.disabled=false;b.textContent='提交评测'}
}
function addResult(r){
 if(document.getElementById('res-'+r.test_case_index))return;
 const v=(r.verdict||'SE').toLowerCase();
 const d=document.createElement('div');d.id='res-'+r.test_case_index;d.className='result-card '+v;
 d.innerHTML='<span class="verdict verdict-'+v+'">'+(r.verdict||'SE')+'</span><span style="flex:1;font-size:11px;color:#888">#'+(r.test_case_index+1)+' | '+((r.time_used||0).toFixed(3))+'s | Score: '+r.score+'</span>';
 document.getElementById('results').appendChild(d);
}
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
