<?php
require __DIR__ . '/inc/config.php';
$username = $_GET['name'] ?? '';
require __DIR__ . '/inc/auth.php';
$s = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$s->execute([$username]); $profile = $s->fetch();
if (!$profile) die("User not found");

$stats = $pdo->prepare("
    SELECT problem_id, 
           MAX(CASE WHEN status='AC' THEN score ELSE 0 END) AS best_score,
           MAX(max_score) AS max_score,
           COUNT(*) AS attempts,
           SUM(CASE WHEN status='AC' THEN 1 ELSE 0 END) AS ac_count
    FROM submissions 
    WHERE username = ?
    GROUP BY problem_id ORDER BY problem_id
");
$stats->execute([$username]);
$rows = $stats->fetchAll();

$acList = []; $tryList = [];
foreach ($rows as $r) {
    if ($r['ac_count'] > 0) $acList[] = $r;
    else $tryList[] = $r;
}

$totalSubs = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE username = ?");
$totalSubs->execute([$username]); $totalSubs = $totalSubs->fetchColumn();

$acSubs = $pdo->prepare("SELECT COUNT(DISTINCT problem_id) FROM submissions WHERE username = ? AND status='AC'");
$acSubs->execute([$username]); $acSubs = $acSubs->fetchColumn();

// 提交记录（最近50条）
$subs = $pdo->prepare("SELECT id, problem_id, status, score, language, created_at FROM submissions WHERE username=? ORDER BY id DESC LIMIT 50");
$subs->execute([$username]); $recentSubs = $subs->fetchAll();
// 该用户发表的文章
require_once __DIR__ . '/inc/article_tables.php';
$arts = $pdo->prepare("SELECT id, title, is_announcement, is_public, created_at FROM articles WHERE author=? AND is_solution=0 ORDER BY id DESC LIMIT 50");
$arts->execute([$username]); $userArts = $arts->fetchAll();

// 状态颜色
$colorMap = ['AC'=>'#25ad40','WA'=>'#ff4f4f','TLE'=>'#ffab00','RE'=>'#f8603a','MLE'=>'#d500f9','OLE'=>'#0091ea','CE'=>'#ff9100','SE'=>'#999','judging'=>'#09f','waiting'=>'#999','compiling'=>'#ffab00'];

$isOwner = isLoggedIn() && currentUser()['id'] == $profile['id'];

// 我的文件（仅本人）
$myFiles = []; $usedBytes = 0;
if ($isOwner) {
    $mf = $pdo->prepare("SELECT * FROM user_files WHERE username=? ORDER BY id DESC");
    $mf->execute([$username]); $myFiles = $mf->fetchAll();
    $ub = $pdo->prepare("SELECT COALESCE(SUM(size),0) FROM user_files WHERE username=?");
    $ub->execute([$username]); $usedBytes = intval($ub->fetchColumn());
}
$pageTitle = $username . ' - Zxt Super OJ';
require __DIR__ . '/inc/header.php';
?>
<style>
.profile-header{display:flex;gap:24px;align-items:start;margin-bottom:32px}
.avatar-box{width:50px;height:50px;border:1px solid #333;overflow:hidden;flex-shrink:0}
.avatar-box img{width:100%;height:100%;object-fit:cover}
.avatar-default{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#000;color:#999;font-size:20px;font-weight:700}
.profile-info{flex:1}
.profile-info h1{font-size:18px;color:#fff;font-weight:400;margin-bottom:4px;letter-spacing:1px}
.profile-info .motto{font-size:12px;color:#999;margin-bottom:12px;font-style:italic}
.profile-info .motto:empty::after{content:'暂无格言.'}
.profile-stats{display:flex;gap:24px;font-size:12px;color:#999}
.profile-stats b{color:#ccc}
.edit-link{font-size:11px;color:#999;text-decoration:none;border:1px solid #333;padding:3px 10px;margin-left:8px}
.edit-link:hover{color:#fff;border-color:#999}
.section-title{font-size:13px;color:#fff;font-weight:400;margin:24px 0 12px;letter-spacing:1px}
.p-table{width:100%;border-collapse:collapse;font-size:12px}
.p-table th,.p-table td{padding:8px 12px;text-align:left;border-bottom:1px solid #111}
.p-table th{color:#999;font-weight:400;font-size:10px;text-transform:uppercase;letter-spacing:1px}
.p-table tr:hover td{background:#1a1a1a}
.p-table a{color:#ccc;text-decoration:none}.p-table a:hover{color:#fff}
.ac-badge{color:#0c0;font-size:11px}.no-ac{color:#999;font-size:11px}
.empty{text-align:center;color:#999;padding:20px;font-size:12px}
.tabs{display:flex;gap:0;border-bottom:1px solid #222;margin-bottom:20px}
.tabs .tab{padding:10px 22px;color:#999;cursor:pointer;font-size:13px;border-bottom:2px solid transparent;transition:all .15s;user-select:none}
.tabs .tab:hover{color:#fff}
.tabs .tab.active{color:#fff;border-bottom-color:#5af}
.tab-panel{display:none}
.tab-panel.active{display:block}
.avatar-upload{width:60px;height:60px;border:1px solid #333;border-radius:50%;overflow:hidden;cursor:pointer;position:relative}
.avatar-upload img{width:100%;height:100%;object-fit:cover}
.avatar-upload .overlay{position:absolute;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;font-size:10px;color:#ccc;opacity:0;transition:.15s}
.avatar-upload:hover .overlay{opacity:1}
</style>

<div class="profile-header">
  <div class="avatar-box">
    <?php if ($profile['avatar']): ?><img src="<?=htmlspecialchars($profile['avatar'])?>" alt="">
    <?php else: ?><div class="avatar-default"><?=strtoupper(substr($username,0,1))?></div><?php endif ?>
  </div>
  <div class="profile-info">
    <h1><span style="color:<?=userColor($profile['role'])?>"><?=htmlspecialchars($username)?></span><?php if ($profile['tag']): ?><span class="utag" style="background:<?=userColor($profile['role'])?>;color:#fff"><?=htmlspecialchars($profile['tag'])?></span><?php endif; ?><?php if ($isOwner): ?><a href="profile.php" class="edit-link">编辑</a><?php endif ?></h1>
    <div class="motto"><?=htmlspecialchars($profile['motto'])?></div>
    <?php
    $canSetTag = false;
    if (isLoggedIn()) {
        $meTag = currentUser();
        $rlTag = ['super_admin'=>3,'admin'=>2,'user'=>1];
        if (in_array($meTag['role'], ['super_admin','admin'])) {
            $myL = $rlTag[$meTag['role']]; $tL = $rlTag[$profile['role']] ?? 1;
            if ($meTag['username'] === $username || $tL < $myL) $canSetTag = true;
        }
    }
    ?>
    <?php if ($canSetTag): ?>
    <div style="margin-top:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <input id="tagInput" class="no-float" placeholder="设置标签(最多5字)" value="<?=htmlspecialchars($profile['tag']??'')?>" maxlength="5" style="width:130px;padding:5px 8px;background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#ddd;font-size:12px;outline:none">
      <button class="btn btn-sm" onclick="setTag()">设置</button>
      <button class="btn btn-sm" onclick="clearTag()" <?=($profile['tag']??'')?'':'disabled'?>>清除</button>
      <span id="tagMsg" style="font-size:11px;color:#999"></span>
    </div>
    <?php endif; ?>
    <div class="profile-stats">
      <span>提交数: <b><?=$totalSubs?></b></span>
      <span>通过: <b><?=$acSubs?></b></span>
      <span>角色: <b><?=$profile['role']?></b></span>
    </div>
  </div>
</div>

<div class="tabs">
  <div class="tab active" data-tab="practice" onclick="showTab('practice')">练习情况</div>
  <div class="tab" data-tab="subs" onclick="showTab('subs')">提交记录</div>
  <div class="tab" data-tab="articles" onclick="showTab('articles')">文章</div>
  <?php if ($isOwner): ?><div class="tab" data-tab="files" onclick="showTab('files')">我的文件</div><div class="tab" data-tab="settings" onclick="showTab('settings')">信息设置</div><?php endif; ?>
</div>

<!-- 练习情况 -->
<div id="panel-practice" class="tab-panel active">
  <?php if ($acList): ?>
  <div class="section-title">已通过 (<?=count($acList)?>)</div>
  <table class="p-table"><tr><th>题目</th><th>分数</th><th>尝试次数</th></tr>
  <?php foreach($acList as $p): ?>
  <tr><td><a href="problem.php?id=<?=$p['problem_id']?>"><?=$p['problem_id']?></a></td><td><span class="ac-badge"><?=$p['best_score']?>/<?=$p['max_score']?></span></td><td><?=$p['attempts']?></td></tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
  <?php if ($tryList): ?>
  <div class="section-title">尝试中 (<?=count($tryList)?>)</div>
  <table class="p-table"><tr><th>题目</th><th>最高分</th><th>尝试次数</th></tr>
  <?php foreach($tryList as $p): ?>
  <tr><td><a href="problem.php?id=<?=$p['problem_id']?>"><?=$p['problem_id']?></a></td><td><?=$p['best_score']?>/<?=$p['max_score']?></td><td><?=$p['attempts']?></td></tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
  <?php if (!$acList && !$tryList): ?><div class="empty">暂无提交记录.</div><?php endif ?>
</div>

<!-- 提交记录 -->
<div id="panel-subs" class="tab-panel">
  <?php if ($recentSubs): ?>
  <table class="p-table"><tr><th>#</th><th>题目</th><th>状态</th><th>分数</th><th>语言</th><th>时间</th></tr>
  <?php foreach($recentSubs as $sub): $sc=strtolower($sub['status']); ?>
  <tr>
    <td><a href="submission.php?id=<?=$sub['id']?>"><?=$sub['id']?></a></td>
    <td><a href="problem.php?id=<?=$sub['problem_id']?>"><?=$sub['problem_id']?></a></td>
    <td style="color:<?=$colorMap[$sub['status']] ?? '#999'?>"><?=$sub['status']?></td>
    <td><?=$sub['score']?></td>
    <td><?=$sub['language']?></td>
    <td style="color:#666;font-size:11px"><?=date('m-d H:i', strtotime($sub['created_at']))?></td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php else: ?><div class="empty">暂无提交记录.</div><?php endif ?>
</div>

<!-- 文章 -->
<div id="panel-articles" class="tab-panel">
  <?php if ($userArts): ?>
  <?php foreach($userArts as $a): ?>
  <div class="card" style="padding:12px 16px;margin-bottom:8px">
    <a href="article.php?id=<?=$a['id']?>" style="color:#fff;text-decoration:none;font-size:13px;font-weight:600"><?=htmlspecialchars($a['title'])?></a>
    <?php if ($a['is_announcement']): ?><span style="font-size:10px;color:#ffab00;margin-left:6px">公告</span><?php endif; ?>
    <span style="font-size:10px;color:<?=$a['is_public']?'#0c0':'#c90'?>;margin-left:6px"><?=$a['is_public']?'公开':'私密'?></span>
    <div style="font-size:11px;color:#666;margin-top:4px"><?=date('Y-m-d H:i', strtotime($a['created_at']))?></div>
  </div>
  <?php endforeach ?>
  <?php else: ?><div class="empty">暂无文章.</div><?php endif ?>
</div>

<?php if ($isOwner): ?>
<!-- 我的文件 -->
<div id="panel-files" class="tab-panel">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
    <span style="font-size:12px;color:#888">已用空间: <b style="color:#fff"><?=number_format($usedBytes/1048576, 1)?>MB</b> / 256MB</span>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="file" id="fileInput" style="font-size:12px;color:#999">
      <button class="btn btn-sm" onclick="uploadFile()">上传</button>
      <span id="fileMsg" style="font-size:11px;color:#999"></span>
    </div>
  </div>
  <progress id="fileProg" value="0" max="100" style="width:100%;height:4px;display:none;margin-bottom:12px;accent-color:#5af;border:none;background:#222"></progress>
  <?php if ($myFiles): ?>
  <table class="p-table"><tr><th>文件名</th><th>大小</th><th>时间</th><th></th></tr>
  <?php foreach($myFiles as $f): ?>
  <tr>
    <td style="word-break:break-all"><?=htmlspecialchars($f['filename'])?></td>
    <td><?= $f['size']>=1048576 ? number_format($f['size']/1048576,1).'MB' : number_format($f['size']/1024,1).'KB' ?></td>
    <td style="color:#666;font-size:11px"><?=date('m-d H:i', strtotime($f['created_at']))?></td>
    <td style="white-space:nowrap">
      <a class="btn btn-sm" href="api/file_download.php?id=<?=$f['id']?>">下载</a>
      <button class="btn btn-sm" onclick="copyFileLink(<?=$f['id']?>, this)">复制下载链接</button>
      <button class="btn btn-sm btn-danger" onclick="delFile(<?=$f['id']?>, this)">删除</button>
    </td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php else: ?><div class="empty">还没有上传文件.</div><?php endif ?>
</div>

<!-- 信息设置 -->
<div id="panel-settings" class="tab-panel">
  <div style="display:flex;gap:28px;align-items:start;flex-wrap:wrap">
    <div>
      <div style="font-size:12px;color:#888;margin-bottom:8px">头像</div>
      <div class="avatar-upload" onclick="document.getElementById('af').click()">
        <img id="avImg" src="<?=htmlspecialchars($profile['avatar'] ?? '')?>" alt="">
        <div class="overlay">更换</div>
        <input type="file" id="af" accept="image/*" style="display:none" onchange="uploadAvatar(this)">
      </div>
      <div style="font-size:10px;color:#666;margin-top:5px">点击更换头像</div>
    </div>
    <div style="flex:1;min-width:280px">
      <div style="font-size:12px;color:#888;margin-bottom:8px">格言</div>
      <form method="POST" action="profile.php">
        <textarea name="motto" rows="3" placeholder="写点格言吧..." style="width:100%;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#ddd;font-size:13px;padding:10px;outline:none;resize:vertical"><?=htmlspecialchars($profile['motto'] ?? '')?></textarea>
        <button class="btn btn-sm" style="margin-top:8px">保存格言</button>
      </form>
    </div>
  </div>
  <div style="margin-top:16px;padding-top:14px;border-top:1px solid #222">
    <a href="profile.php" class="btn btn-line btn-sm">完整编辑资料 →</a>
  </div>
</div>
<?php endif; ?>

<script>
function showTab(name){
  document.querySelectorAll('.tabs .tab').forEach(t=>t.classList.toggle('active', t.dataset.tab===name));
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.toggle('active', p.id==='panel-'+name));
}
async function uploadAvatar(input){
  if(!input.files[0]) return;
  const f=new FormData(); f.append('avatar', input.files[0]);
  try{
    const r=await fetch('api/avatar.php',{method:'POST',body:f});
    let d; try{ d=await r.json(); }catch(e){ ztAlert('服务器异常','err'); return; }
    if(d.ok){ document.getElementById('avImg').src=d.avatar; ztAlert('头像已更新','ok'); }
    else ztAlert(d.message||'上传失败','err');
  }catch(e){ ztAlert('上传失败','err'); }
}
// ===== 我的文件：分片并行上传（快速通道）=====
const FCHUNK = 4*1024*1024, FCONC = 2;
function calcFileMD5(file){
  return new Promise(function(resolve){
    var chunks=Math.ceil(file.size/2097152), spark=new SparkMD5.ArrayBuffer, idx=0, reader=new FileReader;
    reader.onload=function(e){ spark.append(e.target.result); idx++; if(idx<chunks) loadNext(); else resolve(spark.end()); };
    function loadNext(){ reader.readAsArrayBuffer(file.slice(idx*2097152,(idx+1)*2097152)); }
    loadNext();
  });
}
async function uploadFile(){
  const inp=document.getElementById('fileInput'), msg=document.getElementById('fileMsg'), prog=document.getElementById('fileProg');
  const f=inp.files[0];
  if(!f){ msg.textContent='请选择文件'; return; }
  prog.style.display='block'; prog.value=0; msg.textContent='计算MD5...';
  const total=Math.ceil(f.size/FCHUNK);
  const md5=await calcFileMD5(f);
  msg.textContent='检查断点...';
  let cj={exist:[]};
  try{
    const r=await fetch('api/fchunk_check.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({md5:md5,name:f.name})});
    cj=await r.json();
  }catch(e){}
  const exist=new Set((cj.exist||[]).map(x=>parseInt(x)));
  let done=exist.size;
  const tasks=[]; for(let i=0;i<total;i++) if(!exist.has(i)) tasks.push(i);
  if(tasks.length===0){ msg.textContent='已上传过，合并中...'; return merge(); }
  prog.value=Math.round(done/total*100); msg.textContent='上传中 0% ('+done+'/'+total+')';
  let ti=0;
  async function worker(){
    while(ti<tasks.length){
      const i=tasks[ti++];
      const blob=f.slice(i*FCHUNK, Math.min((i+1)*FCHUNK, f.size));
      let ok=false;
      for(let attempt=0; attempt<3 && !ok; attempt++){
        try{
          await new Promise(function(res,rej){
            const fd=new FormData(); fd.append('file',blob); fd.append('md5',md5); fd.append('index',i);
            const xhr=new XMLHttpRequest(); xhr.open('POST','api/fchunk_upload.php');
            xhr.onload=function(){ if(xhr.status>=200&&xhr.status<300){ ok=true; res(); } else rej(); };
            xhr.onerror=function(){ rej(); };
            xhr.send(fd);
          });
        }catch(e){
          if(attempt<2){ msg.textContent='分片'+i+'重试中 ('+(attempt+1)+'/3)...'; await new Promise(r=>setTimeout(r,1000*Math.pow(2,attempt))); }
        }
      }
      if(!ok) throw new Error('chunk '+i+' failed');
      done++; prog.value=Math.round(done/total*100); msg.textContent='上传中 '+Math.round(done/total*100)+'% ('+done+'/'+total+')';
    }
  }
  try{
    await Promise.all(Array.from({length:Math.min(FCONC,tasks.length)},function(){return worker();}));
  }catch(e){ msg.textContent='部分分片失败，点击重试可续传'; return; }
  await merge();
  async function merge(){
    msg.textContent='合并中...';
    try{
      const r=await fetch('api/fchunk_merge.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({md5:md5,name:f.name,total:total})});
      const d=await r.json();
      if(d.ok){ prog.value=100; msg.textContent=d.message; setTimeout(()=>location.reload(),500); }
      else msg.textContent=d.message||'合并失败';
    }catch(e){ msg.textContent='合并失败'; }
  }
}
// 复制文本（http 非安全上下文用 fallback）
function copyText(text, btn){
  function done(ok){
    if(btn){ btn.textContent=ok?'已复制':'复制失败'; setTimeout(function(){ btn.textContent='复制下载链接'; },1500); }
    ztAlert(ok?'已复制下载链接':'复制失败', ok?'ok':'err');
  }
  if(navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(text).then(function(){ done(true); }).catch(function(){ done(fallback()); });
  } else {
    done(fallback());
  }
  function fallback(){
    try{
      const ta=document.createElement('textarea');
      ta.value=text; ta.style.position='fixed'; ta.style.left='-9999px';
      document.body.appendChild(ta); ta.select();
      const ok=document.execCommand('copy');
      document.body.removeChild(ta);
      return ok;
    }catch(e){ return false; }
  }
}
async function copyFileLink(id, btn){
  copyText(location.origin + '/api/file_download.php?id=' + id, btn);
}
async function delFile(id, btn){
  if(!confirm('确定删除这个文件？')) return;
  const fd=new FormData(); fd.append('id',id);
  const r=await fetch('api/file_delete.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok) location.reload();
  else ztAlert(d.message||'删除失败','err');
}
async function setTag(){
  const inp=document.getElementById('tagInput'), msg=document.getElementById('tagMsg');
  const tag=inp.value.trim();
  if(tag.length>5){ msg.textContent='最多5个字'; return; }
  const fd=new FormData(); fd.append('username',<?=json_encode($username)?>); fd.append('tag',tag);
  try{
    const r=await fetch('api/set_tag.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){ msg.textContent=d.message; setTimeout(()=>location.reload(),500); }
    else msg.textContent=d.message||'失败';
  }catch(e){ msg.textContent='失败'; }
}
async function clearTag(){ const inp=document.getElementById('tagInput'); if(inp) inp.value=''; await setTag(); }
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
