<?php

return [
    'botConfig' => [
        'bots' => [
            // 这个名字写死在代码里了不建议改了，改下面的token就行了 前往 https://t.me/botfather 创建一个新的bot
            'randallanjie_bot' => [
                'token' => '',
            ],
        ]
    ],
    // 管理员设置
    'adminId' => '',
    // 群组设置
    'groupSetting' => [
        // 群组id，要以-开头
        'chat_id' => '',
        // 是否允许在群组里发送通知
        'allow_notify' => true,
    ]
];