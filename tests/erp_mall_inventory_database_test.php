<?php
declare(strict_types=1);
// 仅本地专用空站点、仅事务样本。所有业务写入 finally 回滚，绝不调用外部通知。
if (getenv('HSX_MALL_ROLLBACK_TEST') !== '1') { fwrite(STDERR, "Set HSX_MALL_ROLLBACK_TEST=1\n"); exit(2); }
require dirname(__DIR__) . '/niucloud/vendor/autoload.php';

use think\facade\Db;
use think\facade\Event;
use addon\hsx_erp\app\service\admin\ErpMallInventoryService;
use addon\hsx_erp\app\service\admin\ErpFinanceService;
use addon\phone_shop\app\listener\erp\ErpMallInventoryProvider;
use addon\phone_shop\app\listener\erp\PhoneShopNativeOrderPaidToErp;
use addon\phone_shop\app\listener\erp\PhoneShopOrderPaidToErp;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use addon\hsx_erp\app\listener\ErpExternalSaleRecordedRequested;

(new think\App())->initialize();
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"); exit(1); });
if (!in_array(config('database.connections.mysql.hostname'), ['localhost', '127.0.0.1'], true)) throw new RuntimeException('仅允许本地测试库');
$site = 900000914;
$tables = ['site', 'erp_asset', 'erp_party', 'erp_sale_order', 'erp_sale_item', 'erp_receivable', 'erp_payable', 'erp_money_ledger', 'erp_account_ledger', 'erp_settlement', 'erp_settlement_link', 'erp_capital_account', 'erp_asset_ledger', 'erp_outbox_event', 'erp_inbox_event', 'erp_operation_log', 'erp_warehouse', 'erp_warehouse_location', 'phone_shop_goods', 'phone_shop_goods_sku', 'phone_shop_order', 'phone_shop_order_goods'];
foreach ($tables as $table) {
    if (Db::name($table)->where('site_id', $site)->count() > 0) throw new RuntimeException('隔离站点已被使用：' . $table);
    $fullName = config('database.connections.mysql.prefix') . $table;
    $meta = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$fullName]);
    if (strtoupper((string)($meta[0]['ENGINE'] ?? '')) !== 'INNODB') throw new RuntimeException('测试表不支持回滚：' . $fullName);
}
$operatorUid = (int)Db::name('sys_user')->where('delete_time', 0)->order('uid asc')->value('uid');
if ($operatorUid <= 0) throw new RuntimeException('本地需要一个有效管理员验证标准销售员工校验；不会改动该管理员');
foreach (['ErpDomainEvent', 'HsxPerformanceFactRecorded', 'HsxErpMallInventory', 'ErpExternalSaleRecordedRequested', 'ErpExternalSaleRefundedRequested', 'ErpSaleCreatedRequested', 'HsxErpSaleChannelOptions', 'HsxErpBusinessSourceOptions'] as $name) Event::remove($name);
Event::listen('HsxErpMallInventory', ErpMallInventoryProvider::class);
Event::listen('ErpExternalSaleRecordedRequested', ErpExternalSaleRecordedRequested::class);
Event::listen('ErpExternalSaleRefundedRequested', \addon\hsx_erp\app\listener\ErpExternalSaleRefundedRequested::class);
Event::listen('ErpSaleCreatedRequested', static fn($event) => \addon\hsx_erp\app\listener\ErpSaleCreatedRequested::forSite($site, $operatorUid, '测试')->handle($event));
Event::listen('HsxErpSaleChannelOptions', \addon\phone_shop\app\listener\erp\ErpSaleChannelOptionsListener::class);
Event::listen('HsxErpBusinessSourceOptions', \addon\phone_shop\app\listener\erp\ErpBusinessSourceOptionsListener::class);
request()->siteId($site); request()->uid(1); request()->username('商城对账隔离测试'); request()->appType('adminapi');
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void { $count++; if (!$ok) throw new RuntimeException($message); };
$tag = 'MALL' . bin2hex(random_bytes(4));
$add = static fn(string $table, array $data): int => (int)Db::name($table)->insertGetId(['site_id' => $site] + $data);
$query = static fn(string $table) => Db::name($table)->where('site_id', $site);
$get = static fn(string $table, int $id): array => $query($table)->where('id', $id)->find() ?? [];
$now = time();
Db::startTrans();
try {
    $warehouse = $add('erp_warehouse', ['warehouse_name' => $tag, 'status' => 1, 'allow_direct_sale' => 1]);
    $location = $add('erp_warehouse_location', ['warehouse_id' => $warehouse, 'location_name' => '测试位', 'status' => 1]);
    $makeSku = static function (string $imei, float $cost = 6000, array $extraGoods = [], array $extraSku = []) use ($add, $query): array {
        $goodsId = $add('phone_shop_goods', $extraGoods + ['goods_name' => '隔离测试手机', 'goods_type' => 'real', 'source' => '1', 'is_proxy' => 0, 'sale_status' => 'available', 'condition_grade' => '', 'stock' => 1, 'status' => 1, 'goods_image' => 'upload/test-a.jpg,upload/test-b.jpg']);
        $skuId = $add('phone_shop_goods_sku', $extraSku + ['goods_id' => $goodsId, 'sku_no' => $imei, 'stock' => 1, 'cost_price' => $cost, 'price' => 7680, 'is_unique' => 1]);
        return ['sku_id' => $skuId, 'goods_id' => $goodsId, 'sku_no' => $imei, 'cost_price' => $cost, 'erp_asset_id' => (int)($extraSku['erp_asset_id'] ?? 0), 'is_unique' => 1];
    };
    $makeAsset = static function (string $imei, array $extra = []) use ($add, $tag, $warehouse, $location, $now): int {
        return $add('erp_asset', $extra + ['asset_no' => $tag . bin2hex(random_bytes(3)), 'imei' => $imei, 'model' => 'ERP原设备', 'warehouse_id' => $warehouse, 'location_id' => $location, 'warehouse_name' => '原仓库', 'location_name' => '原库位', 'ownership_type' => 'owned', 'status' => 'in_stock', 'refurbish_status' => 'none', 'total_cost' => 6100, 'purchase_cost' => 6000, 'source_plugin' => 'hsx_recycle', 'stock_in_at' => $now - 3600]);
    };
    $makeOrder = static function (array $sku, bool $snapshot = true, int $assetId = 0, float $cost = 6000, string $mode = 'offline_credit') use ($add, $tag, $now): array {
        $orderId = $add('phone_shop_order', ['order_no' => $tag . bin2hex(random_bytes(3)), 'order_from' => 'weapp', 'payment_mode' => $mode, 'taker_name' => '隔离客户', 'pay_time' => $now - 60, 'order_money' => 7680, 'goods_money' => 7680, 'merchant_net_amount' => $mode === 'online' ? 7633.92 : 7680, 'payment_fee_amount' => $mode === 'online' ? 46.08 : 0]);
        $extend = $snapshot ? ErpDeviceSnapshot::extend([], array_replace($sku, ['erp_asset_id' => $assetId]), ['source' => '1', 'is_proxy' => 0]) : [];
        $lineId = $add('phone_shop_order_goods', ['order_id' => $orderId, 'goods_id' => $sku['goods_id'], 'sku_id' => $sku['sku_id'], 'goods_name' => '订单测试设备', 'num' => 1, 'price' => 7680, 'goods_money' => 7680, 'order_goods_money' => 7680, 'inventory_source' => $assetId > 0 ? 'erp_asset' : 'self_owned', 'cost_price_snapshot' => $cost, 'total_cost_snapshot' => $cost, 'extend' => json_encode($extend)]);
        return ['order_id' => $orderId, 'line_id' => $lineId];
    };
    $service = ErpMallInventoryService::forSite($site, 1, '测试');
    $finance = ErpFinanceService::forSite($site, 1, '测试');
    $stockRow = static function (array $sku) use ($service): array {
        $data = $service->preview(['keyword' => $sku['sku_no'], 'include_linked' => 1]);
        return $data['data'][0] ?? [];
    };
    $requestRow = static fn(array $row): array => ['sku_id' => $row['sku_id'] ?? 0, 'sale_order_id' => $row['sale_order_id'] ?? 0, 'sale_item_id' => $row['sale_item_id'] ?? 0, 'preview_token' => $row['preview_token'], 'action' => $row['state'] === 'opening' ? 'opening' : 'link'];
    $openingParams = ['warehouse_id' => $warehouse, 'location_id' => $location, 'opening_at' => $now - 7200];

    // 1. 复用回收原资产，绝不能重新入库、补造应付、改原付款。
    $sku = $makeSku('357465822199406');
    $assetId = $makeAsset($sku['sku_no']);
    $payableId = $add('erp_payable', ['payable_no' => $tag . 'AP', 'source_type' => 'purchase_asset', 'source_id' => $assetId, 'amount' => 6000, 'settled_amount' => 6000, 'status' => 'settled']);
    $payableBefore = $get('erp_payable', $payableId);
    $row = $stockRow($sku);
    $assert($row['state'] === 'match' && (float)$row['erp_cost'] === 6100.0, '预览应命中原设备，包含整备成本');
    $assert((int)$query('phone_shop_goods_sku')->where('sku_id', $sku['sku_id'])->value('erp_asset_id') === 0, '预览不能写入');
    $result = $service->confirm(['items' => [$requestRow($row)]]);
    $assert($result['failed'] === 0, '关联失败：' . json_encode($result));
    $assert((int)$query('phone_shop_goods_sku')->where('sku_id', $sku['sku_id'])->value('erp_asset_id') === $assetId, '商城SKU应保存ERP关联');
    $assert($query('erp_asset')->count() === 1 && $query('erp_asset_ledger')->count() === 0, '关联原库存不能重复资产或入库');
    $assert($get('erp_payable', $payableId) === $payableBefore, '原采购应付及付款保持不变');
    $assert((float)$query('phone_shop_goods_sku')->where('sku_id', $sku['sku_id'])->value('price') === 7680.0, '商城售价不应改变');
    $assert($service->confirm(['items' => [$requestRow($row)]])['results'][0]['state'] === 'duplicate', '重复关联应幂等');

    // 2. 商城独有设备明确期初，必须核对库位、日期及真实成本。
    $openingSku = $makeSku('357465822199407', 5900);
    $row = $stockRow($openingSku);
    $assert($row['state'] === 'opening', '商城独有设备需期初确认');
    $bad = $service->confirm(['items' => [$requestRow($row)], 'warehouse_id' => $warehouse, 'location_id' => 0, 'opening_at' => $now - 7200]);
    $assert($bad['failed'] === 1 && $query('erp_asset')->count() === 1, '库位缺失不能半成品入库');
    $good = $service->confirm($openingParams + ['items' => [$requestRow($row)]]);
    $assert($good['failed'] === 0, '期初失败：' . json_encode($good));
    $opened = $get('erp_asset', $good['results'][0]['asset_id']);
    $assert($opened['status'] === 'in_stock' && (float)$opened['total_cost'] === 5900.0, '期初库存成本准确');
    $assert(json_decode($opened['image_urls'], true) === ['upload/test-a.jpg', 'upload/test-b.jpg'], '期初保留商城图片');
    $assert($query('erp_payable')->count() === 1 && $query('erp_asset_ledger')->where('action', 'inbound')->count() === 1, '期初只有入库，无新增采购应付');
    $assert($service->confirm($openingParams + ['items' => [$requestRow($row)]])['results'][0]['state'] === 'duplicate', '期初重复确认不重复建资产');

    // 3. 过期预览、重复串号、代理货等必须给出阻断，不能模糊配对。
    $staleSku = $makeSku('357465822199408');
    $stale = $stockRow($staleSku);
    $query('phone_shop_goods_sku')->where('sku_id', $staleSku['sku_id'])->update(['cost_price' => 6100]);
    $bad = $service->confirm($openingParams + ['items' => [$requestRow($stale)]]);
    $assert($bad['failed'] === 1 && str_contains($bad['results'][0]['message'], '重新预览'), '过期成本必须重新确认');
    $duplicateSku = $makeSku('357465822199409');
    $makeAsset($duplicateSku['sku_no']); $makeAsset($duplicateSku['sku_no']);
    $assert($stockRow($duplicateSku)['state'] === 'conflict', '重复串号不得自动选择');
    $proxySku = $makeSku('357465822199410', 6000, ['is_proxy' => 1, 'source' => '100005']);
    $assert(($service->preview(['keyword' => $proxySku['sku_no']])['total'] ?? 0) === 0, '代理货不混入自营期初');
    $assert($service->confirm(['items' => ['bad-request']])['failed'] === 1, '非法输入清晰失败');

    // 4. 已生成、已收部分款的原生应收只补设备，原账逐字段不变。
    $oldSku = $makeSku('357465822199411', 0, [], ['stock' => 0]);
    $oldAsset = $makeAsset($oldSku['sku_no']);
    $order = $makeOrder($oldSku, false, 0, 0);
    $saleId = $add('erp_sale_order', ['sale_no' => $tag . 'OLD', 'origin_plugin' => 'phone_shop', 'origin_type' => 'phone_shop.native_goods_sale', 'origin_id' => (string)$order['order_id'], 'origin_no' => $tag . 'ORDER', 'status' => 'completed', 'total_amount' => 7680, 'total_cost' => 0, 'profit' => 7680, 'received_amount' => 1000, 'receivable_amount' => 6680, 'sale_at' => $now - 60]);
    $saleLineId = $add('erp_sale_item', ['sale_order_id' => $saleId, 'asset_id' => 0, 'external_goods_id' => $oldSku['goods_id'], 'external_sku_id' => $oldSku['sku_id'], 'external_line_id' => (string)$order['line_id'], 'model' => '测试旧成交', 'quantity' => 1, 'sale_price' => 7680, 'status' => 'sold', 'remark' => '库存待核对：缺少信息；原始备注']);
    $arId = $add('erp_receivable', ['receivable_no' => $tag . 'AR', 'source_type' => 'phone_shop.native_goods_sale', 'source_id' => $saleId, 'origin_plugin' => 'phone_shop', 'source_no' => $tag . 'ORDER', 'amount' => 7680, 'settled_amount' => 1000, 'status' => 'partial']);
    $arBefore = $get('erp_receivable', $arId);
    $moneyBefore = $query('erp_money_ledger')->count();
    $saleBefore = $get('erp_sale_order', $saleId);
    $row = $service->preview(['receivable_id' => $arId])['data'][0];
    $assert($row['state'] === 'match' && str_contains($row['identity_source'], '旧订单'), '旧应收可利用当前商城串号核对');
    $query('phone_shop_goods_sku')->where('sku_id', $oldSku['sku_id'])->update(['erp_asset_id' => $oldAsset]);
    $row = $service->preview(['receivable_id' => $arId])['data'][0];
    $assert($row['state'] === 'match' && str_contains($row['message'], '仅补齐'), 'SKU已绑定但成交未关联，必须仍可补出库');
    $beforeInfo = $finance->receivableInfo($arId);
    $assert($beforeInfo['can_reconcile_mall_inventory'] === true, '应收详情给出核对入口');
    $result = $service->confirm(['receivable_id' => $arId, 'items' => [$requestRow($row)]]);
    $assert($result['failed'] === 0, '补齐旧应收失败：' . json_encode($result));
    $assert($get('erp_receivable', $arId) === $arBefore, '补设备绝不能更改应收或已收金额');
    $assert($query('erp_money_ledger')->count() === $moneyBefore && $get('erp_payable', $payableId) === $payableBefore, '补出库不重放收款与采购付款');
    $line = $get('erp_sale_item', $saleLineId);
    $assert((int)$line['asset_id'] === $oldAsset && $line['imei'] === $oldSku['sku_no'] && (float)$line['cost'] === 6100.0 && (float)$line['profit'] === 1580.0, '旧成交身份与成本利润补齐');
    $assert($get('erp_asset', $oldAsset)['status'] === 'sold', '原ERP库存正确出库');
    $assert(!str_contains($line['remark'], '库存待核对') && str_contains($line['remark'], '原始备注'), '清除过期警告并保留人工备注');
    $afterSale = $get('erp_sale_order', $saleId);
    foreach (['total_amount', 'received_amount', 'receivable_amount', 'finance_status'] as $key) $assert($afterSale[$key] === $saleBefore[$key], '销售收款字段不变：' . $key);
    $afterInfo = $finance->receivableInfo($arId);
    $assert($afterInfo['detail_status'] === 'complete' && $afterInfo['can_reconcile_mall_inventory'] === false, '核对完成后不再显示待补全');
    $assert($service->confirm(['receivable_id' => $arId, 'items' => [$requestRow($row)]])['results'][0]['state'] === 'duplicate', '旧应收重试不重复出库');
    $assert($query('erp_asset_ledger')->where('asset_id', $oldAsset)->where('action', 'sold')->count() === 1, '一台只有一条补出库记录');
    $page = $finance->receivablePage(['imei' => $oldSku['sku_no']]);
    $assert($page['total'] === 1 && (int)$page['data'][0]['item_count'] === 1, '商城应收可串号搜索，台数不再显示0');

    // 5. 新订单快照 -> 原生付款桥 -> ERP自动复用资产；补映射后重放不能转到标准桥重复记账。
    $futureSku = $makeSku('357465822199412');
    $futureAsset = $makeAsset($futureSku['sku_no']);
    $futureOrder = $makeOrder($futureSku);
    $native = new PhoneShopNativeOrderPaidToErp();
    $standard = new PhoneShopOrderPaidToErp();
    $event = ['site_id' => $site, 'order_id' => $futureOrder['order_id']];
    $result = $native->handle($event);
    $assert($result['status'] === 'processed', '自动销售失败：' . json_encode($result));
    $assert($get('erp_asset', $futureAsset)['status'] === 'sold', '新成交自动复用并出库：' . json_encode($result, JSON_UNESCAPED_UNICODE));
    $saleCount = $query('erp_sale_order')->count();
    $arCount = $query('erp_receivable')->count();
    $assert($standard->handle($event)['status'] === 'skipped', '事后关联不能重走标准桥');
    $again = $native->handle($event);
    $assert(in_array($again['status'], ['duplicate', 'processed'], true), '原生桥幂等回执：' . json_encode($again));
    $assert($query('erp_sale_order')->count() === $saleCount && $query('erp_receivable')->count() === $arCount, '付款重放不能重复销售或应收');
    $assert($query('erp_payable')->count() === 1, '自动关联也不新增应付');

    // 6. 缺少ERP资产但商城有IMEI：先保存真实销售，明确待对账，再确认期初及出库。
    $missingSku = $makeSku('357465822199413', 5900);
    $missingOrder = $makeOrder($missingSku, true, 0, 5900);
    $result = $native->handle(['site_id' => $site, 'order_id' => $missingOrder['order_id']]);
    $assert($result['status'] === 'processed', '缺库存不能抹掉真实应收：' . json_encode($result));
    $missingSaleId = (int)$query('erp_sale_order')->where('origin_id', (string)$missingOrder['order_id'])->value('id');
    $missingLine = $query('erp_sale_item')->where('sale_order_id', $missingSaleId)->find();
    $assert($missingLine['imei'] === $missingSku['sku_no'] && (int)$missingLine['asset_id'] === 0 && str_contains($missingLine['remark'], '库存待核对'), '保存IMEI与可解释的库存警告');
    $missingAR = $query('erp_receivable')->where('source_id', $missingSaleId)->find();
    $row = $service->preview(['receivable_id' => $missingAR['id']])['data'][0];
    $assert($row['state'] === 'opening', '已有成交缺库存可以确认期初');
    $result = $service->confirm($openingParams + ['receivable_id' => $missingAR['id'], 'items' => [$requestRow($row)]]);
    $assert($result['failed'] === 0, '成交期初失败：' . json_encode($result));
    $assert($get('erp_receivable', $missingAR['id']) === $missingAR, '成交期初保留原账');
    $asset = $get('erp_asset', $result['results'][0]['asset_id']);
    $assert($asset['status'] === 'sold' && (float)$asset['total_cost'] === 5900.0, '成交期初先入库再出库');
    $assert($query('erp_asset_ledger')->where('asset_id', $asset['id'])->count() === 2, '期初已售设备入出库成对');

    // 7. 线上已付款：清算收款和手续费只发生一次，库存补关联不再收一遍。
    $onlineSku = $makeSku('357465822199414');
    $onlineAsset = $makeAsset($onlineSku['sku_no']);
    $onlineOrder = $makeOrder($onlineSku, true, 0, 6000, 'online');
    $event = ['site_id' => $site, 'order_id' => $onlineOrder['order_id']];
    $result = $native->handle($event);
    $assert($result['status'] === 'processed', '线上自动入账失败：' . json_encode($result));
    $assert($get('erp_asset', $onlineAsset)['status'] === 'sold', '线上成交正确出库');
    $settlementCount = $query('erp_settlement')->count();
    $moneyRows = $query('erp_money_ledger')->select()->toArray();
    $assert(count($moneyRows) === 2, '线上收款及手续费各一条');
    $assert((float)$query('erp_capital_account')->sum('balance') === 7633.92, '清算净到账准确');
    $native->handle($event); $standard->handle($event);
    $assert($query('erp_settlement')->count() === $settlementCount && $query('erp_money_ledger')->select()->toArray() === $moneyRows, '重复通知不重复收款手续费');

    // 8. 全额线上退款仍定位原销售明细，恢复同一资产，不把事后映射当作另一笔订单。
    $refund = ['refund_data' => ['site_id' => $site, 'order_id' => $onlineOrder['order_id'], 'order_goods_id' => $onlineOrder['line_id'], 'money' => 7680, 'refund_order_goods_money' => 7680, 'refund_id' => 999999914, 'order_refund_no' => $tag . 'REF', 'transfer_time' => $now, 'reason' => '隔离退款测试']];
    $refundBridge = new \addon\phone_shop\app\listener\erp\PhoneShopNativeOrderRefundedToErp();
    $refundResult = $refundBridge->handle($refund);
    $assert($refundResult['status'] === 'processed', '退款闭环失败：' . json_encode($refundResult));
    $assert($get('erp_asset', $onlineAsset)['status'] === 'in_stock' && (float)$get('erp_asset', $onlineAsset)['total_cost'] === 6100.0, '全退恢复原设备及实际成本');
    $afterRefundMoney = $query('erp_money_ledger')->select()->toArray();
    $refundBridge->handle($refund);
    $assert($query('erp_money_ledger')->select()->toArray() === $afterRefundMoney, '退款重试不重复资金支出');

    // 9. 期初关联后创建的新订单走原标准ERP销售渠道，保持正常主流程。
    $newLinkedOrder = $makeOrder($openingSku, true, (int)$opened['id'], 5900);
    $query('phone_shop_order')->where('order_id', $newLinkedOrder['order_id'])->update(['pay_time' => 0]);
    $creditLine = \addon\phone_shop\app\model\order\OrderGoods::where('order_goods_id', $newLinkedOrder['line_id'])->find();
    $creditLine->save(['extend' => ErpDeviceSnapshot::withSaleTime($creditLine->extend, $now - 60)]);
    $savedExtend = json_decode((string)$query('phone_shop_order_goods')->where('order_goods_id', $newLinkedOrder['line_id'])->value('extend'), true);
    $assert($savedExtend['erp_sale_at'] === $now - 60 && $savedExtend['erp_device']['erp_asset_id'] === (int)$opened['id'], '真实订单扩展保存挂账日期且不丢串号映射');
    $event = ['site_id' => $site, 'order_id' => $newLinkedOrder['order_id']];
    $assert($native->handle($event)['status'] === 'skipped', '预先关联的订单不走原生销售桥');
    $linkedResult = $standard->handle($event);
    $assert($linkedResult['status'] === 'processed', '预关联标准销售失败：' . json_encode($linkedResult));
    $assert($get('erp_asset', $opened['id'])['status'] === 'sold', '预关联设备正常出库');
    $assert((int)$query('erp_sale_order')->where('origin_id', (string)$newLinkedOrder['order_id'])->value('sale_at') === $now - 60, '挂账销售使用原确认时间，不伪造支付时间');
    $saleCount = $query('erp_sale_order')->count();
    $standard->handle($event);
    $assert($query('erp_sale_order')->count() === $saleCount, '预关联标准销售幂等');

    // 10. 没有应收单的线上销售也可以从库存对账处理，保留原收款与手续费。
    $paidMissingSku = $makeSku('357465822199415');
    $paidMissingOrder = $makeOrder($paidMissingSku, true, 0, 6000, 'online');
    $result = $native->handle(['site_id' => $site, 'order_id' => $paidMissingOrder['order_id']]);
    $assert($result['status'] === 'processed' && !$result['erp']['receivable_created'], '线上现结不造待收款');
    $sold = $service->preview(['scope' => 'sold', 'keyword' => $paidMissingSku['sku_no']]);
    $assert($sold['total'] === 1 && $sold['data'][0]['state'] === 'opening', '无应收的线上成交必须可查可处理');
    $moneyBeforeOpening = $query('erp_money_ledger')->select()->toArray();
    $arCount = $query('erp_receivable')->count();
    $result = $service->confirm($openingParams + ['items' => [$requestRow($sold['data'][0])]]);
    $assert($result['failed'] === 0, '线上无应收建账失败：' . json_encode($result));
    $assert($query('erp_money_ledger')->select()->toArray() === $moneyBeforeOpening && $query('erp_receivable')->count() === $arCount, '线上补建账不修改资金或追加应收');
    $assert($service->preview(['scope' => 'sold', 'keyword' => $paidMissingSku['sku_no']])['total'] === 0, '已成交核对完成后退出待处理');

    // 11. ERP已暂建资产但商城拒绝绑定：整台事务回滚，不能留下孤儿库存。
    $failSku = $makeSku('357465822199416');
    $row = $stockRow($failSku);
    $assetCount = $query('erp_asset')->count();
    $ledgerCount = $query('erp_asset_ledger')->count();
    Event::remove('HsxErpMallInventory');
    Event::listen('HsxErpMallInventory', static fn($event) => $event['action'] === 'bind'
        ? ['provider' => 'phone_shop', 'data' => ['bound' => false]] : (new ErpMallInventoryProvider())->handle($event));
    $result = $service->confirm($openingParams + ['items' => [$requestRow($row)]]);
    $assert($result['failed'] === 1 && str_contains($result['results'][0]['message'], '回滚'), '绑定失败必须清楚告知');
    $assert($query('erp_asset')->count() === $assetCount && $query('erp_asset_ledger')->count() === $ledgerCount, '绑定失败不留库存、入库流水');
    Event::remove('HsxErpMallInventory'); Event::listen('HsxErpMallInventory', ErpMallInventoryProvider::class);

    // 12. 下单后SKU身份发生矛盾，不得用订单快照掩盖当前商品冲突。
    $changedSku = $makeSku('357465822199417', 6000, [], ['device_snapshot' => json_encode(['identity' => ['imei' => '357465822199417']])]);
    $changedAsset = $makeAsset($changedSku['sku_no']);
    $changedOrder = $makeOrder($changedSku);
    $query('phone_shop_goods_sku')->where('sku_id', $changedSku['sku_id'])->update(['sku_no' => '357465822199418']);
    $result = $native->handle(['site_id' => $site, 'order_id' => $changedOrder['order_id']]);
    $assert($result['status'] === 'processed' && !empty($result['erp']['inventory_warnings']), '身份变化保留真实应收并报告冲突');
    $assert($get('erp_asset', $changedAsset)['status'] === 'in_stock', '身份矛盾不能把原资产错误出库');
    // 主站历史 is_proxy=1 已废弃；本地 source 仍应可以预览、期初与幂等关联。
    foreach (['', '0', '1', (string)$site] as $index => $localSource) {
        $legacySku = $makeSku('357465822198' . str_pad((string)$index, 3, '0', STR_PAD_LEFT), 6100, ['is_proxy' => 1, 'source' => $localSource]);
        $row = $stockRow($legacySku);
        $assert($row['state'] === 'opening', '旧代理标记不能排除本站商品：' . $localSource);
        $result = $service->confirm($openingParams + ['items' => [$requestRow($row)]]);
        $assert($result['success'] === 1 && $result['failed'] === 0, '本站历史商品可以完成真实期初关联');
        $assert($service->confirm($openingParams + ['items' => [$requestRow($row)]])['results'][0]['state'] === 'duplicate', '历史标记商品重复操作仍应幂等');
    }
    $assert($query('erp_payable')->count() === 1, '全过程未凭空生成采购应付');
    $assert($query('erp_outbox_event')->where('status', '<>', 'pending')->count() === 0, '外层测试事务内不能发送外部消息');
} finally { Db::rollback(); }
foreach ($tables as $table) $assert($query($table)->count() === 0, '测试样本全部回滚：' . $table);
echo "PASS ERP/mall real database: {$count} assertions; fixtures rolled back; no external consumers.\n";
