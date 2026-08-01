<?php require __DIR__ . '/inc/config.php'; $error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $inv=trim($_POST['invite']??'');$un=trim($_POST['username']??'');$pw=$_POST['password']??'';$cf=$_POST['confirm']??'';
 if(strlen($un)<2||strlen($un)>50)$error='用户名: 2-50个字符';
 elseif(!preg_match('/^[a-zA-Z0-9_]+$/',$un))$error='用户名: 仅字母数字下划线';
 elseif(strlen($pw)<6)$error='密码: 至少6位';
 elseif($pw!==$cf)$error='两次密码不一致';
 else{
  $s=$pdo->prepare("SELECT * FROM invite_codes WHERE code=? AND is_active=1");$s->execute([$inv]);$c=$s->fetch();
  if(!$c)$error='邀请码无效';
  elseif($c['expires_at']&&strtotime($c['expires_at'])<time())$error='邀请码已过期';
  elseif($c['use_count']>=$c['max_uses'])$error='邀请码已用完';
  else{
   $s=$pdo->prepare("SELECT id FROM users WHERE username=?");$s->execute([$un]);
   if($s->fetch())$error='用户名已占用';
   else{$h=password_hash($pw,PASSWORD_BCRYPT);$pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES (?,?,'user')")->execute([$un,$h]);$pdo->prepare("UPDATE invite_codes SET use_count=use_count+1 WHERE id=?")->execute([$c['id']]);$ok='注册成功！<a href="login.php" style="color:#fff">登录</a>';}
  }
 }
}
?><!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Register - Zxt Super OJ</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'SF Mono','Consolas',monospace;background:#111;color:#ccc;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{width:340px}
.box .title{font-size:14px;color:#aaa;text-transform:uppercase;letter-spacing:2px;margin-bottom:24px;text-align:center}
.box input{width:100%;padding:8px 10px;background:#111;border:1px solid #333;color:#ccc;font-size:13px;margin-bottom:8px;outline:none;font-family:inherit}
.box input:focus{border-color:#999}
.box .btn{display:block;width:100%;padding:8px;background:#fff;color:#000;border:none;font-size:12px;font-weight:600;cursor:pointer;letter-spacing:1px;font-family:inherit;margin-top:8px}
.box .btn:hover{opacity:.8}
.box .err{color:#c00;font-size:11px;margin-bottom:8px;text-align:center}
.box .ok{color:#0c0;font-size:11px;margin-bottom:8px;text-align:center}
.box .links{margin-top:16px;text-align:center;font-size:11px}
.box .links a{color:#999;text-decoration:none}.box .links a:hover{color:#fff}
</style></head><body>
<div class="box">
<div class="title">ZXT SUPER OJ</div>
<?php if($error):?><div class="err"><?=$error?></div><?php endif?>
<?php if($ok):?><div class="ok"><?=$ok?></div><?php else:?>
<form method="POST">
<input name="invite" placeholder="邀请码" required autofocus>
<input name="username" placeholder="用户名">
<input name="password" type="password" placeholder="密码">
<input name="confirm" type="password" placeholder="确认密码">
<button class="btn">注册</button>
</form>
<?php endif?>
<div class="links"><a href="login.php">登录</a></div>
</div></body></html>
