<?php

$availablePayment = [];
$prefix = 'AVAILABLE_PAYMENT_';
foreach ($_ENV as $envVar => $value) {
    if (strpos($envVar, $prefix) === 0) {
        $availablePayment[] = $value;
    }
}

return [
    // 易支付接口
    'epay' => [
        'urlBase' => env('PAY_URL', ''),
        // 商户id
        'id' => env('PAY_MCHID', ''),
        // 商户密钥
        'key' => env('PAY_KEY', ''),
        // 支持的支付方式
        'availablePayment' => $availablePayment,
    ],

];