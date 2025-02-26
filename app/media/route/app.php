<?php

use think\facade\Route;

// 用户相关路由组
Route::group('media', function () {
    Route::group('user', function () {
        // 不需要登录验证的路由
        Route::get('terms', 'terms');
        Route::get('privacy', 'privacy');
        Route::get('login', 'login');
        Route::post('login', 'login');
        Route::get('register', 'register');
        Route::post('register', 'register');
        Route::get('forgot', 'forgot');
        Route::post('forgot', 'forgot');
        
        // 需要登录验证的路由
        Route::get('logout', 'logout')->middleware('auth');
        // ... 其他需要登录验证的路由
    })->prefix('User/');
}); 