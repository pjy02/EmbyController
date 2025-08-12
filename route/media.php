<?php

use think\facade\Route;

// 设备管理路由
Route::group('media/device', function () {
    // 获取用户设备列表
    Route::get('index', 'media/DeviceController/index');
    
    // 获取设备详情
    Route::get('show/:deviceId', 'media/DeviceController/show');
    
    // 更新设备信息
    Route::post('update', 'media/DeviceController/update');
    
    // 停用设备
    Route::post('deactivate', 'media/DeviceController/deactivate');
    
    // 获取用户会话列表
    Route::get('sessions', 'media/DeviceController/sessions');
    
    // 获取设备历史记录
    Route::get('history/:deviceId', 'media/DeviceController/history');
    
    // 获取设备统计信息
    Route::get('statistics', 'media/DeviceController/statistics');
});

// 设备状态同步命令路由（如果需要通过HTTP触发）
Route::group('media/command', function () {
    // 同步设备状态
    Route::get('sync-device-status', 'media/command/SyncDeviceStatus/execute');
    
    // 注册事件监听器
    Route::get('register-event-listeners', 'media/command/RegisterEventListeners/register');
});

// 媒体同步相关路由
Route::group('media/sync', function () {
    Route::post('all', 'media/sync/syncAllUsers');
    Route::post('user/:userId', 'media/sync/syncUser');
    Route::post('cleanup', 'media/sync/cleanupOldRecords');
    Route::get('stats', 'media/sync/getSyncStats');
    Route::post('manual', 'media/sync/manualSync');
});