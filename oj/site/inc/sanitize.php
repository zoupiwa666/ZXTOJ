<?php
// 输入净化：防止异常字符（控制字符/空字节等）破坏数据库存储与显示
// 用法: require_once __DIR__.'/sanitize.php'; 然后 sanitize_text($input)

// 文本净化：移除控制字符（保留换行/制表），可选截断长度
function sanitize_text(string $s, int $maxLen = 65535): string {
    // 移除 NUL 和除 \n \t 外的控制字符
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    if (mb_strlen($s, 'utf-8') > $maxLen) {
        $s = mb_substr($s, 0, $maxLen, 'utf-8');
    }
    return $s;
}

// 短文本净化（用户名/题单名等）：去首尾空白 + 控制字符 + 截断
function sanitize_name(string $s, int $maxLen = 50): string {
    $s = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $s));
    if (mb_strlen($s, 'utf-8') > $maxLen) {
        $s = mb_substr($s, 0, $maxLen, 'utf-8');
    }
    return $s;
}

// @提及渲染：@用户名 存在则转为 Markdown 链接 [@xxx](user.php?name=xxx)
// 前端 marked 渲染成 <a href="user.php?name=xxx">，自动获得用户名片悬停
function render_mentions(string $content): string {
    global $pdo;
    return preg_replace_callback('/(?<![A-Za-z0-9_@])@([A-Za-z0-9_]{1,50})/', function($m) use ($pdo) {
        $name = $m[1];
        $s = $pdo->prepare("SELECT 1 FROM users WHERE username=?");
        $s->execute([$name]);
        return $s->fetch() ? "[@$name](user.php?name=".urlencode($name).")" : $m[0];
    }, $content);
}

// HTML 白名单净化（聊天等可渲染内容）：
//   允许: input/textarea/button/font/p/span 等装饰性标签
//   禁止: script/form/iframe/head/meta/style/object/embed 等危险标签
//   同时剥离 on* 事件属性和 javascript:/vbscript:/data: 协议
function sanitize_html(string $s): string {
    $allowed = '<input><textarea><button><font><p><span><b><i><u><strong><em><br>'
              .'<a><code><pre><h1><h2><h3><h4><ul><ol><li><blockquote><img>'
              .'<table><tbody><tr><td><th><div><hr><sup><sub>';
    // 1) 移除所有 on* 事件属性（onclick/onerror/onload/onmouseover 等）
    $s = preg_replace("/\s+on[a-z]+\s*=\s*(\"[^\"]*\"|'[^']*'|[^\s>]+)/i", '', $s);
    // 2) 移除 href/src/action 中的 javascript:/vbscript:/data: 协议
    $s = preg_replace_callback("/\s+(?:href|src|action)\s*=\s*(\"[^\"]*\"|'[^']*'|[^\s>]+)/i",
        function($m) {
            if (preg_match("/^\s*[\"']?\s*(?:javascript|vbscript|data):/i", $m[1])) return '';
            return $m[0];
        }, $s);
    // 3) font-size 钳制：超过 150px 自动替换为 150px（防止字体过大）
    $s = preg_replace_callback('/font-size\s*:\s*([\d.]+)\s*(px|pt|em|rem)?/i',
        function($m) {
            $num = (float)$m[1];
            $unit = strtolower($m[2] ?? 'px');
            $px = $num * ($unit==='pt' ? 1.333 : (($unit==='em'||$unit==='rem') ? 16 : 1));
            return $px > 150 ? 'font-size:150px' : $m[0];
        }, $s);
    // 4) 白名单过滤：白名单外的标签全部剥掉（保留文本内容）
    $s = strip_tags($s, $allowed);
    return $s;
}
