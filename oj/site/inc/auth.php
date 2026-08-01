<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = currentUser();
    if (!$user) { header('Location: login.php'); exit; }
    
    $roles = ['super_admin' => 3, 'admin' => 2, 'user' => 1];
    if (($roles[$user['role']] ?? 0) < ($roles[$role] ?? 0)) {
        header('HTTP/1.0 403 Forbidden');
        die('权限不足');
    }
}

function isAdmin(): bool {
    $user = currentUser();
    return $user && in_array($user['role'], ['super_admin', 'admin']);
}

function isSuperAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'super_admin';
}

function generateInviteCode(): string {
    return bin2hex(random_bytes(24)); // 48字符
}

function canViewProblem($pdo, $problem, $username, $role) {
    if (in_array($role, ['super_admin','admin'])) return true;
    if (($problem['visibility'] ?? 'public') === 'public') return true;
    if ($problem['created_by'] === $username) return true;
    // 用户直接授权
    $s = $pdo->prepare("SELECT id FROM problem_permissions WHERE problem_id=? AND username=?");
    $s->execute([$problem['problem_id'], $username]);
    if ($s->fetch()) return true;
    // 组授权 team->组名
    $s = $pdo->prepare("SELECT username FROM problem_permissions WHERE problem_id=? AND username LIKE 'team->%'");
    $s->execute([$problem['problem_id']]);
    foreach ($s->fetchAll() as $row) {
        $teamName = substr($row['username'], 6);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM user_group_members m JOIN user_groups g ON m.group_id=g.id WHERE g.name=? AND m.username=?");
        $chk->execute([$teamName, $username]);
        if ($chk->fetchColumn() > 0) return true;
    }
    return false;
}
