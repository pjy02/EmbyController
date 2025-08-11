<?php

use think\facade\Route;

// 日志管理路由组
Route::group('log', function () {
    // 日志管理页面
    Route::get('index', 'LogController@index');
    
    // API接口
    Route::get('list', 'LogController@getLogList');
    Route::post('delete', 'LogController@deleteLog');
    Route::post('batch-delete', 'LogController@batchDeleteLogs');
    Route::post('clean-expired', 'LogController@cleanExpiredLogs');
    Route::get('preview', 'LogController@previewLog');
    Route::get('config', 'LogController@getConfig');
    Route::post('config', 'LogController@saveConfig');
    Route::get('check-auto-clean', 'LogController@checkAutoClean');
});

// 添加到管理菜单的路由
Route::get('admin/logs', 'LogController@index');