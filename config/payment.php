<?php

return [
    // 易支付接口
    'epay' => [
        // 易支付接口 如 https://pay.randallanjie.com/ 一定要以/结尾
        'urlBase' => 'https://pay.randallanjie.com/',
        // 商户id
        'id' => '',
        // 商户密钥
        'key' => '',
        // 支持的支付方式
        'availablePayment' => [
            'alipay',
//        'wechat',
//        'qqpay',
//        'bank',
        ],
    ],
];