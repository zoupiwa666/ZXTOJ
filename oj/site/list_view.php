<?php $pageTitle = '题单详情 - Zxt Super OJ'; require __DIR__ . '/inc/header.php';
$id = intval($_GET['id'] ?? 0);
?>
<style>
.lv-head{background:#1e1e1e;border:1px solid #2a2a2a;padding:20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center}
.lv-head h1{font-size:18px;color:#fff;font-weight:400;margin-bottom:8px}
.lv-head .meta{font-size:12px;color:#888}
.tag{display:inline-block;background:#1a3a5c;color:#5af;font-size:11px;padding:2px 10px;margin:2px;border-radius:2px}
.p-row{display:flex;align-items:center;gap:12px;background:#1e1e1e;border:1px solid #2a2a2a;padding:10px 16px;margin-bottom:6px}
.p-row a{color:#ddd;text-decoration:none;flex:1}.p-row a:hover{color:#fff}
.btn-sm{padding:6px 18px;background:#2a2a2a;color:#ccc;text-decoration:none;font-size:12px;border:none;cursor:pointer}
.btn-sm:hover{background:#3a3a3a;color:#fff}
</style>
<div id="app"><div style="text-align:center;color:#666;padding:40px">加载中...</div></div>
<script>
const listId=<?=$id?>;
async function load(){
 const r=await fetch('api/list.php?m=view&id='+listId);
 const d=await r.json();
 if(d.error){document.getElementById('app').innerHTML='<div style="color:#f66;text-align:center;padding:40px">'+d.error+'</div>';return;}
 const tags=(d.tags||'').split(',').filter(t=>t).map(t=>'<span class="tag">'+t+'</span>').join('');
 let html='<div class="lv-head"><div><h1>'+d.name+'</h1><div class="meta">'+(d.visibility==='public'?'公开':'私密')+' | 创建者: '+d.created_by+' | '+d.problems.length+'题</div><div style="margin-top:6px">'+tags+'</div></div>';
 if(d.can_manage){html+='<a class="btn-sm" href="list_manage.php?id='+listId+'">管理</a>';}
 html+='</div>';
 d.problems.forEach((p,i)=>{html+='<div class="p-row"><span style="color:#666;font-size:12px">'+(i+1)+'</span><a href="problem.php?id='+p.problem_id+'&list='+listId+'">'+p.problem_id+' '+p.title+'</a><span style="color:#666">→</span></div>';});
 if(!d.problems.length)html+='<div style="text-align:center;color:#666;padding:30px">暂无题目</div>';
 document.getElementById('app').innerHTML=html;
}
load();
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
