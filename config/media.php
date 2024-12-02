<?php

$lineList = [];
$prefix = 'EMBY_LINE_LIST_';
foreach ($_ENV as $envVar => $value) {
    if (strpos($envVar, $prefix) === 0) {
        $parts = explode('_', substr($envVar, strlen($prefix)));
        $index = $parts[0];
        $subKey = strtolower($parts[1]);

        if (!isset($lineList[$index])) {
            $lineList[$index] = [];
        }
        $lineList[$index][$subKey] = $value;
    }
}
ksort($lineList);
$lineList = array_values($lineList);

return [
    // 媒体服务器地址
    'urlBase' => env('EMBY_URLBASE', 'http://127.0.0.1:8096/emby/'),
    // 媒体服务器api key
    'apiKey' => env('EMBY_APIKEY', ''),
    // 模板用户id
    'UserTemplateId' => env('EMBY_TEMPLATEUSERID', ''),
    // crontab密码
    'crontabKey' => 'randallanjie',
    // 线路
    'lineList' => $lineList,
    // Emby中管理员用户id
    'adminUserId' => env('EMBY_ADMINUSERID', ''),
];