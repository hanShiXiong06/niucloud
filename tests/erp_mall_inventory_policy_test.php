<?php
declare(strict_types=1);
require dirname(__DIR__) . '/niucloud/vendor/autoload.php';

use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot as Snapshot;
use addon\hsx_erp\app\support\ErpMallDevicePolicy as Policy;

$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) throw new RuntimeException($message);
};
$sku = ['sku_no' => '357465822199406', 'erp_asset_id' => 0, 'is_unique' => 1];
$goods = ['source' => '1', 'is_proxy' => 0];
$extend = Snapshot::extend(['campaign' => 'preserved'], $sku, $goods);
$assert($extend['campaign'] === 'preserved', '保留其他订单扩展');
$assert(!isset(Snapshot::extend(['erp_sale_at' => 1], $sku, $goods)['erp_sale_at']), '新订单不信任扩展传入的成交时点');
$assert($extend['erp_device']['imei'] === $sku['sku_no'], '完整IMEI进入订单快照');
$credit = Snapshot::withSaleTime($extend, 1700000000);
$assert(Snapshot::withSaleTime($credit, 1700001000)['erp_sale_at'] === 1700000000, '挂账时点只冻结一次');
$assert(Snapshot::saleTime(['pay_time' => 0], [['extend' => $credit]]) === 1700000000, '挂账重推使用原确认时点');
$assert(Snapshot::saleTime(['pay_time' => 1700003000], [['extend' => $credit]]) === 1700003000, '已付款使用原始支付时间');
$assert(Snapshot::fromOrderLine(['extend' => $extend], ['erp_asset_id' => 99])['erp_asset_id'] === 0, '付款重放不能跟随事后关联切换记账通道');
$assert(Snapshot::fromOrderLine(['inventory_source' => 'self_owned'], ['erp_asset_id' => 99])['erp_asset_id'] === 0, '旧原生订单也不能切换通道重复记账');
$assert(Snapshot::fromOrderLine(['inventory_source' => 'erp_asset'], ['erp_asset_id' => 99])['erp_asset_id'] === 99, '原ERP订单仍走标准销售');
$assert(Snapshot::fromSku(['sku_no' => '123456'])['imei'] === '', '不能把尾号作IMEI');
$assert(Snapshot::fromSku(['sku_no' => 'iPhone18', 'is_unique' => 0])['sn'] === '', '标品编码不能猜成SN');
$assert(Snapshot::fromSku(['sku_no' => 'jw2nd9nvr2', 'is_unique' => 1])['sn'] === 'JW2ND9NVR2', '单台完整SN归一化');
$assert(Snapshot::fromSku(['device_snapshot' => '{"identity":{"imei":"357465822199406","serial_number":"jw2nd9nvr2"}}'])['sn'] === 'JW2ND9NVR2', '使用原始采集身份');
$source = ['device' => $extend['erp_device'], 'stock' => 1, 'quantity' => 1, 'sale_status' => 'available', 'cost_price' => 6000];
$asset = ['id' => 7, 'site_id' => 100005, 'imei' => $sku['sku_no'], 'sn' => '', 'ownership_type' => 'owned', 'status' => 'in_stock', 'total_cost' => 6100, 'sale_item_id' => 0, 'stock_in_at' => 100, 'refurbish_status' => 'none'];
$inspect = static fn(array $s = [], array $a = []): array => Policy::inspect(100005, array_replace_recursive($source, $s), [array_replace($asset, $a)]);
$assert($inspect()['state'] === 'match', '唯一串号可复用原资产');
$conflictDevice = Snapshot::fromSku($sku + ['device_snapshot' => ['identity' => ['imei' => '357465822199407']]], $goods);
$assert(Policy::inspect(100005, array_replace($source, ['device' => $conflictDevice]), [$asset])['state'] === 'conflict', '商城填写串号与采集原串号冲突时不猜测');
$assert(Policy::inspect(100005, $source, [])['state'] === 'opening', 'ERP无记录必须明确期初');
$assert(Policy::inspect(100005, array_replace($source, ['cost_price' => 0]), [])['state'] === 'conflict', '缺失成本不当零成本');
$assert(Policy::inspect(100005, $source, [$asset, $asset])['state'] === 'conflict', '重复串号不猜选资产');
$assert($inspect(['device' => ['source' => '100024']])['state'] === 'conflict', '跨站代理不能变本站自有');
$assert($inspect(['device' => ['is_proxy' => 1, 'source' => '100024']])['state'] === 'conflict', '外站来源仍严格隔离');
foreach (['', '0', '1', '100005'] as $localSource) {
    $assert($inspect(['device' => ['is_proxy' => 1, 'source' => $localSource]])['state'] === 'match', '废弃代理标记不能排除本地来源：' . $localSource);
}
$assert($inspect(['device' => ['is_proxy' => 0, 'source' => '100024']])['state'] === 'conflict', '清空旧标记也不能将外站商品当作自营');
$assert($inspect([], ['site_id' => 100024])['state'] === 'conflict', 'ERP租户隔离');
$assert($inspect(['quantity' => 2])['state'] === 'conflict', '多台同串号必须拒绝');
$assert($inspect(['stock' => 0])['state'] === 'conflict', '售罄不能期初在库');
$assert($inspect(['sale_status' => 'locked'])['state'] === 'conflict', '锁定商城货不作为期初');
$assert($inspect([], ['status' => 'sold'])['state'] === 'conflict', '已售ERP货不能重复卖');
$assert($inspect([], ['ownership_type' => 'consigned'])['state'] === 'conflict', '非自有物权不强行变更');
$assert($inspect([], ['refurbish_status' => 'processing'])['state'] === 'conflict', '整备未完不绕过');
$assert($inspect(['erp_asset_id' => 8])['state'] === 'conflict', '不覆盖原有映射');
$assert($inspect(['erp_asset_id' => 7])['state'] === 'linked', '已关联可识别');
$assert($inspect(['device' => ['sn' => 'ABC123456']], ['sn' => 'XYZ123456'])['state'] === 'conflict', 'IMEI/SN冲突拒绝');
$assert(Policy::inspect(100005, $source + ['sale_at' => 50], [$asset], true)['state'] === 'conflict', '避免旧订单关联到后来回购资产');
try { Snapshot::extend(['other' => str_repeat('a', 1000)], $sku, $goods); $assert(false, '超长扩展必须拒绝而非截断'); }
catch (\core\exception\CommonException $e) { $assert(str_contains($e->getMessage(), '过长'), '超长给出明确提示'); }
echo "PASS ERP/mall identity policy: {$count} assertions\n";
