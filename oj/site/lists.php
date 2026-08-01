<?php $pageTitle = '题单 - Zxt Super OJ'; require __DIR__ . '/inc/header.php'; ?>
<style>
.search-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.search-bar input,.search-bar select{flex:1;min-width:150px;padding:8px 12px;background:#000;border:1px solid #333;color:#ddd;font-size:13px}
.l-card{background:#1e1e1e;border:1px solid #2a2a2a;padding:16px 20px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;transition:.15s}
.l-card:hover{border-color:#444;background:#222}
.l-card h3{font-size:14px;color:#fff;font-weight:400}
.l-card .meta{font-size:11px;color:#666;margin-top:4px}
.tag{display:inline-block;background:#1a3a5c;color:#5af;font-size:10px;padding:1px 8px;margin:2px;border-radius:2px}
.btn-sm{padding:4px 12px;background:#2a2a2a;color:#ddd;border:none;cursor:pointer;font-size:11px}
.btn-sm:hover{background:#3a3a3a}
</style>
<h1 class="page-title">题单</h1>

<div class="search-bar">
  <input id="searchQ" placeholder="搜索题单名..." onkeydown="if(event.key==='Enter')searchList()">
  <input id="searchTag" placeholder="按标签搜索 (多个用逗号)..." onkeydown="if(event.key==='Enter')searchList()">
  <button class="btn-sm" onclick="searchList()">搜索</button>
  <?php if (isAdmin()): ?>
  <button class="btn-sm" onclick="createList()">+ 新建题单</button>
  <?php endif; ?>
</div>

<div id="lists"></div>

<script>
async function searchList(){
 const q=document.getElementById('searchQ').value, tag=document.getElementById('searchTag').value;
 const r=await fetch('api/list.php?m=search&q='+encodeURIComponent(q)+'&tag='+encodeURIComponent(tag));
 const lists=await r.json();
 document.getElementById('lists').innerHTML='';
 for(const l of lists){
  const tags=(l.tags||'').split(',').filter(t=>t).map(t=>'<span class="tag">'+t+'</span>').join('');
  const d=document.createElement('div');d.className='l-card';d.onclick=()=>location.href='list_view.php?id='+l.id;
  d.innerHTML='<div><h3>'+l.name+'</h3><div class="meta">'+l.count+'题 | 创建者: '+l.created_by+'</div><div style="margin-top:4px">'+tags+'</div></div><span style="color:#666">→</span>';
  document.getElementById('lists').appendChild(d);
 }
 if(!lists.length) document.getElementById('lists').innerHTML='<div style="text-align:center;color:#666;padding:40px">暂无题单</div>';
}
async function createList(){
 const name=prompt('题单名:');if(!name)return;
 const vis=confirm('设为私密?')?'private':'public';
 const tags=prompt('标签(逗号分隔,可留空):')||'';
 const r=await fetch('api/list.php?m=create',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(name)+'&visibility='+vis+'&tags='+encodeURIComponent(tags)});
 const d=await r.json();
 if(d.ok){alert('已创建! ID='+d.id);location.href='list_view.php?id='+d.id;}
 else alert(d.error);
}
searchList();
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
