<?php
// 头像上传（返回 JSON，任何错误都输出 JSON 而非 HTML，便于前端提示）
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

function avatar_err(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'message'=>$msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['avatar'])) {
    avatar_err(400, '未收到文件');
}

$user = currentUser();
$ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) avatar_err(400, '不支持的图片格式');
if ($_FILES['avatar']['size'] > 2*1024*1024) avatar_err(413, '图片过大（最大2MB）');

// GD 函数存在性检查：环境缺库时给出明确提示而非 HTML 致命错误
$fnMap = [
    'jpg'  => 'imagecreatefromjpeg',
    'jpeg' => 'imagecreatefromjpeg',
    'png'  => 'imagecreatefrompng',
    'gif'  => 'imagecreatefromgif',
    'webp' => 'imagecreatefromwebp',
];
if (!function_exists($fnMap[$ext]) || !function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
    avatar_err(500, '服务器缺少 GD 图片支持（JPEG/WebP），请重建 OJ 镜像');
}

try {
    // 创建 50x50 缩略图
    $src = call_user_func($fnMap[$ext], $_FILES['avatar']['tmp_name']);
    if (!$src) avatar_err(400, '图片解析失败，请换一张图片');

    $size = 50;
    $dst = imagecreatetruecolor($size, $size);
    if (!$dst) avatar_err(500, '创建画布失败');
    if (in_array($ext, ['png','webp'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    $sw = imagesx($src); $sh = imagesy($src);
    $min = min($sw, $sh);
    $sx = ($sw - $min) / 2; $sy = ($sh - $min) / 2;
    imagecopyresampled($dst, $src, 0, 0, (int)$sx, (int)$sy, $size, $size, $min, $min);

    // 确保目录存在
    $dir = __DIR__.'/../uploads/avatars';
    @mkdir($dir, 0777, true);

    $filename = 'avatar_'.$user['id'].'_'.time().'.jpg';
    $path = $dir.'/'.$filename;
    if (!imagejpeg($dst, $path, 85)) avatar_err(500, '图片保存失败，请检查目录权限');
    imagedestroy($src); imagedestroy($dst);

    // 删旧头像
    if ($user['avatar'] && file_exists(__DIR__.'/../'.$user['avatar'])) {
        @unlink(__DIR__.'/../'.$user['avatar']);
    }

    $avatarUrl = '/uploads/avatars/'.$filename;   // 绝对路径，/chat/ 子路径下也能加载
    $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatarUrl, $user['id']]);

    echo json_encode(['ok'=>true, 'avatar'=>$avatarUrl]);
} catch (Throwable $e) {
    avatar_err(500, '图片处理失败: '.$e->getMessage());
}
