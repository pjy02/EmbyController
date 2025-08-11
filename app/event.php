<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
        
        // 设备状态变更事件监听器
        'app\media\event\DeviceStatusChangedEvent' => [
            'app\media\listener\DeviceHistoryListener',
            'app\media\listener\UpdateDeviceDisplayListener',
            'app\media\listener\SessionHistoryListener',
        ],
    ],

    'subscribe' => [
    ],
];
