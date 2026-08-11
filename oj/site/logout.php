<?php
session_start();
// 清除 OJCID cookie（DB 中保留，下次登录复用）
setcookie('OJCID', '', time() - 3600, '/', '', false, true);
session_destroy();
header('Location: /');
