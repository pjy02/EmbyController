<?php
return [
    'socks5' => [
        'enable' => env('SOCKS5_ENABLE', false),
        'host' => env('SOCKS5_HOST', '127.0.0.1'),
        'port' => env('SOCKS5_PORT', 1080),
        'username' => env('SOCKS5_USERNAME', ''),
        'password' => env('SOCKS5_PASSWORD', ''),
    ]
]; 