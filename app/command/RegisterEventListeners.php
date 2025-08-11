<?php

namespace app\command;

use app\media\event\EventDispatcher;
use app\media\listener\UpdateDeviceDisplayListener;
use app\media\listener\DeviceHistoryListener;

class RegisterEventListeners
{
    /**
     * 注册事件监听器
     */
    public function register()
    {
        $eventDispatcher = new EventDispatcher();
        
        // 注册设备显示更新监听器
        $eventDispatcher->listen(
            \app\media\event\DeviceStatusChangedEvent::class,
            UpdateDeviceDisplayListener::class
        );
        
        // 注册设备历史记录监听器
        $eventDispatcher->listen(
            \app\media\event\DeviceStatusChangedEvent::class,
            DeviceHistoryListener::class
        );
        
        echo "事件监听器注册成功\n";
    }
}