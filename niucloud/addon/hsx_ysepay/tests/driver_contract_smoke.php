<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_ysepay\core\pay\Ysepay;
use core\pay\BasePay;

$reflection = new ReflectionClass(Ysepay::class);
if (!$reflection->isSubclassOf(BasePay::class)) {
    throw new RuntimeException('Ysepay driver must extend BasePay');
}

foreach ([
    'pay', 'web', 'wap', 'app', 'mini', 'mp', 'scan', 'pos',
    'close', 'refund', 'notify', 'getOrder', 'getRefund',
    'transfer', 'getTransfer', 'transferCancel',
] as $method) {
    if (!$reflection->hasMethod($method) || !$reflection->getMethod($method)->isPublic()) {
        throw new RuntimeException('Missing public driver method: ' . $method);
    }
}

$driverSource = file_get_contents(dirname(__DIR__) . '/core/pay/Ysepay.php');
if (!is_string($driverSource)
    || !str_contains($driverSource, "'access_mode'")
    || !str_contains($driverSource, '/openapi/unify/basePay/scan/weChatPay/js')
    || !str_contains($driverSource, '/openapi/order/createOrder')
    || !str_contains($driverSource, "'jsapi'")
    || str_contains($driverSource, "busi_code'] ?? '00510103'")) {
    throw new RuntimeException('JS pay and cashier access modes must coexist in the driver');
}

$info = json_decode((string)file_get_contents(dirname(__DIR__) . '/info.json'), true);
if (($info['key'] ?? '') !== 'hsx_ysepay') {
    throw new RuntimeException('Addon key must use the hsx_ysepay two-part naming convention');
}

echo "OK: hsx_ysepay driver contract passed\n";
