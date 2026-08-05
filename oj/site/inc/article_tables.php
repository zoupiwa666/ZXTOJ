<?php
// 文章功能数据表（幂等自动创建，兼容已有部署）
function article_ensure_tables(): void {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            content LONGTEXT NOT NULL,
            author VARCHAR(50) NOT NULL,
            is_announcement TINYINT(1) DEFAULT 0,
            is_public TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_visible (is_announcement, is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS article_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            can_view TINYINT(1) DEFAULT 1,
            can_publish TINYINT(1) DEFAULT 1,
            can_edit TINYINT(1) DEFAULT 0,
            updated_by VARCHAR(50),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}
article_ensure_tables();

// 获取用户文章权限（默认：可查看、可发布、不可修改他人）
function article_perm(string $username): array {
    global $pdo;
    $s = $pdo->prepare("SELECT can_view, can_publish, can_edit FROM article_permissions WHERE username=?");
    $s->execute([$username]);
    $r = $s->fetch();
    return $r ?: ['can_view'=>1, 'can_publish'=>1, 'can_edit'=>0];
}

// 判断用户能否查看某文章
function article_can_view(array $art, string $username): bool {
    global $pdo;
    if ($art['is_announcement']) return true;              // 公告人人可见
    if ($art['is_public']) return article_perm($username)['can_view'] == 1; // 公开：需查看权限
    return $art['author'] === $username || isAdmin();      // 私密：仅作者/管理员
}

// 判断用户能否编辑某文章
function article_can_edit(array $art, string $username): bool {
    if (isAdmin()) return true;
    if ($art['author'] === $username) return true;         // 作者可改自己的
    return article_perm($username)['can_edit'] == 1;       // 或被授权修改
}
