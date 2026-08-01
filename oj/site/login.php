<?php require __DIR__ . '/inc/config.php'; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $u=trim($_POST['username']??'');$p=$_POST['password']??'';
 $s=$pdo->prepare("SELECT * FROM users WHERE username=?");$s->execute([$u]);$user=$s->fetch();
 if($user&&password_verify($p,$user['password_hash'])){$_SESSION['user_id']=$user['id'];header('Location: /');exit;}
 else $error='用户名或密码错误';
}
?><!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Login - Zxt Super OJ</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'SF Mono','Consolas',monospace;background:#111;color:#ccc;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{width:320px}
.box .title{font-size:14px;color:#aaa;text-transform:uppercase;letter-spacing:2px;margin-bottom:24px;text-align:center}
.box input{width:100%;padding:8px 10px;background:#111;border:1px solid #333;color:#ccc;font-size:13px;margin-bottom:8px;outline:none;font-family:inherit}
.box input:focus{border-color:#999}
.box .btn{display:block;width:100%;padding:8px;background:#2a2a2a;color:#ccc;border:none;font-size:12px;font-weight:600;cursor:pointer;letter-spacing:1px;font-family:inherit;margin-top:8px}
.box .btn:hover{opacity:.8}
.box .err{color:#c00;font-size:11px;margin-bottom:8px;text-align:center}
.box .links{margin-top:16px;text-align:center;font-size:11px}
.box .links a{color:#999;text-decoration:none;margin:0 8px}
.box .links a:hover{color:#fff}
</style></head><body>
<div class="box">
<div class="title">ZXT SUPER OJ</div>
<?php if($error):?><div class="err"><?=$error?></div><?php endif?>
<form method="POST">
<input name="username" placeholder="用户名" required autofocus>
<input name="password" type="password" placeholder="密码" required>
<button class="btn">登录</button>
</form>
<div class="links"><a href="/">Home</a><a href="register.php">Register</a></div>
</div></body></html>
