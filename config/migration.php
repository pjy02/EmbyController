<?php

return [
    'default' => 'mysql',
    
    'paths' => [
        'migrations' => 'database/migrations',
        'seeds' => 'database/seeds'
    ],
    
    'environments' => [
        'default_migration_table' => env('DB_PREFIX', 'rc_') . 'migrations', // 添加表前缀
        'default_database' => 'mysql',
        'mysql' => [
            'adapter' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'name' => env('DB_NAME', 'forge'),
            'user' => env('DB_USER', 'forge'),
            'pass' => env('DB_PASS', ''),
            'port' => env('DB_PORT', '3306'),
            'charset' => env('DB_CHARSET', 'utf8'),
            'table_prefix' => env('DB_PREFIX', 'rc_'), // 添加表前缀配置
        ],
    ],
];