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
