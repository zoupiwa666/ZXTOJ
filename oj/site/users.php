<?php $pageTitle='用户管理 - Zxt Super OJ'; require __DIR__.'/inc/header.php'; requireRole('super_admin');
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&isset($_POST['user_id'])){
 $uid=intval($_POST['user_id']);
 if($_POST['action']==='change_role'&&isset($_POST['role'])){
  $pdo->prepare("UPDATE users SET role=? WHERE id=? AND id!=?")->execute([$_POST['role'],$uid,$currentUser['id']]);
 }
}
$users=$pdo->query("SELECT * FROM users ORDER BY role DESC, id")->fetchAll();
?>
<style>.tbl{width:100%;border-collapse:collapse;font-size:.85em}.tbl th,.tbl td{padding:10px 14px;border-bottom:1px solid var(--border);text-align:left}.tbl th{color:var(--accent)}.badge{padding:3px 10px;border-radius:12px;font-size:.75em;font-weight:600}.badge-sa{background:var(--accent);color:#fff}.badge-ad{background:rgba(124,92,252,.3);color:#fff}.badge-us{background:rgba(255,255,255,.1);color:var(--text)}select.small{padding:6px;border-radius:6px;border:1px solid var(--border);background:rgba(0,0,0,.3);color:#fff;font-size:.8em}</style>
<h1 class="page-title">👥 用户管理</h1>
<table class="tbl" style="background:var(--card);border:1px solid var(--border);border-radius:12px">
<tr><th>编号</th><th>用户名</th><th>角色</th><th>注册时间</th><th>操作</th></tr>
<?php foreach($users as $u): ?>
<tr>
  <td><?=$u['id']?></td><td><?=htmlspecialchars($u['username'])?></td>
  <td><span class="badge badge-<?=$u['role']==='super_admin'?'sa':($u['role']==='admin'?'ad':'us')?>"><?=$u['role']?></span></td>
  <td style="color:var(--muted)"><?=$u['created_at']?></td>
  <td><?php if($u['id']!=$currentUser['id']):?>
    <form method="POST" style="display:inline"><input type="hidden" name="action" value="change_role"><input type="hidden" name="user_id" value="<?=$u['id']?>">
    <select name="role" class="small" onchange="this.form.submit()"><option <?=$u['role']=='user'?'selected':''?> value="user">user</option><option <?=$u['role']=='admin'?'selected':''?> value="admin">admin</option></select></form>
  <?php else:?><span style="color:var(--muted)">当前用户</span><?php endif?></td>
</tr>
<?php endforeach?>
</table>
<?php require __DIR__.'/inc/footer.php'; ?>
