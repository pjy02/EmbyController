<?php

$availablePayment = [];
$prefix = 'AVAILABLE_PAYMENT_';
foreach ($_ENV as $envVar => $value) {
    if (strpos($envVar, $prefix) === 0) {
        $availablePayment[] = $value;
    }
}

$enableEPay = true;

if (env('PAY_URL', '') == '' || env('PAY_MCHID', '') == '' || env('PAY_KEY', '') == '' || count($availablePayment) == 0) {
    $enableEPay = false;
}

return [
    // 易支付接口
    'epay' => [
        // 是否启用
        'enable' => $enableEPay,
        // 接口地址
        'urlBase' => env('PAY_URL', ''),
        // 商户id
        'id' => env('PAY_MCHID', ''),
        // 商户密钥
        'key' => env('PAY_KEY', ''),
        // 支持的支付方式
        'availablePayment' => $availablePayment,
    ],
];