<?php
$pageTitle = '文章权限 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
require __DIR__.'/inc/article_tables.php';
requireRole('admin');
$users = $pdo->query("SELECT u.id,u.username,u.role, IFNULL(p.can_view,1) cv, IFNULL(p.can_publish,1) cp, IFNULL(p.can_edit,0) ce
                      FROM users u LEFT JOIN article_permissions p ON p.username=u.username ORDER BY u.role DESC,u.id")->fetchAll();
?>
<h1 class="page-title"><i class="fa-solid fa-lock"></i> 文章权限管理</h1>
<div style="font-size:11px;color:#888;margin-bottom:12px">设置每个用户的：查看 / 发布 / 修改 权限（未设置的用户默认：可查看、可发布、不可修改）</div>
<table style="width:100%;border-collapse:collapse;font-size:12px">
<thead><tr style="color:#888;text-align:left">
  <th style="padding:8px;border-bottom:1px solid #222">用户</th>
  <th style="padding:8px;border-bottom:1px solid #222">查看</th>
  <th style="padding:8px;border-bottom:1px solid #222">发布</th>
  <th style="padding:8px;border-bottom:1px solid #222">修改</th>
</tr></thead>
<tbody>
<?php foreach($users as $u): ?>
<tr>
  <td style="padding:8px;border-bottom:1px solid #1a1a1a"><?=userBadge($u['username'])?> <?=$u['role']==='super_admin'?'<span style="color:#ffab00;font-size:10px">SA</span>':''?></td>
  <td style="padding:8px;border-bottom:1px solid #1a1a1a"><input type="checkbox" class="p-cv" data-u="<?=htmlspecialchars($u['username'])?>" <?=$u['cv']?'checked':''?> <?=$u['role']==='super_admin'?'disabled':''?>></td>
  <td style="padding:8px;border-bottom:1px solid #1a1a1a"><input type="checkbox" class="p-cp" data-u="<?=htmlspecialchars($u['username'])?>" <?=$u['cp']?'checked':''?> <?=$u['role']==='super_admin'?'disabled':''?>></td>
  <td style="padding:8px;border-bottom:1px solid #1a1a1a"><input type="checkbox" class="p-ce" data-u="<?=htmlspecialchars($u['username'])?>" <?=$u['ce']?'checked':''?> <?=$u['role']==='super_admin'?'disabled':''?>></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div style="margin-top:14px;display:flex;gap:8px;align-items:center">
  <button class="btn" onclick="savePerms()">保存全部</button>
  <span id="pmMsg" style="font-size:12px;color:#999"></span>
</div>
<script>
async function savePerms(){
  const msg=document.getElementById('pmMsg'); msg.textContent='保存中...';
  const rows=[];
  document.querySelectorAll('tbody tr').forEach(tr=>{
    const u=tr.querySelector('.p-cv');
    if(!u||u.disabled) return;
    rows.push({username:u.dataset.u,
      can_view:u.checked?1:0,
      can_publish:tr.querySelector('.p-cp').checked?1:0,
      can_edit:tr.querySelector('.p-ce').checked?1:0});
  });
  let ok=0,fail=0;
  for(const r of rows){
    const fd=new FormData();
    fd.append('username',r.username);
    fd.append('can_view',r.can_view); fd.append('can_publish',r.can_publish); fd.append('can_edit',r.can_edit);
    try{ const resp=await fetch('api/article_perms.php',{method:'POST',body:fd}); const d=await resp.json(); d.ok?ok++:fail++; }
    catch(e){ fail++; }
  }
  msg.textContent=`保存完成：${ok} 成功${fail?'，'+fail+' 失败':''}`;
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
