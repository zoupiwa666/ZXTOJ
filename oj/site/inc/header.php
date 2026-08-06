<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
$currentUser = isLoggedIn() ? currentUser() : null;
$currentPage = basename($_SERVER['PHP_SELF']);

// 确保用户标签字段存在（幂等）
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS tag VARCHAR(5) DEFAULT NULL"); } catch (Exception $e) {}

// 全站用户头像 + 用户名徽章（用户名左边显示头像）
function userAvatar($username, $avatar = null, $size = 20) {
    if ($avatar === null || $avatar === '') {
        static $cache = [];
        global $pdo;
        if (!isset($cache[$username])) {
            $s = $pdo->prepare("SELECT avatar FROM users WHERE username=?");
            $s->execute([$username]);
            $cache[$username] = $s->fetchColumn() ?: '';
        }
        $avatar = $cache[$username];
    }
    if ($avatar) {
        return '<img class="uavatar" src="'.htmlspecialchars($avatar).'" width="'.$size.'" height="'.$size.'" alt="">';
    }
    return '<span class="uavatar uavatar-char" style="width:'.$size.'px;height:'.$size.'px;line-height:'.$size.'px;font-size:'.max(9, intval($size*0.5)).'px">'.htmlspecialchars(strtoupper(mb_substr($username,0,1))).'</span>';
}
// 用户名颜色：管理员紫色，普通用户棕色
function userColor(string $role): string {
    return in_array($role, ['super_admin', 'admin']) ? '#a855f7' : '#b0815a';
}
function userBadge($username, $avatar = null, $size = 20) {
    static $cache = [];
    global $pdo;
    if (!isset($cache[$username])) {
        $s = $pdo->prepare("SELECT avatar, role, tag FROM users WHERE username=?");
        $s->execute([$username]);
        $cache[$username] = $s->fetch() ?: null;
    }
    $info = $cache[$username];
    $role = $info['role'] ?? 'user';
    $tag  = $info['tag'] ?? '';
    $color = userColor($role);
    $tagHtml = $tag !== '' && $tag !== null
        ? ' <span class="utag" style="background:'.$color.';color:#fff">'.htmlspecialchars($tag).'</span>'
        : '';
    return '<span class="ubadge">'.userAvatar($username, $avatar, $size)
        .'<a class="ubadge-name" href="user.php?name='.urlencode($username).'" style="color:'.$color.'">'.htmlspecialchars($username).'</a>'
        .$tagHtml.'</span>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/jpeg" href="assets/favicon.jpg">
<title><?= $pageTitle ?? 'Zxt Super OJ' ?></title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,'Segoe UI','PingFang SC','Microsoft YaHei','Helvetica Neue',Arial,sans-serif;background:#000;color:#ddd;min-height:100vh;font-size:14px}
.topbar{
  background:#252525;border-bottom:1px solid #333;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 32px;height:64px;
}
.topbar .title{color:#fff;font-size:27px;font-weight:700;text-decoration:none;letter-spacing:2px;display:inline-flex;align-items:center;gap:14px}
.topbar .logo-icon{width:54px;height:54px;border-radius:50%;object-fit:cover;border:1px solid #333;flex-shrink:0}
.topbar .title span{color:#aaa;font-weight:400;font-size:14px;margin-left:6px}
.topbar .btns{display:flex;gap:0}
.topbar .btns a{
  color:#aaa;text-decoration:none;font-size:12px;padding:0 16px;line-height:64px;
  border-left:1px solid #222;transition:color .15s
}
.topbar .btns a:hover{color:#fff}
.topbar .user{color:#fff;font-size:12px;padding:0 16px;line-height:64px;border-left:1px solid #222}

.navbar{
  background:#252525;border-bottom:1px solid #222;
  padding:0 32px;display:flex;gap:0
}
.navbar a{
  color:#aaa;text-decoration:none;font-size:12px;padding:10px 20px;
  border-bottom:2px solid transparent;transition:all .15s
}
.navbar a:hover,.navbar a.active{color:#fff;border-bottom-color:#fff}

.main{padding:32px}
.container{max-width:900px;margin:0 auto;background:#141414;padding:24px;border:1px solid #222}
.card{background:#1e1e1e;border:1px solid #2a2a2a;padding:24px;margin-bottom:16px}
h1.page-title{font-size:16px;color:#fff;margin-bottom:20px;font-weight:400}
input,textarea,select,button{font-family:inherit}
input,textarea,select{
  width:100%;padding:8px 10px;background:#252525;border:1px solid #333;color:#ddd;font-size:13px;
  outline:none;transition:border-color .15s
}
input:focus,textarea:focus,select:focus{border-color:#999}
textarea{resize:vertical}
.btn{display:inline-block;padding:8px 24px;background:#2a2a2a;color:#ddd;border:none;font-size:12px;font-weight:600;cursor:pointer;letter-spacing:1px;transition:background .15s;text-decoration:none}
.btn:hover{background:#3a3a3a;color:#fff}
.btn:disabled{opacity:.4;cursor:not-allowed}
.btn-line{background:transparent;color:#aaa;border:1px solid #333}
.btn-line:hover{color:#fff;border-color:#999}
.btn-sm{padding:4px 12px;font-size:11px;background:#2a2a2a;color:#ddd;border:none;cursor:pointer;letter-spacing:0}
.btn-sm:hover{background:#3a3a3a;color:#fff}
.btn-danger{background:#400;color:#c00;border:1px solid #600}
.btn-danger:hover{background:#600;color:#f66}
label{display:block;font-size:11px;color:#999;margin:8px 0 4px;text-transform:uppercase;letter-spacing:1px}
.row{display:flex;gap:16px}.row>div{flex:1}
.msg{padding:8px 12px;border:1px solid #333;margin:8px 0;font-size:12px}
.msg-ok{border-color:#0c0;color:#0c0}
.msg-err{border-color:#c00;color:#c00}
.link{color:#aaa;text-decoration:none;font-size:12px}.link:hover{color:#fff}
.copy-btn{position:absolute;top:4px;right:4px;padding:2px 10px;background:#1a3a5c;color:#5af;border:1px solid #2a5a8c;font-size:10px;cursor:pointer;letter-spacing:0;font-family:inherit;opacity:0;transition:opacity .15s}
.copy-btn:hover{background:#2a4a7c;color:#8cf}
*:hover>.copy-btn{opacity:1}
.copy-done{color:#0c0!important;border-color:#0c0!important}
.ubadge{display:inline-flex;align-items:center;gap:6px;vertical-align:middle;line-height:1}
.ubadge .uavatar{border-radius:50%;object-fit:cover;display:inline-block;background:#333;flex-shrink:0}
.ubadge .uavatar-char{background:#2a3a5c;color:#fff;text-align:center;font-weight:700;border-radius:50%;display:inline-block;flex-shrink:0}
.ubadge-name{color:#ccc;text-decoration:none}
.ubadge-name:hover{color:#fff}
.utag{display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;line-height:1.5;margin-left:5px;vertical-align:middle;font-weight:400;letter-spacing:.5px;white-space:nowrap}
/* 用户名片悬停卡片 */
.ucard{position:fixed;z-index:9999;background:#1a1a1a;border:1px solid #333;border-radius:10px;padding:12px 14px;min-width:180px;max-width:240px;box-shadow:0 8px 28px rgba(0,0,0,.55);opacity:0;transform:translateY(6px) scale(.95);transition:opacity .18s ease,transform .18s ease;pointer-events:none;cursor:pointer}
.ucard.show{opacity:1;transform:none;pointer-events:auto}
.ucard .uc-top{display:flex;align-items:center;gap:10px}
.ucard .uc-avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;background:#2a3a5c;flex-shrink:0}
.ucard .uc-name{font-size:13px;color:#fff;font-weight:600;letter-spacing:.5px}
.ucard .uc-role{font-size:10px;color:#5af;margin-left:4px}
.ucard .uc-motto{font-size:11px;color:#999;margin-top:7px;line-height:1.45;word-break:break-word;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.ucard .uc-tip{font-size:10px;color:#666;margin-top:7px;text-align:center;border-top:1px solid #262626;padding-top:6px;letter-spacing:.5px}</style>
<style>
/* ===== 高级浮动输入框（OJ 风格）===== */
.float-wrap{position:relative;margin-bottom:14px;flex:1;min-width:0}
.float-wrap.in-flex{margin-bottom:0}
.float-wrap input,.float-wrap textarea,.float-wrap select{
  width:100%;padding:20px 12px 8px;background:#1a1a1a;border:1px solid #333;border-radius:8px;
  color:#ddd;font-size:13px;outline:none;transition:border-color .2s,box-shadow .2s,background .2s;font-family:inherit;
}
.float-wrap textarea{resize:vertical;min-height:60px}
.float-wrap .float-label{
  position:absolute;left:13px;top:calc(var(--flabel,13px) * 1.15);color:#777;font-size:var(--flabel,13px);pointer-events:none;margin:0;padding:0;
  text-transform:none;letter-spacing:0;transition:all .18s ease;line-height:1;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:calc(100% - 26px);
}
.float-wrap.focused .float-label,.float-wrap.filled .float-label{
  top:4px;font-size:var(--flabel-up,10px);color:#5af;letter-spacing:.5px;
}
.float-wrap input:focus,.float-wrap textarea:focus,.float-wrap select:focus{
  border-color:#5af;background:#1f1f1f;box-shadow:0 0 0 3px rgba(90,170,255,.12);
}
/* 基础输入框升级（非浮动场景） */
input,textarea,select{border-radius:8px;transition:border-color .2s,box-shadow .2s,background .2s}
input:focus,textarea:focus,select:focus{border-color:#5af;box-shadow:0 0 0 3px rgba(90,170,255,.12);background:#1f1f1f}
</style>
<link rel="stylesheet" href="assets/fa/all.min.css">
<link rel="stylesheet" href="assets/highlight.css"><style>.hljs-ln-numbers{text-align:right;color:#444;border-right:1px solid #222;padding-right:7px;margin-right:7px;user-select:none;font-size:inherit!important;line-height:inherit!important}.hljs-ln td{padding:0!important;background:transparent!important}.hljs-ln tr:hover{background:#0f0f0f}.hljs-ln tr:hover td{background:transparent!important}.hljs[data-highlighted],.hljs [data-highlighted]{font-size:inherit!important;line-height:inherit!important;background:transparent!important}.hljs-ln-code{padding-left:0!important}code.hljs[data-highlighted]{display:contents!important;background:transparent!important;padding:0!important}.hljs,.hljs *,.hljs-ln,.hljs-ln *,.hljs-ln-line{font-family:Consolas,'Courier New',monospace!important;font-size:12px!important;line-height:1.5!important}
.hljs-ln td,.hljs-ln th{padding:2px 0!important;border:none!important}
.hljs-ln-numbers{-webkit-user-select:none;user-select:none}</style></head></head>
<body>
<div class="topbar">
  <a class="title" href="/"><img src="assets/favicon.jpg" class="logo-icon" alt="logo">ZXT SUPER OJ<span>v1</span></a>
  <div class="btns">
    <?php if ($currentUser): ?>
      <span class="user" style="display:flex;align-items:center"><?= userBadge($currentUser["username"], $currentUser["avatar"] ?? null, 24) ?></span>
      <a href="chat.php">聊天<span id="chatUnread" style="display:none;background:#f44;color:#fff;font-size:10px;min-width:16px;height:16px;line-height:16px;border-radius:8px;padding:0 5px;margin-left:4px;text-align:center;vertical-align:middle"></span></a>
      <a href="logout.php">退出</a>
    <?php else: ?>
      <a href="login.php">登录</a>
      <a href="register.php">注册</a>
    <?php endif; ?>
  </div>
<script>
// 未读消息红点轮询
(function(){
  var el=null;
  function refresh(){
    fetch('api/unread_count.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
      var n=(d&&d.unread)||0;
      if(!el) el=document.getElementById('chatUnread');
      if(!el) return;
      if(n>0){ el.style.display='inline-block'; el.textContent=n>99?'99+':n; }
      else el.style.display='none';
    }).catch(function(){});
  }
  refresh();
  setInterval(refresh, 8000);
})();
</script>
</div>
<div class="navbar">
  <a href="index.php" class="<?= $currentPage=='index.php'?'active':'' ?>">首页</a>
  <a href="problems.php" class="<?= str_starts_with($currentPage,"problem")?"active":"" ?>">题库</a>
  <a href="lists.php" class="<?= $currentPage=='lists.php'||$currentPage=='list_view.php'?'active':'' ?>">题单</a>
  <a href="submissions.php" class="<?= $currentPage=="submissions.php"?"active":"" ?>">提交记录</a>
  <a href="articles.php" class="<?= in_array($currentPage,['articles.php','article.php','article_edit.php'])?"active":"" ?>">文章</a>
  <?php if ($currentUser && in_array($currentUser['role'], ['admin','super_admin'])): ?>
    <a href="groups.php" class="<?= $currentPage=='groups.php'?'active':'' ?>">用户组</a>
  <?php endif; ?>
  <?php if ($currentUser && $currentUser['role']==='super_admin'): ?>
    <a href="invites.php" class="<?= $currentPage=='invites.php'?'active':'' ?>">邀请码</a>
    <a href="users.php" class="<?= $currentPage=='users.php'?'active':'' ?>">用户管理</a>
  <?php endif; ?>
</div>
<div class="main"><div class="container">
