<?php
function getClientIP(): string {
    // 1. Cloudflare CDN 专用 Header（最可靠）
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    // 2. 其他反向代理（Nginx、负载均衡等）
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    // 3. 标准转发链（可能包含多个 IP，取第一个）
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    // 4. 直连兜底
    return $_SERVER['REMOTE_ADDR'];
}

$clientIP = getClientIP();
echo htmlspecialchars($clientIP);
?>
