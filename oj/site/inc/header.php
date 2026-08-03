<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
$currentUser = isLoggedIn() ? currentUser() : null;
$currentPage = basename($_SERVER['PHP_SELF']);

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
function userBadge($username, $avatar = null, $size = 20) {
    return '<span class="ubadge">'.userAvatar($username, $avatar, $size)
        .'<a class="ubadge-name" href="user.php?name='.urlencode($username).'">'.htmlspecialchars($username).'</a></span>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'Zxt Super OJ' ?></title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,'Segoe UI','PingFang SC','Microsoft YaHei','Helvetica Neue',Arial,sans-serif;background:#000;color:#ddd;min-height:100vh;font-size:14px}
.topbar{
  background:#252525;border-bottom:1px solid #333;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 32px;height:48px;
}
.topbar .title{color:#fff;font-size:16px;font-weight:700;text-decoration:none;letter-spacing:2px}
.topbar .title span{color:#aaa;font-weight:400;font-size:12px;margin-left:6px}
.topbar .btns{display:flex;gap:0}
.topbar .btns a{
  color:#aaa;text-decoration:none;font-size:12px;padding:0 16px;line-height:48px;
  border-left:1px solid #222;transition:color .15s
}
.topbar .btns a:hover{color:#fff}
.topbar .user{color:#fff;font-size:12px;padding:0 16px;line-height:48px;border-left:1px solid #222}

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
.ubadge-name:hover{color:#fff}</style>
<link rel="stylesheet" href="assets/highlight.css"><style>.hljs-ln-numbers{text-align:right;color:#444;border-right:1px solid #222;padding-right:7px;margin-right:7px;user-select:none;font-size:inherit!important;line-height:inherit!important}.hljs-ln td{padding:0!important;background:transparent!important}.hljs-ln tr:hover{background:#0f0f0f}.hljs-ln tr:hover td{background:transparent!important}.hljs[data-highlighted],.hljs [data-highlighted]{font-size:inherit!important;line-height:inherit!important;background:transparent!important}.hljs-ln-code{padding-left:0!important}code.hljs[data-highlighted]{display:contents!important;background:transparent!important;padding:0!important}.hljs,.hljs *,.hljs-ln,.hljs-ln *,.hljs-ln-line{font-family:monospace!important;font-size:12px!important;line-height:1.5!important}
.hljs-ln td,.hljs-ln th{padding:2px 0!important;border:none!important}
.hljs-ln-numbers{-webkit-user-select:none;user-select:none}</style></head></head>
<body>
<div class="topbar">
  <a class="title" href="/">ZXT SUPER OJ<span>v1</span></a>
  <div class="btns">
    <?php if ($currentUser): ?>
      <span class="user" style="display:flex;align-items:center"><?= userBadge($currentUser["username"], $currentUser["avatar"] ?? null, 24) ?></span>
      <a href="chat.php">聊天</a>
      <a href="logout.php">退出</a>
    <?php else: ?>
      <a href="login.php">登录</a>
      <a href="register.php">注册</a>
    <?php endif; ?>
  </div>
</div>
<div class="navbar">
  <a href="index.php" class="<?= $currentPage=='index.php'?'active':'' ?>">首页</a>
  <a href="problems.php" class="<?= str_starts_with($currentPage,"problem")?"active":"" ?>">题库</a>
  <a href="lists.php" class="<?= $currentPage=='lists.php'||$currentPage=='list_view.php'?'active':'' ?>">题单</a>
  <a href="submissions.php" class="<?= $currentPage=="submissions.php"?"active":"" ?>">提交记录</a>
  <?php if ($currentUser && in_array($currentUser['role'], ['admin','super_admin'])): ?>
    <a href="groups.php" class="<?= $currentPage=='groups.php'?'active':'' ?>">用户组</a>
  <?php endif; ?>
  <?php if ($currentUser && $currentUser['role']==='super_admin'): ?>
    <a href="invites.php" class="<?= $currentPage=='invites.php'?'active':'' ?>">邀请码</a>
    <a href="users.php" class="<?= $currentPage=='users.php'?'active':'' ?>">用户管理</a>
  <?php endif; ?>
</div>
<div class="main"><div class="container">
