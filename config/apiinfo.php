<?php

$xfyunList = [];
$prefix = 'XFYUNLIST_';
foreach ($_ENV as $envVar => $value) {
    if (strpos($envVar, $prefix) === 0) {
        $parts = explode('_', substr($envVar, strlen($prefix)));
        $key = strtolower($parts[0]);
        $subKey = strtolower($parts[1]);

        if (!isset($xfyunList[$key])) {
            $xfyunList[$key] = [];
        }
        $xfyunList[$key][$subKey] = $value;
    }
}

$cloudflareTurnstile = [];
$prefix = 'CLOUDFLARE_TURNSTILE_';
foreach ($_ENV as $envVar => $value) {
    if (strpos($envVar, $prefix) === 0) {
        $parts = explode('_', substr($envVar, strlen($prefix)));
        $type = strtolower($parts[0]);
        $subKey = strtolower($parts[1]);

        if (!isset($cloudflareTurnstile[$type])) {
            $cloudflareTurnstile[$type] = [];
        }
        $cloudflareTurnstile[$type][$subKey] = $value;
    }
}

return [
    // 讯飞星火API
    'xfyunList' => $xfyunList,

    // Cloudflarer Turnstile
    'cloudflareTurnstile' => $cloudflareTurnstile,
];