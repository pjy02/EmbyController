<?php

$lineList = [];
$prefix = 'EMBY_LINE_LIST_';

$envVars = !empty($_ENV) ? $_ENV : getenv();

foreach ($envVars as $envVar => $value) {
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
    'crontabKey' => env('CRONTAB_KEY', ''),
    // 线路
    'lineList' => $lineList,
    // Emby中管理员用户id
    'adminUserId' => env('EMBY_ADMINUSERID', ''),

    'clientList' => [
        'Emby Web',
        'Emby for iOS',
        'Emby for Android',
        'Emby Theater',
        'Emby for macOS',
        'Emby for Apple TV',
        'Infuse-Direct',
        'SenPlayer',
        'Fileball',
        'AfuseKt',
        'Conflux',
        'Yamby',
        'Xfuse',
        'Terminus Player',
        'AfuseKt/(Linux;Android Release)Player',
        'Reflix',
        'Forward',
        'Hills',
        'femor/1.0.64',
        'Tsukimi',
        'iPlay',
        'Filebox',
        'AndroidTv'
    ],

    'clientBlackList' => [
        'vidhub',
        'Infuse-Library',
        '网易爆米花',
        'Widget'
    ]
];