<?php


return [
    // 讯飞星火API
    'xfyunList' => [
        '随便一个名字就好' => [
            'appid' => 'XXXXX',
            'apikey' => 'XXXXXXXXXXXXXXXXXXXXXXXXX',
            'apiSecret' => 'XXXXXXXXXXXXXXXXXXXXXXXXX',
        ]
    ],
    // Cloudflarer Turnstile
    'cloudflareTurnstile' => [
        // 登录注册
        'non-interactive' => [
            'sitekey' => 'XXXXXXXXXXXXXXXXXXXXXXXXX',
            'secret' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ],
        // 签到
        'invisible' => [
            'sitekey' => 'XXXXXXXXXXXXXXXXXXXXXXXXX',
            'secret' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ]
    ]
];