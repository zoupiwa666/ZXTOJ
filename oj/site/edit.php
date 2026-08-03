<?php
require __DIR__.'/inc/config.php'; require __DIR__.'/inc/auth.php'; requireRole('admin');
$pid = $_GET['id'] ?? ''; $isNew = empty($pid); $msg = ''; $problem = null;

if (!$isNew) {
    $stmt = $pdo->prepare("SELECT * FROM problems WHERE problem_id = ?"); $stmt->execute([$pid]); $problem = $stmt->fetch();
    if (!$problem) die("Not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_problem') {
        $title=$_POST['title']??'';$bg=$_POST['background']??'';$desc=$_POST['description']??'';
        $inf=$_POST['input_format']??'';$outf=$_POST['output_format']??'';$hint=$_POST['hints']??'';
        $tl=floatval($_POST['time_limit']??2);$ml=intval($_POST['memory_limit']??128);
        $vis=$_POST['visibility']??'public';
        if ($isNew) {
            $newPid=trim($_POST['problem_id']??'');
            if(!$newPid){$msg='需要题目编号';goto end;}
            $s=$pdo->prepare("SELECT id FROM problems WHERE problem_id=?");$s->execute([$newPid]);
            if($s->fetch()){$msg='编号已存在';goto end;}
            $pdo->prepare("INSERT INTO problems (problem_id,title,background,description,input_format,output_format,hints,time_limit,memory_limit,created_by,visibility) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$newPid,$title,$bg,$desc,$inf,$outf,$hint,$tl,$ml,currentUser()['username'],$vis]);
            $pid=$newPid;$isNew=false;
        } else {
            $pdo->prepare("UPDATE problems SET title=?,background=?,description=?,input_format=?,output_format=?,hints=?,time_limit=?,memory_limit=?,visibility=? WHERE problem_id=?")
                ->execute([$title,$bg,$desc,$inf,$outf,$hint,$tl,$ml,$vis,$pid]);
        }
        // 同步时限到数据目录 config.yaml（评测时以 config.yaml 为准）
        $dataDir = "/data/problems/$pid";
        @mkdir($dataDir, 0777, true);
        $cfgPath = "$dataDir/config.yaml";
        $cfg = file_exists($cfgPath) ? file_get_contents($cfgPath) : "";
        if (preg_match('/^time_limit\s*:/m', $cfg)) {
            $cfg = preg_replace('/^time_limit\s*:.*$/m', "time_limit: $tl", $cfg);
        } else { $cfg .= ($cfg==='' ? "" : "\n") . "time_limit: $tl\n"; }
        if (preg_match('/^memory_limit\s*:/m', $cfg)) {
            $cfg = preg_replace('/^memory_limit\s*:.*$/m', "memory_limit: $ml", $cfg);
        } else { $cfg .= "memory_limit: $ml\n"; }
        // 目录可写即可删除重建，规避 root 属主文件不可写问题
        if (!@file_put_contents($cfgPath, $cfg)) {
            @unlink($cfgPath);
            file_put_contents($cfgPath, $cfg);
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
</style>
<a href="problems.php" style="font-size:12px;color:#999;text-decoration:none;display:block;margin-bottom:16px">← 返回题库</a>
<h1 class="page-title"><?=$isNew?'新建题目':'编辑: '.$pid?></h1>
<?php if($msg):?><div class="msg"><?=$msg?></div><?php endif?>

<!-- 题面 -->
<form method="POST"><input type="hidden" name="action" value="save_problem">
<div class="card"><h3>题目信息</h3>
<?php if($isNew):?><label>题目编号</label><input name="problem_id" placeholder="例如: P1000" required><?php endif?>
<label>标题</label><input name="title" value="<?=htmlspecialchars($problem['title']??'')?>" required>
<div class="row">
  <div><label>时间限制 (s)</label><input type="number" step="0.5" name="time_limit" value="<?=$problem['time_limit']??2.0?>"></div>
  <div><label>内存限制 (MB)</label><input type="number" name="memory_limit" value="<?=$problem['memory_limit']??128?>"></div>
  <div><label>可见性</label><select name="visibility"><option value="public" <?=($problem['visibility']??'public')==='public'?'selected':''?>>公开</option><option value="hidden" <?=($problem['visibility']??'')==='hidden'?'selected':''?>>隐藏</option></select></div>
</div>
<?php foreach(['background'=>'题目背景','description'=>'题目描述','input_format'=>'输入格式','output_format'=>'输出格式','hints'=>'提示'] as $k=>$lb):?>
<label><?=$lb?> (Markdown + LaTeX)</label><textarea name="<?=$k?>" rows="3"><?=htmlspecialchars($problem[$k]??'')?></textarea>
<?php endforeach?>
<button class="btn">保存题目</button>
</div></form>

<?php if(!$isNew):?>
<!-- 导入 -->
<div class="card"><h3>导入数据包 (已有 <span id="tcCount"><?=$tcCount?></span> 个测试点)</h3>
<label class="file-zone" id="dz"><div style="font-size:20px">+</div><div style="font-size:12px">拖拽或点击上传 .zip / .tar.gz</div><div style="font-size:11px;color:#999" id="fn">未选择</div><input type="file" name="package" accept=".zip,.tar.gz,.tgz,.tar" id="pf"></label>
<button class="btn" style="margin-top:12px" onclick="uploadPackage()" id="importBtn">标准上传</button> <button class="btn" style="margin-top:12px;background:#1a3a5c;color:#5af" onclick="directUpload()" id="directBtn">直传</button> <button class="btn" style="margin-top:12px;background:#2a5a3c;color:#6c6" onclick="downloadPackage()" id="dlBtn">下载数据包</button>
<div id="dlStatus" style="margin-top:6px;font-size:12px;color:#999"></div>
<progress id="dlProgress" value="0" max="100" style="width:100%;height:4px;display:none;margin-top:6px;accent-color:#6c6;border:none;background:#222"></progress>
<div style="margin-top:12px;display:flex;gap:8px">
  <input id="serverPath" placeholder="服务器路径或下载链接: /tmp/a.zip 或 https://...zip" style="flex:1;font-size:12px" onkeydown="if(event.key==='Enter')importServerPath()">
  <button class="btn btn-sm" onclick="importServerPath()">路径导入</button>
</div>
<div id="pathStatus" style="margin-top:4px;font-size:12px;color:#999"></div>
<progress id="progressBar" value="0" max="100" style="width:100%;height:4px;display:none;margin-top:8px;accent-color:#5af;border:none;background:#222"></progress>
<div id="progressText" style="font-size:11px;color:#5af;text-align:center;display:none"></div>
<div id="importStatus" style="margin-top:4px;font-size:12px;color:#999"></div>
</div>

<!-- 样例 -->
<form method="POST"><input type="hidden" name="action" value="save_samples">
<div class="card"><h3>样例</h3>
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

<!-- 权限 -->
<form method="POST"><input type="hidden" name="action" value="grant_user">
<div class="card"><h3>访问权限</h3>
<div style="display:flex;gap:8px;margin-bottom:12px"><input name="grant_username" placeholder="用户名 或 team->组名" style="flex:1"><button class="btn btn-sm">授权</button></div>
<?php $perms=$pdo->prepare("SELECT * FROM problem_permissions WHERE problem_id=?");$perms->execute([$pid]);$plist=$perms->fetchAll();if($plist):?>
<table style="width:100%;font-size:11px;color:#888">
<?php foreach($plist as $pm):?>
<tr><td><?= userBadge($pm['username'], null, 16) ?></td><td style="color:#666">by <?= userBadge($pm['granted_by'], null, 14) ?></td><td>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="revoke_user"><input type="hidden" name="perm_id" value="<?=$pm['id']?>"><button class="btn-sm btn-danger">撤销</button></form></td></tr>
<?php endforeach?>
</table>
<?php endif?>
</div></form>
<?php endif?>

<script>
function addSample(){const n=document.querySelectorAll('#samples>div').length;const d=document.createElement('div');d.style.cssText='background:#141414;border:1px solid #222;padding:12px;margin-bottom:8px';d.innerHTML='<div style="display:flex;justify-content:space-between;margin-bottom:6px"><b style="font-size:12px">样例 #'+(n+1)+'</b><button class="btn-sm btn-danger" onclick="this.closest(\'div\').remove()">删除</button></div><div class="row"><div><label>输入</label><textarea name="s_input[]" rows="3"></textarea></div><div><label>输出</label><textarea name="s_output[]" rows="3"></textarea></div></div>';document.getElementById('samples').appendChild(d)}
if(!document.querySelectorAll('#samples>div').length)addSample();
const dz=document.getElementById('dz'),pf=document.getElementById('pf'),fn=document.getElementById('fn');dz.addEventListener('click',()=>pf.click());pf.addEventListener('change',()=>fn.textContent=pf.files[0]?.name||'未选择');
function uploadPackage(){
 const f=document.getElementById('pf').files[0];if(!f)return;
 const b=document.getElementById('importBtn');b.disabled=true;
 const st=document.getElementById('importStatus');
 const pb=document.getElementById('progressBar');
 const pt=document.getElementById('progressText');
 st.innerHTML='';pb.style.display='block';pt.style.display='block';pt.textContent='0%';pb.value=0;
 const fd=new FormData();fd.append('package',f);fd.append('problem_id','<?=$pid?>');
 const xhr=new XMLHttpRequest();
 xhr.upload.onprogress=function(e){
  if(e.lengthComputable){const pct=Math.round(e.loaded/e.total*100);pb.value=pct;pt.textContent=pct+'% ('+formatSize(e.loaded)+' / '+formatSize(e.total)+')';}
 };
 xhr.onload=function(){
  pb.style.display='none';pt.style.display='none';
  try{const d=JSON.parse(xhr.responseText);
   if(d.ok&&d.path){document.getElementById('serverPath').value=d.path;st.innerHTML='<span style="color:#0c0">已保存: '+d.path+' ('+formatSize(d.size)+')</span>';importServerPath();}
   else{st.innerHTML='<span style="color:#c00">'+d.message+'</span>';}
  }catch(e){st.innerHTML='<span style="color:#c00">错误</span>'}
  b.disabled=false;b.textContent='导入';
 };
 xhr.onerror=function(){pb.style.display='none';pt.style.display='none';st.innerHTML='<span style="color:#c00">上传中断</span> <button onclick="uploadPackage()" style="font-size:11px;background:#2a2a2a;color:#ccc;border:1px solid #333;cursor:pointer;padding:2px 10px">重试</button>';b.disabled=false;b.textContent='导入';};
 xhr.ontimeout=function(){pb.style.display='none';pt.style.display='none';st.innerHTML='<span style="color:#c00">上传超时</span> <button onclick="uploadPackage()" style="font-size:11px;background:#2a2a2a;color:#ccc;border:1px solid #333;cursor:pointer;padding:2px 10px">重试</button>';b.disabled=false;b.textContent='导入';};
 xhr.timeout=3600000;xhr.open('POST','api/upload_package.php');xhr.send(fd);
}
async function importServerPath(){
 const path=document.getElementById('serverPath').value.trim();if(!path)return;
 const st=document.getElementById('pathStatus');st.innerHTML='<span class="spinner"></span>处理中...';
 try{
  const fd=new FormData();fd.append("server_path",path);fd.append("problem_id","<?=$pid?>");
  const r=await fetch('api/import_by_path.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){st.innerHTML='<span style="color:#0c0">'+d.message+'</span> 3秒后刷新...';setTimeout(()=>location.reload(),3000);}
  else{st.innerHTML='<span style="color:#c00">'+d.message+'</span>';}
 }catch(e){st.innerHTML='<span style="color:#c00">失败</span>';}
}
const CHUNK_SIZE=5*1024*1024, MAX_CONCURRENT=5, CHECK_URL='/api/check.php', CHUNK_URL='/api/chunk.php', MERGE_URL='/api/merge.php';
async function directUpload(){
 const f=document.getElementById("pf").files[0];if(!f)return;
 const b=document.getElementById("directBtn");b.disabled=true;b.textContent="准备...";
 const st=document.getElementById("importStatus"),pb=document.getElementById("progressBar"),pt=document.getElementById("progressText");
 st.innerHTML="";pb.style.display="block";pt.style.display="block";pb.max=f.size;
 // 计算MD5
 b.textContent='计算MD5...'; const md5=await calcMD5(f).catch(e=>{st.innerHTML='<span style="color:#c00">MD5失败: '+e.message+'</span>';b.disabled=false;b.textContent='直传';throw e}); if(!md5)return;
 b.textContent="检查...";
 // 查询已有分片
 const ck=await fetch(CHECK_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({md5,name:f.name})});
 const cj=await ck.json();
 if(cj.instant){pb.value=f.size;pt.textContent="秒传!";st.innerHTML='<span style="color:#0c0">秒传成功</span>';document.getElementById("serverPath").value=cj.path;importServerPath();b.disabled=false;b.textContent="直传";return}
 const exist=new Set(cj.exist.map(x=>parseInt(x)));
 const total=Math.ceil(f.size/CHUNK_SIZE);
 let uploaded=exist.size*CHUNK_SIZE;
 pb.value=uploaded;pt.textContent=Math.round(uploaded/f.size*100)+"%";
 // 生成待传分片列表
 const tasks=[];
 for(let i=0;i<total;i++){if(!exist.has(i))tasks.push(i)}
 // 并发上传
 let active=0,done=0;
 async function uploadChunk(i){
  const start=i*CHUNK_SIZE,end=Math.min(start+CHUNK_SIZE,f.size);
  const blob=f.slice(start,end);
  const fd=new FormData();fd.append('file',blob);fd.append('md5',md5);fd.append('index',i);
  for(let retry=0;retry<3;retry++){
   try{
    const r=await fetch(CHUNK_URL,{method:'POST',body:fd});
    if(r.ok){done++;uploaded+=blob.size;pb.value=uploaded;pt.textContent=Math.round(uploaded/f.size*100)+"% "+done+"/"+total;return}
   }catch(e){await new Promise(r=>setTimeout(r,1000*Math.pow(2,retry)))}
  }
  throw new Error("chunk "+i+" failed")
 }
 b.textContent="上传中...";
 // 并发 worker 池：传完所有分片（修复只传前3片的bug）
 let ti=0;
 async function worker(){while(ti<tasks.length){const i=tasks[ti++];await uploadChunk(i)}}
 await Promise.all(Array.from({length:Math.min(MAX_CONCURRENT,tasks.length)},()=>worker()));
 // 合并
 const mr=await fetch(MERGE_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({md5,name:f.name,total})});
 const mj=await mr.json();
 if(mj.path){document.getElementById("serverPath").value=mj.path;st.innerHTML='<span style="color:#0c0">完成 '+formatSize(mj.size)+'</span>';importServerPath()}
 else{st.innerHTML='<span style="color:#c00">合并失败</span>'}
 b.disabled=false;b.textContent="直传";
}
async function calcMD5(file){
 return new Promise(resolve=>{
  const chunks=Math.ceil(file.size/2097152),spark=new SparkMD5.ArrayBuffer;
  let idx=0;const reader=new FileReader;
  reader.onload=e=>{spark.append(e.target.result);idx++;if(idx<chunks)loadNext();else resolve(spark.end())};
  function loadNext(){const el=document.getElementById('progressText');if(el)el.textContent='MD5 '+Math.round(idx/chunks*100)+'%'; reader.readAsArrayBuffer(file.slice(idx*2097152,(idx+1)*2097152))}
  loadNext()
 })
}
function formatSize(b){return b<1024?b+'B':b<1048576?(b/1024).toFixed(1)+'KB':(b/1048576).toFixed(1)+'MB'}

async function downloadPackage(){
 const btn=document.getElementById('dlBtn'),st=document.getElementById('dlStatus'),pb=document.getElementById('dlProgress');
 btn.disabled=true;btn.textContent='打包中...';
 st.textContent='正在生成数据包...';pb.style.display='block';pb.value=0;
 try{
  const resp=await fetch('api/download_package.php?problem_id=<?= urlencode($pid) ?>');
  if(!resp.ok){throw new Error('HTTP '+resp.status)}
  const total=parseInt(resp.headers.get('Content-Length')||'0');
  const reader=resp.body.getReader();const chunks=[];let received=0;
  btn.textContent='下载中...';
  while(true){
   const {done,value}=await reader.read();
   if(done)break;
   chunks.push(value);received+=value.length;
   const pct=total?Math.round(received/total*100):0;
   pb.value=pct;
   st.textContent='下载中 '+formatSize(received)+(total?' / '+formatSize(total)+' ('+pct+'%)':'');
  }
  const blob=new Blob(chunks);
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a');a.href=url;a.download='<?= urlencode($pid) ?>_data.zip';
  document.body.appendChild(a);a.click();a.remove();
  URL.revokeObjectURL(url);
  st.textContent='下载完成 ('+formatSize(received)+')';
 }catch(e){st.textContent='下载失败: '+e.message}
 btn.disabled=false;btn.textContent='下载数据包';
 setTimeout(()=>{pb.style.display='none';if(st.textContent.includes('失败'))st.textContent='';},4000);
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
