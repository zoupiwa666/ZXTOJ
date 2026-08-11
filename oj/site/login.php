<?php require __DIR__ . '/inc/config.php'; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $u=trim($_POST['username']??'');$p=$_POST['password']??'';
 $s=$pdo->prepare("SELECT * FROM users WHERE username=?");$s->execute([$u]);$user=$s->fetch();
 if($user&&password_verify($p,$user['password_hash'])){
  $_SESSION['user_id']=$user['id'];
  // OJCID 长效登录凭证：已有有效 CID 复用并续期，否则生成新的
  if (!empty($user['ojcid']) && $user['ojcid_expire'] && strtotime($user['ojcid_expire']) > time()) {
      $cid = $user['ojcid'];
      $pdo->prepare("UPDATE users SET ojcid_expire=DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id=?")->execute([$user['id']]);
  } else {
      $cid = bin2hex(random_bytes(24));
      $pdo->prepare("UPDATE users SET ojcid=?, ojcid_expire=DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id=?")->execute([$cid, $user['id']]);
  }
  setcookie('OJCID', $cid, time() + 7 * 86400, '/', '', false, true);
  $redir = $_GET['redirect'] ?? '';
  if ($redir !== '' && strpos($redir, 'login.php') === false && strpos($redir, 'register.php') === false) header('Location: '.$redir);
  else header('Location: /');
  exit;}
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

.float-wrap{position:relative;margin-bottom:14px}
.float-wrap input{width:100%;padding:20px 12px 8px;background:#111;border:1px solid #333;border-radius:8px;color:#ccc;font-size:13px;outline:none;transition:border-color .2s,box-shadow .2s,background .2s;font-family:inherit;margin-bottom:0}
.float-wrap .float-label{position:absolute;left:13px;top:15px;color:#777;font-size:13px;pointer-events:none;transition:all .18s ease}
.float-wrap.focused .float-label,.float-wrap.filled .float-label{top:5px;font-size:10px;color:#5af;letter-spacing:.5px}
.float-wrap input:focus{border-color:#5af;box-shadow:0 0 0 3px rgba(90,170,255,.12);background:#181818}
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
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.box input[placeholder]').forEach(function(el){
    var par=el.parentElement; if(!par||par.querySelector('.float-label'))return;
    var ph=el.getAttribute('placeholder')||''; if(!ph)return;
    var wrap=document.createElement('div'); wrap.className='float-wrap';
    par.insertBefore(wrap,el); wrap.appendChild(el);
    var lab=document.createElement('label'); lab.className='float-label'; lab.textContent=ph; wrap.appendChild(lab);
    el.removeAttribute('placeholder');
    if(el.value)wrap.classList.add('filled');
    el.addEventListener('input',function(){wrap.classList.toggle('filled',!!el.value);});
    el.addEventListener('focus',function(){wrap.classList.add('focused');});
    el.addEventListener('blur',function(){wrap.classList.remove('focused');});
  });
});
</script>
</body></html>
