<?php

/**
 * 配置文件
 */
return [
    'scheme'   => env('MAIL_TYPE', 'smtp'),
    'host'     => env('MAIL_HOST', 'smtp.gmail.com'),
    'username' => env('MAIL_USER', 'randall@randallanjie.com'),
    'password' => env('MAIL_PASS', 'password'),
    'port'     => (int)env('MAIL_PORT', 587),
    'options'  => [],
    // 'dsn'             => '',
    'embed'    => 'cid:',
    'from'     => [
        'address' => env('MAIL_FROM_EMAIL', 'randall@randallanjie.com'),
        'name'    => env('MAIL_FROM_NAME', 'RandallAnjie'),
    ]
];
