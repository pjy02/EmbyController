<?php

return [
    // 事件监听器配置
    'listeners' => [
        // 设备状态变更事件监听器
        \app\media\event\DeviceStatusChangedEvent::class => [
            \app\media\listener\UpdateDeviceDisplayListener::class,
            \app\media\listener\DeviceHistoryListener::class,
            \app\media\listener\SessionHistoryListener::class,
        ],
    ],
    
    // 事件分发器配置
    'dispatcher' => [
        'enable_cache' => true,
        'cache_ttl' => 3600, // 1小时缓存
    ],
];