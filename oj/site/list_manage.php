<?php $pageTitle = '题单管理 - Zxt Super OJ'; require __DIR__ . '/inc/header.php';
$id = intval($_GET['id'] ?? 0);
?>
<style>
.mg-head{background:#1e1e1e;border:1px solid #2a2a2a;padding:20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center}
.mg-head h1{font-size:18px;color:#fff;font-weight:400}
.mg-box{background:#1e1e1e;border:1px solid #2a2a2a;padding:16px;margin-bottom:12px}
.mg-box h3{font-size:13px;color:#fff;font-weight:400;margin-bottom:10px}
.mg-box input{width:100%;padding:8px;background:#000;border:1px solid #333;color:#ddd;font-size:13px;margin:4px 0}
.row-flex{display:flex;gap:8px;align-items:center}
.btn-sm{padding:4px 12px;background:#2a2a2a;color:#ddd;border:none;cursor:pointer;font-size:11px}
.btn-sm:hover{background:#3a3a3a}.btn-danger{background:#400;color:#c00;border:1px solid #600}
.p-row{display:flex;align-items:center;gap:12px;background:#141414;border:1px solid #222;padding:8px 14px;margin-bottom:6px;font-size:13px}
.p-row span{flex:1;color:#ccc}
.perm-row{display:flex;align-items:center;gap:12px;background:#141414;border:1px solid #222;padding:6px 12px;margin-bottom:4px;font-size:12px}
.perm-row span{flex:1;color:#ccc}
</style>
<div id="app"><div style="text-align:center;color:#666;padding:40px">加载中...</div></div>
<script>
const listId=<?=$id?>;
async function load(){
 const r=await fetch('api/list.php?m=view&id='+listId);
 const d=await r.json();
 if(d.error){document.getElementById('app').innerHTML='<div style="color:#f66;text-align:center;padding:40px">'+d.error+'</div>';return;}
 if(!d.can_manage){location.href='list_view.php?id='+listId;return;}
 const tags=(d.tags||'').split(',').filter(t=>t).join(',');
 let html='<div class="mg-head"><h1>管理: '+d.name+'</h1><a href="list_view.php?id='+listId+'" class="btn-sm" style="text-decoration:none">返回题单</a></div>';

 html+='<div class="mg-box"><h3>基本信息</h3>'
  +'<input id="newName" value="'+d.name+'"><input id="newTags" value="'+tags+'" placeholder="标签,逗号分隔">'
  +'<div class="row-flex" style="margin-top:8px"><select id="newVis" style="flex:1;padding:8px;background:#000;border:1px solid #333;color:#ddd"><option value="public" '+(d.visibility==='public'?'selected':'')+'>公开</option><option value="private" '+(d.visibility==='private'?'selected':'')+'>私密</option></select><button class="btn-sm" onclick="saveInfo()">保存</button></div></div>';

 html+='<div class="mg-box"><h3>添加题目</h3><div class="row-flex"><input id="addPid" placeholder="题号"><button class="btn-sm" onclick="addP()">加题</button></div></div>';

 html+='<div class="mg-box"><h3>题目列表 ('+d.problems.length+')</h3><div id="plist">';
 d.problems.forEach(p=>{html+='<div class="p-row"><span>'+p.problem_id+' '+p.title+'</span><button class="btn-sm btn-danger" onclick="removeP(\''+p.problem_id+'\')">移除</button></div>';});
 html+='</div></div>';

 html+='<div class="mg-box"><h3>访问授权</h3><div class="row-flex"><input id="grantU" placeholder="用户名 或 team->组名"><button class="btn-sm" onclick="grant()">授权</button></div><div style="margin-top:8px" id="perms"></div></div>';

 html+='<div class="mg-box"><h3>危险操作</h3><button class="btn-sm btn-danger" onclick="del()">删除题单</button></div>';

 document.getElementById('app').innerHTML=html;
 loadPerms();
}
async function loadPerms(){
 const r=await fetch('api/list.php?m=perms&id='+listId);
 const perms=await r.json();
 let h='';
 for(const p of perms){h+='<div class="perm-row"><span>'+p.username+'</span><button class="btn-sm btn-danger" onclick="revoke(\''+p.username+'\')">撤销</button></div>';}
 document.getElementById('perms').innerHTML=h||'<span style="color:#666">无授权</span>';
}
async function saveInfo(){const n=document.getElementById('newName').value,t=document.getElementById('newTags').value,v=document.getElementById('newVis').value;const r=await fetch('api/list.php?m=update',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'list_id='+listId+'&name='+encodeURIComponent(n)+'&tags='+encodeURIComponent(t)+'&visibility='+v});const d=await r.json();alert(d.ok?'已保存':d.error);load();}
async function addP(){const pid=document.getElementById('addPid').value;const r=await fetch('api/list.php?m=add',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'list_id='+listId+'&problem_id='+encodeURIComponent(pid)});const d=await r.json();alert(d.ok?'已添加':d.error);load();}
async function removeP(pid){const r=await fetch('api/list.php?m=remove',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'list_id='+listId+'&problem_id='+encodeURIComponent(pid)});await r.json();load();}
async function grant(){const u=document.getElementById('grantU').value;const r=await fetch('api/list.php?m=grant',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'list_id='+listId+'&username='+encodeURIComponent(u)});const d=await r.json();alert(d.ok?'已授权':d.error);loadPerms();}
async function revoke(u){const r=await fetch('api/list.php?m=revoke',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'list_id='+listId+'&username='+encodeURIComponent(u)});await r.json();loadPerms();}
async function del(){if(!confirm('确定删除题单?'))return;const r=await fetch('api/list.php?m=delete&id='+listId);const d=await r.json();alert(d.ok?'已删除':d.error);location.href='lists.php';}
load();
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
