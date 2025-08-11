<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'websocket' => 'app\command\WebSocket',
        'sync:device-status' => 'app\command\SyncDeviceStatus',
    ],
];
