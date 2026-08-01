<?php $pageTitle = '用户组 - Zxt Super OJ'; require __DIR__ . '/inc/header.php'; requireRole('admin');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create' && !empty($_POST['group_name'])) {
        $name = trim($_POST['group_name']);
        $s = $pdo->prepare("SELECT id FROM user_groups WHERE name=?"); $s->execute([$name]);
        if ($s->fetch()) { $msg = '组名已存在'; }
        else { $pdo->prepare("INSERT INTO user_groups (name, created_by) VALUES (?,?)")->execute([$name, currentUser()['username']]); $msg = '组已创建'; }
    }
    elseif ($_POST['action'] === 'add_member' && isset($_POST['group_id'])) {
        $gid = intval($_POST['group_id']); $uname = trim($_POST['member'] ?? '');
        if ($uname) { $pdo->prepare("INSERT IGNORE INTO user_group_members (group_id,username) VALUES (?,?)")->execute([$gid, $uname]); $msg = '成员已添加'; }
    }
    elseif ($_POST['action'] === 'remove_member' && isset($_POST['member_id'])) {
        $pdo->prepare("DELETE FROM user_group_members WHERE id=?")->execute([intval($_POST['member_id'])]); $msg = '成员已移除';
    }
    elseif ($_POST['action'] === 'delete_group' && isset($_POST['group_id'])) {
        $gid = intval($_POST['group_id']);
        $pdo->prepare("DELETE FROM user_group_members WHERE group_id=?")->execute([$gid]);
        $pdo->prepare("DELETE FROM user_groups WHERE id=?")->execute([$gid]); $msg = '组已删除';
    }
}
$groups = $pdo->query("SELECT g.*, (SELECT COUNT(*) FROM user_group_members m WHERE m.group_id=g.id) as cnt FROM user_groups g ORDER BY g.id")->fetchAll();
?>
<style>
.g-card{background:#1e1e1e;border:1px solid #2a2a2a;padding:16px;margin-bottom:12px}
.g-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.g-head b{color:#fff;font-size:14px}
.member-chip{display:inline-block;background:#222;border:1px solid #333;padding:3px 10px;margin:3px;font-size:12px;color:#ccc}
.member-chip a{color:#f66;margin-left:6px;text-decoration:none}
input{width:100%;padding:8px;background:#000;border:1px solid #333;color:#ddd;margin:4px 0;font-size:13px}
.btn-sm{padding:4px 12px;background:#2a2a2a;color:#ddd;border:none;cursor:pointer;font-size:11px}
.btn-sm:hover{background:#3a3a3a}
</style>
<h1 class="page-title">用户组管理</h1>
<?php if($msg):?><div style="padding:8px 12px;border:1px solid #0c0;color:#0c0;margin-bottom:16px;font-size:12px"><?=$msg?></div><?php endif?>

<div class="g-card">
  <b style="color:#fff">创建用户组</b>
  <form method="POST" style="display:flex;gap:8px;margin-top:8px">
    <input type="hidden" name="action" value="create">
    <input name="group_name" placeholder="组名" style="flex:1">
    <button class="btn-sm">创建</button>
  </form>
</div>

<?php foreach($groups as $g): 
    $members = $pdo->prepare("SELECT * FROM user_group_members WHERE group_id=? ORDER BY username"); $members->execute([$g['id']]); $ml = $members->fetchAll();
?>
<div class="g-card">
  <div class="g-head">
    <b><?=htmlspecialchars($g['name'])?> <span style="color:#666;font-size:12px">(<?=$g['cnt']?>人)</span></b>
    <form method="POST" style="display:inline"><input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="<?=$g['id']?>"><button class="btn-sm" style="background:#400;color:#c00;border:1px solid #600">删除组</button></form>
  </div>
  <div>
    <?php foreach($ml as $m): ?>
      <span class="member-chip"><?=htmlspecialchars($m['username'])?>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="remove_member"><input type="hidden" name="member_id" value="<?=$m['id']?>"><button class="btn-sm" style="background:none;color:#f66;border:none;padding:0">✕</button></form>
      </span>
    <?php endforeach; if(!$ml):?><span style="color:#666;font-size:12px">暂无成员</span><?php endif;?>
  </div>
  <form method="POST" style="display:flex;gap:8px;margin-top:8px">
    <input type="hidden" name="action" value="add_member">
    <input type="hidden" name="group_id" value="<?=$g['id']?>">
    <input name="member" placeholder="添加用户名" style="flex:1">
    <button class="btn-sm">添加成员</button>
  </form>
</div>
<?php endforeach; if(!$groups):?><div style="text-align:center;color:#666;padding:40px">暂无用户组</div><?php endif?>
<?php require __DIR__ . '/inc/footer.php'; ?>
