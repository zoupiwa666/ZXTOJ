<?php
require __DIR__.'/../inc/config.php';
require __DIR__.'/../inc/auth.php';
requireLogin();

$method = $_GET['m'] ?? 'view';
$me = currentUser();

// 列表可见性判断
function canViewList($pdo, $list, $user) {
    if (in_array($user['role'], ['admin','super_admin'])) return true;
    if ($list['visibility'] === 'public') return true;
    if ($list['created_by'] === $user['username']) return true;
    // 直接授权
    $s = $pdo->prepare("SELECT id FROM list_permissions WHERE list_id=? AND username=?");
    $s->execute([$list['id'], $user['username']]);
    if ($s->fetch()) return true;
    // 组授权 team->组名
    $s = $pdo->prepare("SELECT username FROM list_permissions WHERE list_id=? AND username LIKE 'team->%'");
    $s->execute([$list['id']]);
    foreach ($s->fetchAll() as $row) {
        $teamName = substr($row['username'], 6);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM user_group_members m JOIN user_groups g ON m.group_id=g.id WHERE g.name=? AND m.username=?");
        $chk->execute([$teamName, $user['username']]);
        if ($chk->fetchColumn() > 0) return true;
    }
    return false;
}

switch ($method) {
    case 'create': // 创建列表
        requireRole('admin');
        $name = trim($_POST['name'] ?? '');
        $vis = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
        $tags = trim($_POST['tags'] ?? '');
        if (!$name) die(json_encode(['error'=>'缺少列表名']));
        $pdo->prepare("INSERT INTO lists (name,visibility,created_by,tags) VALUES (?,?,?,?)")->execute([$name,$vis,$me['username'],$tags]);
        die(json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]));

    case 'update': // 更新列表信息
        requireRole('admin');
        $id = intval($_POST['list_id'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM lists WHERE id=?"); $s->execute([$id]); $l = $s->fetch();
        if (!$l) die(json_encode(['error'=>'列表不存在']));
        if ($l['created_by'] !== $me['username'] && $me['role'] !== 'super_admin') die(json_encode(['error'=>'无权限']));
        $name = trim($_POST['name'] ?? $l['name']);
        $vis = ($_POST['visibility'] ?? $l['visibility']) === 'private' ? 'private' : 'public';
        $tags = trim($_POST['tags'] ?? $l['tags']);
        $pdo->prepare("UPDATE lists SET name=?,visibility=?,tags=? WHERE id=?")->execute([$name,$vis,$tags,$id]);
        die(json_encode(['ok'=>true]));

    case 'delete': // 删除列表
        requireRole('admin');
        $id = intval($_GET['id'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM lists WHERE id=?"); $s->execute([$id]); $l = $s->fetch();
        if (!$l) die(json_encode(['error'=>'列表不存在']));
        if ($l['created_by'] !== $me['username'] && $me['role'] !== 'super_admin') die(json_encode(['error'=>'无权限']));
        $pdo->prepare("DELETE FROM list_problems WHERE list_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM lists WHERE id=?")->execute([$id]);
        die(json_encode(['ok'=>true]));

    case 'add': // 加题目
        requireRole('admin');
        $id = intval($_POST['list_id'] ?? 0); $pid = trim($_POST['problem_id'] ?? '');
        $s = $pdo->prepare("SELECT * FROM lists WHERE id=?"); $s->execute([$id]); $l = $s->fetch();
        if (!$l) die(json_encode(['error'=>'列表不存在']));
        if ($l['created_by'] !== $me['username'] && $me['role'] !== 'super_admin') die(json_encode(['error'=>'无权限']));
        if (!$pid) die(json_encode(['error'=>'缺少题号']));
        $ck = $pdo->prepare("SELECT id FROM problems WHERE problem_id=?"); $ck->execute([$pid]);
        if (!$ck->fetch()) die(json_encode(['error'=>'题目不存在']));
        $mx = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM list_problems WHERE list_id=?"); $mx->execute([$id]);
        $pdo->prepare("INSERT IGNORE INTO list_problems (list_id,problem_id,sort_order) VALUES (?,?,?)")->execute([$id,$pid,$mx->fetchColumn()]);
        die(json_encode(['ok'=>true]));

    case 'remove': // 移除题目
        requireRole('admin');
        $id = intval($_POST['list_id'] ?? 0); $pid = trim($_POST['problem_id'] ?? '');
        $s = $pdo->prepare("SELECT * FROM lists WHERE id=?"); $s->execute([$id]); $l = $s->fetch();
        if (!$l) die(json_encode(['error'=>'列表不存在']));
        if ($l['created_by'] !== $me['username'] && $me['role'] !== 'super_admin') die(json_encode(['error'=>'无权限']));
        $pdo->prepare("DELETE FROM list_problems WHERE list_id=? AND problem_id=?")->execute([$id,$pid]);
        die(json_encode(['ok'=>true]));

    case 'grant': // 授权用户/组查看列表
        requireRole('admin');
        $id = intval($_POST['list_id'] ?? 0); $uname = trim($_POST['username'] ?? '');
        $s = $pdo->prepare("SELECT * FROM lists WHERE id=?"); $s->execute([$id]); $l = $s->fetch();
        if (!$l) die(json_encode(['error'=>'列表不存在']));
        if ($l['created_by'] !== $me['username'] && $me['role'] !== 'super_admin') die(json_encode(['error'=>'无权限']));
        if (!$uname) die(json_encode(['error'=>'缺少用户名']));
        $pdo->prepare("INSERT IGNORE INTO list_permissions (list_id,username,granted_by) VALUES (?,?,?)")->execute([$id,$uname,$me['username']]);
        die(json_encode(['ok'=>true]));

    case 'revoke': // 撤销授权
        requireRole('admin');
        $id = intval($_POST['list_id'] ?? 0); $uname = trim($_POST['username'] ?? '');
        $pdo->prepare("DELETE FROM list_permissions WHERE list_id=? AND username=?")->execute([$id,$uname]);
        die(json_encode(['ok'=>true]));

    case 'perms': // 查看列表授权列表
        $id = intval($_GET['id'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM lists WHERE id=?"); $s->execute([$id]); $l = $s->fetch();
        if (!$l) die(json_encode(['error'=>'列表不存在']));
        if (!canViewList($pdo, $l, $me) && !in_array($me['role'],['admin','super_admin'])) die(json_encode(['error'=>'无权限']));
        $ps = $pdo->prepare("SELECT username, granted_by FROM list_permissions WHERE list_id=? ORDER BY username"); $ps->execute([$id]);
        die(json_encode($ps->fetchAll()));

    case 'search': // 搜索公开题单（按名字或标签）
        $q = trim($_GET['q'] ?? '');
        $tag = trim($_GET['tag'] ?? '');
        $sql = "SELECT * FROM lists WHERE visibility='public'";
        $params = [];
        if ($q !== '') { $sql .= " AND name LIKE ?"; $params[] = "%$q%"; }
        if ($tag !== '') { $sql .= " AND (tags LIKE ? OR tags LIKE ? OR tags LIKE ?)"; $params[] = "$tag"; $params[] = "%,$tag"; $params[] = "$tag,%"; }
        $sql .= " ORDER BY id DESC";
        $s = $pdo->prepare($sql); $s->execute($params);
        $out = [];
        foreach ($s->fetchAll() as $l) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM list_problems WHERE list_id=?"); $cnt->execute([$l['id']]);
            $out[] = ['id'=>$l['id'],'name'=>$l['name'],'visibility'=>$l['visibility'],'tags'=>$l['tags'],'count'=>$cnt->fetchColumn(),'created_by'=>$l['created_by']];
        }
        die(json_encode($out));

    case 'list': // 列出所有可查看的列表
        $rows = $pdo->query("SELECT * FROM lists ORDER BY id DESC")->fetchAll();
        $out = [];
        foreach ($rows as $l) {
            if (!canViewList($pdo, $l, $me)) continue;
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM list_problems WHERE list_id=?"); $cnt->execute([$l['id']]);
            $out[] = ['id'=>$l['id'],'name'=>$l['name'],'visibility'=>$l['visibility'],'tags'=>$l['tags'],'count'=>$cnt->fetchColumn()];
        }
        die(json_encode($out));

    case 'view': // 查看单个列表详情
        $id = intval($_GET['id'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM lists WHERE id=?"); $s->execute([$id]); $l = $s->fetch();
        if (!$l) die(json_encode(['error'=>'列表不存在']));
        if (!canViewList($pdo, $l, $me)) die(json_encode(['error'=>'无权限']));
        $ps = $pdo->prepare("SELECT p.problem_id, p.title FROM list_problems lp JOIN problems p ON lp.problem_id=p.problem_id WHERE lp.list_id=? ORDER BY lp.sort_order");
        $ps->execute([$id]);
        die(json_encode(['id'=>$l['id'],'name'=>$l['name'],'visibility'=>$l['visibility'],'tags'=>$l['tags'],'created_by'=>$l['created_by'],'can_manage'=>in_array($me['role'],['admin','super_admin'])||$l['created_by']===$me['username'],'problems'=>$ps->fetchAll()]));
}
