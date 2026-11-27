<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\support\ErpDomainEvent;
use addon\hsx_erp\app\support\ErpMoney;

if (ErpMoney::add('0.10', '0.20') !== '0.30') {
    throw new RuntimeException('ERP金额加法精度测试失败');
}
if (ErpMoney::subtract('100.00', '33.33') !== '66.67') {
    throw new RuntimeException('ERP金额减法精度测试失败');
}

$event = ErpDomainEvent::create(
    1,
    'erp.asset.stocked.v1',
    'foundation-smoke-event',
    'asset',
    1,
    ['type' => 'staff', 'id' => 1, 'name' => 'tester'],
    ['plugin' => 'hsx_erp', 'type' => 'test', 'id' => 1],
    ['asset_id' => 1, 'payable_amount' => '100.00'],
    time()
);
ErpDomainEvent::validate($event);

$event = ErpDomainEvent::create(
    1,
    'erp.refurbishment.required.v1',
    'foundation-smoke-refurbishment-required',
    'asset',
    1,
    ['type' => 'staff', 'id' => 1, 'name' => 'tester'],
    ['plugin' => 'hsx_erp', 'type' => 'stock_in', 'id' => 1],
    ['asset_id' => 1, 'required' => true, 'suggested_items' => [['item_name' => '更换电池']]],
    time()
);
ErpDomainEvent::validate($event);

echo "ERP foundation smoke test passed.\n";
