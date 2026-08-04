<?php
require __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/sanitize.php';
require __DIR__ . '/inc/auth.php';
requireLogin();
$user = currentUser();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['old_password'])) {
        $old = $_POST['old_password'] ?? '';
        $newpw = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($old, $user['password_hash'])) { $msg = '原密码错误'; }
        elseif (strlen($newpw) < 6) { $msg = '新密码至少6位'; }
        elseif ($newpw !== $confirm) { $msg = '两次密码不一致'; }
        else {
            $hash = password_hash($newpw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $user['id']]);
            $msg = '密码已修改。';
        }
    }
    if (isset($_POST['motto'])) {
        $motto = mb_substr(sanitize_text(trim($_POST['motto'])), 0, 200);
        $pdo->prepare("UPDATE users SET motto=? WHERE id=?")->execute([$motto, $user['id']]);
        $msg = '已更新.';
    }
    // 头像通过 JS fetch api/avatar.php 上传
    $user = currentUser(); // refresh
}

$pageTitle = 'Profile - Zxt Super OJ';
require __DIR__ . '/inc/header.php';
?>
<style>
.pf-box{max-width:500px}
.avatar-upload{width:50px;height:50px;border:1px solid #333;overflow:hidden;margin-bottom:16px;cursor:pointer;position:relative}
.avatar-upload img{width:100%;height:100%;object-fit:cover}
.avatar-upload .overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;font-size:10px;color:#ccc;opacity:0;transition:.15s}
.avatar-upload:hover .overlay{opacity:1}
.avatar-upload input{display:none}
.avatar-default{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#111;color:#999;font-size:20px;font-weight:700}
textarea{width:100%;padding:8px 10px;background:#111;border:1px solid #333;color:#ccc;font-size:13px;resize:vertical;font-family:inherit;outline:none;min-height:60px}
textarea:focus{border-color:#999}
</style>
<h1 class="page-title">编辑资料</h1>
<?php if($msg):?><div style="padding:8px 12px;border:1px solid #0c0;color:#0c0;margin-bottom:16px;font-size:12px"><?=$msg?></div><?php endif?>
<div class="pf-box">

<div class="avatar-upload" onclick="document.getElementById('af').click()">
  <?php if($user['avatar']):?><img src="<?=$user['avatar']?>" alt=""><div class="overlay">更换</div>
  <?php else:?><div class="avatar-default"><?=strtoupper(substr($user['username'],0,1))?></div><div class="overlay">上传</div><?php endif?>
  <input type="file" id="af" accept="image/*" onchange="uploadAvatar(this)">
</div>

<form method="POST">
<label>格言</label>
<textarea name="motto" placeholder="你的格言..."><?=htmlspecialchars($user['motto'])?></textarea>
<button class="btn" style="margin-top:12px">保存</button>
</form>

<div style="margin-top:24px;padding-top:16px;border-top:1px solid #222">
<h3 style="font-size:13px;color:#fff;font-weight:400;margin-bottom:8px">修改密码</h3>
<form method="POST">
<input type="password" name="old_password" placeholder="原密码" required>
<input type="password" name="new_password" placeholder="新密码(至少6位)" required>
<input type="password" name="confirm_password" placeholder="确认新密码" required>
<button class="btn" style="margin-top:12px">修改密码</button>
</form>
</div>

<div style="margin-top:24px;font-size:12px;color:#999">用户名: <b style="color:#ccc"><?=htmlspecialchars($user['username'])?></b> | 角色: <b style="color:#ccc"><?=$user['role']?></b></div>
</div>

<script>
async function uploadAvatar(input){
 if(!input.files[0])return;
 const f=new FormData();f.append('avatar',input.files[0]);
 const r=await fetch('api/avatar.php',{method:'POST',body:f});
 const d=await r.json();
 if(d.ok) location.reload();
 else alert('上传失败');
}
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
