<?php
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['avatar'])) {
    http_response_code(400); die('{}');
}

$user = currentUser();
$ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { die('Invalid format'); }
if ($_FILES['avatar']['size'] > 2*1024*1024) { die('Too large (max 2MB)'); }

// 创建 50x50 缩略图
$src = match($ext) {
    'jpg','jpeg' => imagecreatefromjpeg($_FILES['avatar']['tmp_name']),
    'png' => imagecreatefrompng($_FILES['avatar']['tmp_name']),
    'gif' => imagecreatefromgif($_FILES['avatar']['tmp_name']),
    'webp' => imagecreatefromwebp($_FILES['avatar']['tmp_name']),
};
if (!$src) { die('Invalid image'); }

$size = 50;
$dst = imagecreatetruecolor($size, $size);
// PNG 透明支持
if (in_array($ext, ['png','webp'])) {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
}

$sw = imagesx($src); $sh = imagesy($src);
$min = min($sw, $sh);
$sx = ($sw - $min) / 2; $sy = ($sh - $min) / 2;
imagecopyresampled($dst, $src, 0, 0, (int)$sx, (int)$sy, $size, $size, $min, $min);

$filename = 'avatar_'.$user['id'].'_'.time().'.jpg';
$path = __DIR__.'/../uploads/avatars/'.$filename;
imagejpeg($dst, $path, 85);
imagedestroy($src); imagedestroy($dst);

// 删旧头像
if ($user['avatar'] && file_exists(__DIR__.'/../'.$user['avatar'])) {
    unlink(__DIR__.'/../'.$user['avatar']);
}

$avatarUrl = 'uploads/avatars/'.$filename;
$pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatarUrl, $user['id']]);
$_SESSION['avatar_updated'] = true;

echo json_encode(['ok'=>true, 'avatar'=>$avatarUrl]);
