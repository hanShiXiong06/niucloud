<?php
declare(strict_types=1);
// 仅本地隔离样本，实际订单/库存/ERP服务；屏蔽通知与队列，finally 回滚全部样本。
if (getenv('HSX_RETURN_ROLLBACK_TEST') !== '1') { fwrite(STDERR, "Set HSX_RETURN_ROLLBACK_TEST=1\n"); exit(2); }
require dirname(__DIR__) . '/niucloud/vendor/autoload.php';
use think\facade\Db;
use think\facade\Event;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use addon\phone_shop\app\service\core\order\CoreOrderInventoryService;
use addon\phone_shop\app\service\core\order\CoreOrderDeviceReturnService;
use addon\phone_shop\app\service\core\order\CoreOrderCloseService;
use addon\phone_shop\app\service\core\order\OrderDeviceView;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\service\admin\goods\GoodsService;
use addon\hsx_erp\app\service\admin\ErpSaleReturnService;
use addon\hsx_erp\app\listener\PhoneShopOrderReturnContext;

(new think\App())->initialize();
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"); exit(1); });
if (!in_array(config('database.connections.mysql.hostname'), ['localhost', '127.0.0.1'], true)) throw new RuntimeException('仅限本地数据库');
$site = 900000915;
$tables = ['phone_shop_goods', 'phone_shop_goods_sku', 'phone_shop_order', 'phone_shop_order_goods', 'phone_shop_order_refund', 'erp_asset', 'erp_asset_ledger', 'erp_sale_order', 'erp_sale_item', 'erp_sale_return', 'erp_sale_return_item', 'erp_receivable', 'erp_payable', 'erp_account_ledger', 'erp_money_ledger', 'erp_party', 'erp_warehouse', 'erp_warehouse_location', 'erp_operation_log', 'erp_outbox_event', 'erp_inbox_event', 'pay'];
$tables[] = 'phone_shop_order_offline_record';
$tables = array_merge($tables, ['erp_capital_account', 'erp_settlement', 'erp_settlement_link', 'erp_party_member', 'member', 'phone_shop_order_delivery', 'phone_shop_delivery_company']);
foreach ($tables as $table) {
    if (Db::name($table)->where('site_id', $site)->count()) throw new RuntimeException('隔离站点已被占用：' . $table);
    $meta = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [config('database.connections.mysql.prefix') . $table]);
    if (strtoupper($meta[0]['ENGINE'] ?? '') !== 'INNODB') throw new RuntimeException('表不支持回滚：' . $table);
}
request()->siteId($site); request()->uid(1); request()->username('收货测试员'); request()->appType('adminapi');
foreach (['ErpDomainEvent', 'PhoneShopOrderReturnContext', 'PhoneShopSaleReturnCancelled', 'PhoneShopGoodsSaleableChanged', 'HsxPerformanceFactRecorded', 'HsxErpMallInventory', 'ErpOfflineSaleReturnRequested', 'AfterPhoneShopOrderDelivery', 'PayClose'] as $event) Event::remove($event);
Event::listen('ErpOfflineSaleReturnRequested', \addon\hsx_erp\app\listener\ErpOfflineSaleReturnRequested::class);
Event::listen('PhoneShopOrderReturnContext', PhoneShopOrderReturnContext::class);
Event::listen('PhoneShopSaleReturnCancelled', \addon\phone_shop\app\listener\erp\ErpSaleReturnCancelled::class);
Event::listen('HsxErpMallInventory', \addon\phone_shop\app\listener\erp\ErpMallInventoryProvider::class);
// 只替换外部副作用出口；调用的订单创建/关闭/库存核心代码仍为实际实现。
class ReturnTestEvents {
    public static array $created = [];
    public static function orderCreate($d) {
        self::$created = $d;
        (new \addon\phone_shop\app\listener\order\ShopOrderCreate())->handle($d);
    }
    public static function orderCreateAfter($d) {}
    public static function orderClose($d) {}
    public static function orderCloseAfter($d) {}
    public static function orderDelivery($d) {}
    public static function orderFinish($d) {}
    public static function orderFinishAfter($d) {}
}
class_alias(ReturnTestEvents::class, 'addon\phone_shop\app\service\core\order\CoreOrderEventService');
// 用户订单接口会查询微信发货能力；本回归只验证订单数据，禁止请求外部微信服务。
class ReturnTestWeappDelivery { public function getIsTradeManaged() { return false; } }
class_alias(ReturnTestWeappDelivery::class, 'app\service\api\weapp\WeappDeliveryService');
class ReturnTestCheckout {
    use \addon\phone_shop\app\service\core\order\CoreOrderCreateTrait;
    public function invoice() {}
    public function useDiscount() {}
    public function addFormData($id) {}
    public function useDiscountActive() {}
    public function delOrderCache($key) {}
}
$assertions = 0;
$assert = static function(bool $ok, string $text) use (&$assertions) { $assertions++; if (!$ok) throw new RuntimeException($text); };
$throws = static function(callable $fn, string $needle) use ($assert) { try { $fn(); } catch(Throwable $e) { $assert(str_contains($e->getMessage(), $needle), '错误提示不符：' . $e->getMessage()); return; } throw new RuntimeException('本应阻断：' . $needle); };
$add = static fn($table, $data) => (int)Db::name($table)->insertGetId(['site_id' => $site] + $data);
$q = static fn($table) => Db::name($table)->where('site_id', $site);
$now = time(); $tag = 'RET' . bin2hex(random_bytes(4)); $serial = 500;
$makeSku = static function() use ($add, &$serial): array {
    $imei = '357465822199' . (++$serial);
    $goods = $add('phone_shop_goods', ['goods_name' => '退回测试手机', 'goods_type' => 'real', 'stock' => 1, 'status' => 1, 'sale_status' => 'available', 'is_online_sellable' => 1, 'source' => '1']);
    $sku = $add('phone_shop_goods_sku', ['goods_id' => $goods, 'sku_no' => $imei, 'stock' => 1, 'is_unique' => 1, 'price' => 5000, 'cost_price' => 4500]);
    return ['goods_id' => $goods, 'sku_id' => $sku, 'sku_no' => $imei, 'is_unique' => 1];
};
$checkout = static function(array $sku, string $mode = 'offline_pending') use ($site): int {
    $service = new ReturnTestCheckout(); $service->site_id = $site; $service->param = ['order_from' => 'weapp'];
    $result = $service->createOrder(['order_data' => ['site_id' => $site, 'member_id' => 0, 'order_type' => OrderDict::TYPE, 'payment_mode' => $mode, 'status' => OrderDict::WAIT_PAY, 'goods_money' => 5000, 'order_money' => 5000],
        'order_goods_data' => [['site_id' => $site, 'member_id' => 0, 'order_id' => &$service->order_id, 'goods_id' => $sku['goods_id'], 'sku_id' => $sku['sku_id'], 'num' => 1, 'price' => 5000, 'goods_money' => 5000, 'status' => 1, 'goods_type' => 'real', 'extend' => ErpDeviceSnapshot::extend([], $sku, ['source' => '1'])]]]);
    return (int)$result['order_id'];
};
Db::startTrans();
try {
    $sku = $makeSku(); $orderId = $checkout($sku);
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $sku['sku_id'])->value('stock') === 0, '订单事务内必须占库');
    $assert($q('phone_shop_goods')->where('goods_id', $sku['goods_id'])->value('sale_status') === 'locked', '拍下后退出可售');
    $assert(CoreOrderInventoryService::isManaged(ReturnTestEvents::$created['order_goods_data'][0]), '异步事件带占库标记，不能二次扣库');
    $count = $q('phone_shop_order')->count();
    $throws(fn() => $checkout($sku), '占用');
    $assert($q('phone_shop_order')->count() === $count, '第二次下单失败应回滚订单');
    $throws(fn() => (new GoodsService())->editSingleStatus(['goods_id' => $sku['goods_id'], 'status' => 1]), '未执行上架');
    (new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $orderId, 'close_type' => OrderDict::SHOP_CLOSE, 'main_type' => 'user', 'main_id' => 1]);
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $sku['sku_id'])->value('stock') === 1, '未付款取消立即释放一台');
    $throws(fn() => (new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $orderId, 'close_type' => OrderDict::SHOP_CLOSE]), 'SHOP_ORDER_IS_CLOSED');
    $newOrder = $checkout($sku);
    (new CoreOrderInventoryService())->releaseCancelled($site, $orderId, false);
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $sku['sku_id'])->value('stock') === 0, '旧取消重试不能释放新订单');
    $throws(fn() => (new \addon\phone_shop\app\service\core\order\CoreOrderPayService())->pay(['site_id' => $site, 'trade_id' => $orderId]), '原订单已关闭');

    // 创建事务写入真实时限；到期关闭后释放库存，暂停/付款/延时都必须受到保护。
    $configService = new \addon\phone_shop\app\service\core\order\CoreOrderConfigService();
    $assert($configService->orderClose($site) === ['is_close' => '1', 'close_length' => 120], '未配置时保留原自动关闭默认规则');
    $assert($configService->getConfig($site)['close_order_info']['is_close'] === '1', '配置接口返回与创建订单一致的关闭规则');
    $configured = new class extends \addon\phone_shop\app\service\core\order\CoreOrderConfigService {
        public bool $enabled = true;
        public function orderClose(int $site_id) { return ['is_close' => $this->enabled ? '1' : '2', 'close_length' => 20]; }
    };
    $assert($configured->pendingPaymentTimeout($site, 'online', $now) === $now + 1200, '按本站配置的20分钟计算时限');
    $configured->enabled = false;
    $assert($configured->pendingPaymentTimeout($site, 'online', $now) === 0, '关闭开关后新线上订单不自动关单');
    $assert($configured->pendingPaymentTimeout($site, 'offline_pending', $now) === $now + 1200, '线下待处理保留独立的20分钟时限');
    $assert($configured->pendingPaymentTimeout($site, 'offline_cash', $now) === 0 && $configured->pendingPaymentTimeout($site, 'offline_credit', $now) === 0, '现结和挂账订单不设置自动关闭时间');
    request()->memberId(0);
    $customerOrders = new \addon\phone_shop\app\service\api\order\OrderService();
    foreach (['online', ''] as $mode) {
        $heldSku = $makeSku(); $heldOrder = $checkout($heldSku, $mode);
        $timeout = (int)ReturnTestEvents::$created['time'] + 7200;
        $assert((int)$q('phone_shop_order')->where('order_id', $heldOrder)->value('timeout') === $timeout, '后置队列未运行也已写入线上订单到期时间');
        $assert($customerOrders->getDetail($heldOrder)['expire_time'] === $timeout, '订单详情返回真实到期时间');
        $customerPage = $customerOrders->getPage(['order_no' => $q('phone_shop_order')->where('order_id', $heldOrder)->value('order_no'), 'status' => '', 'activity_type' => '', 'body' => '']);
        $assert(count($customerPage['data']) === 1 && $customerPage['data'][0]['expire_time'] === $timeout, '列表与详情采用相同的真实到期时间');
        $beforeHold = $q('phone_shop_order_goods')->where('order_id', $heldOrder)->value('extend');
        $autoClose = static fn() => (new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $heldOrder, 'close_type' => OrderDict::AUTO_CLOSE]);
        $assert($autoClose() === false, '未到期/管理员延时后，过期扫描快照不能关单');
        $q('phone_shop_order')->where('order_id', $heldOrder)->update(['timeout' => 0]);
        $assert($autoClose() === false && $customerOrders->getDetail($heldOrder)['expire_time'] === 0, '暂停锁单后不自动关闭也不显示虚假倒计时');
        $assert($q('phone_shop_order_goods')->where('order_id', $heldOrder)->value('extend') === $beforeHold, '跳过关闭不改占库记录');
        $q('phone_shop_order')->where('order_id', $heldOrder)->update(['timeout' => time() - 1]);
        $assert($autoClose() === true, '到期的线上未付款订单自动关闭');
        $assert((int)$q('phone_shop_order')->where('order_id', $heldOrder)->value('status') === OrderDict::CLOSE, '自动关闭实际更新订单状态');
        $stock = $q('phone_shop_goods')->where('goods_id', $heldSku['goods_id'])->find();
        $assert((int)$stock['stock'] === 1 && (int)$stock['status'] === 1 && $stock['sale_status'] === 'available', '关单后立即恢复一台库存及可售状态');
        $assert(\addon\phone_shop\app\dict\goods\GoodsDict::getSaleState($stock)['can_sell'] === 1, '关单后商品可再次购买');
        $assert($autoClose() === false, '重复自动关闭不重复加库存');
        $checkout($heldSku, 'online');
        $assert($autoClose() === false && (int)$q('phone_shop_goods_sku')->where('sku_id', $heldSku['sku_id'])->value('stock') === 0, '旧任务重试不能释放新订单已占用的库存');
    }
    $offlineSku = $makeSku(); $offlineOrder = $checkout($offlineSku);
    $offlineTimeout = (int)ReturnTestEvents::$created['time'] + 1200;
    $assert((int)$q('phone_shop_order')->where('order_id', $offlineOrder)->value('timeout') === $offlineTimeout, '线下时限也在创建事务内确定，不依赖后置队列');
    $assert($customerOrders->getDetail($offlineOrder)['expire_time'] === $offlineTimeout, '线下待处理仍返回真实处理时限');
    $assert((new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $offlineOrder, 'close_type' => OrderDict::AUTO_CLOSE]) === false, '未到期线下待处理单不能提前关闭');
    $q('phone_shop_order')->where('order_id', $offlineOrder)->update(['timeout' => time() - 1]);
    (new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $offlineOrder, 'close_type' => OrderDict::AUTO_CLOSE]);
    $assert((int)$q('phone_shop_order')->where('order_id', $offlineOrder)->value('status') === OrderDict::CLOSE, '不改变线下待处理超时规则');

    foreach ([['status' => OrderDict::WAIT_DELIVERY], ['pay_money' => 5000], ['is_credit' => 1], ['payment_mode' => 'offline_cash'], ['payment_mode' => 'offline_credit'], ['relate_source' => 'hsx_erp']] as $protected) {
        $guardSku = $makeSku(); $guardOrder = $checkout($guardSku, 'online');
        $q('phone_shop_order')->where('order_id', $guardOrder)->update($protected + ['timeout' => time() - 1]);
        $assert((new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $guardOrder, 'close_type' => OrderDict::AUTO_CLOSE]) === false, '付款/挂账/ERP接管后，旧扫描不能关闭订单');
        $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $guardSku['sku_id'])->value('stock') === 0, '受保护订单库存不释放');
    }
    foreach ([\app\dict\pay\PayDict::STATUS_WAIT, \app\dict\pay\PayDict::STATUS_FINISH] as $payStatus) {
        $paySku = $makeSku(); $payOrder = $checkout($paySku, 'online');
        $q('phone_shop_order')->where('order_id', $payOrder)->update(['timeout' => time() - 1]);
        $tradeNo = $tag . $payOrder;
        $add('pay', ['out_trade_no' => $tradeNo, 'trade_type' => OrderDict::TYPE, 'trade_id' => $payOrder, 'money' => 5000, 'status' => $payStatus]);
        $closePayOrder = static fn() => (new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $payOrder, 'close_type' => OrderDict::AUTO_CLOSE]);
        if ($payStatus === \app\dict\pay\PayDict::STATUS_FINISH) {
            $throws($closePayOrder, '支付状态尚未确认');
            $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $paySku['sku_id'])->value('stock') === 0, '支付单已收款而业务单尚未回写时，禁止释放库存');
        } else {
            $assert($closePayOrder() === true, '未付款支付单随业务单关闭');
            $assert((string)$q('pay')->where('out_trade_no', $tradeNo)->value('status') === \app\dict\pay\PayDict::STATUS_CANCEL, '真实支付记录已取消，不只是关闭页面');
        }
    }

    $warehouse = $add('erp_warehouse', ['warehouse_name' => $tag, 'status' => 1, 'allow_direct_sale' => 1]);
    $location = $add('erp_warehouse_location', ['warehouse_id' => $warehouse, 'location_name' => '原库位', 'status' => 1]);
    $party = $add('erp_party', ['party_name' => '退货测试客户', 'status' => 1]);
    $makeSale = static function(float $paid, int $num = 1, string $mode = 'offline_credit') use ($makeSku, $add, $q, $checkout, $tag, $now, $warehouse, $location, $party): array {
        $skus = []; $lineIds = [];
        $sku = $makeSku(); $order = $checkout($sku); $skus[] = $sku;
        $lineIds[] = (int)$q('phone_shop_order_goods')->where('order_id', $order)->value('order_goods_id');
        for ($i = 1; $i < $num; $i++) {
            $other = $makeSku(); $skus[] = $other;
            $lineIds[] = $add('phone_shop_order_goods', ['order_id' => $order, 'goods_id' => $other['goods_id'], 'sku_id' => $other['sku_id'], 'num' => 1, 'price' => 5000, 'goods_money' => 5000, 'order_goods_money' => 5000, 'status' => 1, 'extend' => json_encode(ErpDeviceSnapshot::extend([], $other, ['source' => '1']))]);
            (new CoreOrderInventoryService())->reserve((int)request()->siteId(), $order);
        }
        $q('phone_shop_order')->where('order_id', $order)->update(['payment_mode' => $mode, 'is_credit' => $mode === 'offline_credit' ? 1 : 0, 'status' => 2, 'pay_money' => $paid, 'pay_time' => $paid > 0 ? $now : 0, 'order_money' => 5000 * $num, 'goods_money' => 5000 * $num]);
        $sale = $add('erp_sale_order', ['sale_no' => $tag . bin2hex(random_bytes(3)), 'origin_plugin' => 'phone_shop', 'origin_type' => 'phone_shop.native_goods_sale', 'origin_id' => (string)$order, 'party_id' => $party, 'party_name' => '退货测试客户', 'payment_mode' => $mode, 'status' => 'completed', 'total_amount' => 5000 * $num, 'received_amount' => $paid, 'receivable_amount' => 5000 * $num - $paid, 'salesman_name' => '原业务员', 'operator_name' => '原开单人']);
        $ar = $add('erp_receivable', ['receivable_no' => $tag . bin2hex(random_bytes(3)), 'source_type' => 'phone_shop.native_goods_sale', 'source_id' => $sale, 'origin_plugin' => 'phone_shop', 'party_id' => $party, 'party_name' => '退货测试客户', 'amount' => 5000 * $num, 'settled_amount' => $paid, 'status' => $paid === 0.0 ? 'pending' : ($paid === 5000.0 * $num ? 'settled' : 'partial')]);
        $assets = [];
        foreach ($skus as $i => $s) {
            $asset = $add('erp_asset', ['asset_no' => $tag . bin2hex(random_bytes(3)), 'imei' => $s['sku_no'], 'model' => '测试设备', 'status' => 'sold', 'ownership_type' => 'owned', 'sale_target' => 'mall', 'total_cost' => 4500, 'warehouse_id' => $warehouse, 'location_id' => $location, 'warehouse_name' => '原仓库', 'location_name' => '原库位', 'sale_order_id' => $sale]);
            $saleItem = $add('erp_sale_item', ['sale_order_id' => $sale, 'asset_id' => $asset, 'imei' => $s['sku_no'], 'external_line_id' => (string)$lineIds[$i], 'sale_price' => 5000, 'cost' => 4500, 'profit' => 500, 'quantity' => 1, 'status' => 'sold', 'warehouse_id' => $warehouse, 'location_id' => $location]);
            $q('erp_asset')->where('id', $asset)->update(['sale_item_id' => $saleItem]);
            $q('phone_shop_goods_sku')->where('sku_id', $s['sku_id'])->update(['erp_asset_id' => $asset]);
            $q('phone_shop_goods')->where('goods_id', $s['goods_id'])->update(['status' => 0, 'sale_status' => 'sold']);
            $assets[] = $asset;
        }
        return compact('order', 'sale', 'ar', 'assets', 'lineIds', 'skus');
    };
    $erpReturn = new ErpSaleReturnService(); $mallReturn = new CoreOrderDeviceReturnService();
    $returnOne = static function(array $sale, int $index = 0) use ($erpReturn, $mallReturn, $q, $warehouse, $location, $site): array {
        $ids = $erpReturn->createAndConfirm(['sale_order_id' => $sale['sale'], 'refund_mode' => 'payable', 'return_to_warehouse_id' => $warehouse, 'return_to_location_id' => $location, 'items' => [['asset_id' => $sale['assets'][$index], 'return_price' => 5000]], 'remark' => '设备已实际收到']);
        $event = $q('erp_outbox_event')->where('event_name', 'erp.asset.returned.v1')->order('id desc')->find();
        $payload = json_decode($event['payload_json'], true)['payload'];
        $result = $mallReturn->fromErp($site, $sale['assets'][$index], $payload['outbound_no'], $payload);
        return [$ids[0], $payload, $result];
    };
    $unpaid = $makeSale(0);
    $before = $q('phone_shop_order')->where('order_id', $unpaid['order'])->find();
    $throws(fn() => (new CoreOrderCloseService())->close(['site_id' => $site, 'order_id' => $unpaid['order'], 'close_type' => OrderDict::SHOP_CLOSE]), '已收款或已挂账');
    [$returnId, $payload] = $returnOne($unpaid);
    $after = $q('phone_shop_order')->where('order_id', $unpaid['order'])->find();
    $assert((int)$after['status'] === -1 && $after['order_money'] === $before['order_money'] && $after['pay_money'] === $before['pay_money'], '全退关闭原单但保留成交和付款金额');
    $assert($q('phone_shop_order_goods')->where('order_id', $unpaid['order'])->count() === 1, '不得删除原明细');
    $assert((float)$q('erp_receivable')->where('id', $unpaid['ar'])->value('amount') === 0.0, '未收款冲减原应收');
    $assert($q('erp_payable')->where('source_type', 'sale_return')->where('source_id', $returnId)->count() === 0, '未收款退回不能新增待退款');
    $stock = $q('phone_shop_goods')->where('goods_id', $unpaid['skus'][0]['goods_id'])->find();
    $assert((int)$stock['stock'] === 1 && (int)$stock['status'] === 1 && $stock['sale_status'] === 'available', '实际退回同时恢复一台库存、上架和可售');
    $assert(\addon\phone_shop\app\dict\goods\GoodsDict::getSaleState($stock)['can_sell'] === 1, '退回后统一销售状态必须为可售');
    $soldMethod = new ReflectionMethod(\addon\phone_shop\app\listener\order\ErpAssetSoldListener::class, 'onSold');
    $soldMethod->setAccessible(true);
    $staleSold = $soldMethod->invoke(new \addon\phone_shop\app\listener\order\ErpAssetSoldListener(), ['site_id' => $site, 'payload' => ['asset_id' => $unpaid['assets'][0], 'sale_order_id' => $unpaid['sale'], 'build_mall_order' => false]]);
    $assert($staleSold['stale'] && (int)$q('phone_shop_goods_sku')->where('sku_id', $unpaid['skus'][0]['sku_id'])->value('stock') === 1, '迟到的旧出库通知不能覆盖已退回的库存');
    $moneyCount = $q('erp_money_ledger')->count(); $arCount = $q('erp_receivable')->count();
    // 不再手动上架，直接验证退回后的真实下单占库链路。
    $resale = $checkout($unpaid['skus'][0]);
    $assert($mallReturn->fromErp($site, $unpaid['assets'][0], $payload['outbound_no'], $payload)['duplicate'], '旧退回通知重试应幂等');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $unpaid['skus'][0]['sku_id'])->value('stock') === 0, '旧通知不能释放新拍下订单');
    $assert($q('erp_money_ledger')->count() === $moneyCount && $q('erp_receivable')->count() === $arCount, '重新上架和拍下未付款不生成新的收款账');
    $throws(fn() => $erpReturn->cancel($returnId, '客户改变主意'), '已被商城新订单占用');
    $assert((float)$q('erp_receivable')->where('id', $unpaid['ar'])->value('amount') === 0.0 && $q('erp_asset')->where('id', $unpaid['assets'][0])->value('status') === 'in_stock', '撤销退货遇到新订单必须整体回滚ERP账务');

    $paid = $makeSale(5000, 1, 'offline_cash'); [$paidReturn] = $returnOne($paid);
    $ap = $q('erp_payable')->where('source_type', 'sale_return')->where('source_id', $paidReturn)->find();
    $assert((float)$ap['amount'] === 5000.0 && (float)$ap['settled_amount'] === 0.0 && $ap['status'] === 'pending', '已收款退回生成原客户退款应付，不冒充已退');
    $assert((float)$q('phone_shop_order')->where('order_id', $paid['order'])->value('pay_money') === 5000.0, '原收款记录不能归零');
    $assert($q('erp_money_ledger')->count() === $moneyCount, '转财务退款未发生实际出款');
    $erpReturn->cancel($paidReturn, '客户撤销退货，恢复原单');
    $assert((int)$q('phone_shop_order')->where('order_id', $paid['order'])->value('status') === 2, '撤销退货恢复原商城订单状态');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $paid['skus'][0]['sku_id'])->value('stock') === 0, '撤销退货同时下架，不能仍然可售');
    $assert($q('erp_payable')->where('id', $ap['id'])->value('status') === 'void', '未付款的退款应付撤销，不重建销售收款');
    $assert((float)$q('phone_shop_order')->where('order_id', $paid['order'])->value('pay_money') === 5000.0, '撤销退货仍保留原已付款金额');
    $mixed = $makeSale(1000); [$mixedReturn] = $returnOne($mixed);
    $assert((float)$q('erp_payable')->where('source_type', 'sale_return')->where('source_id', $mixedReturn)->value('amount') === 1000.0, '部分收款只退已收1000');
    $assert((float)$q('erp_receivable')->where('id', $mixed['ar'])->value('amount') === 1000.0, '部分收款冲掉未收4000，保留历史已收');
    $multi = $makeSale(0, 2); $returnOne($multi, 0);
    $assert((int)$q('phone_shop_order')->where('order_id', $multi['order'])->value('status') === 2, '退一台不能关闭另外一台');
    $returnOne($multi, 1);
    $assert((int)$q('phone_shop_order')->where('order_id', $multi['order'])->value('status') === -1 && $q('phone_shop_order_goods')->where('order_id', $multi['order'])->count() === 2, '全退关闭但明细全保留');

    $online = $makeSale(5000, 1, 'online');
    $guard = static fn($asset, $sourceOrder = 0, $reserve = false) => event('HsxErpMallInventory', ['action' => 'sale_guard', 'site_id' => $site, 'asset_id' => $asset, 'source_order_id' => $sourceOrder, 'reserve' => $reserve]);
    $throws(fn() => $guard($online['assets'][0]), '原订单占用');
    $assert($guard($online['assets'][0], $online['order'])[0]['data']['allowed'], '原订单ERP入账不是第二次销售，不能误拦截');
    $throws(fn() => $returnOne($online), '原渠道退款');
    $throws(fn() => $mallReturn->confirmReceived($site, $online['lineIds'][0], '收货员'), '尚未全额退款');
    $add('phone_shop_order_refund', ['order_id' => $online['order'], 'order_goods_id' => $online['lineIds'][0], 'money' => 5000, 'status' => \addon\phone_shop\app\dict\order\OrderRefundDict::FINISH]);
    $throws(fn() => $guard($online['assets'][0]), '尚未确认实物收回');
    $q('erp_asset')->where('id', $online['assets'][0])->update(['status' => 'in_stock', 'sale_order_id' => 0, 'sale_item_id' => 0, 'update_at' => $now]);
    $oldUid = (int)request()->uid();
    request()->uid((int)Db::name('sys_user')->where('delete_time', 0)->order('uid asc')->value('uid'));
    try {
        $throws(fn() => (new \addon\hsx_erp\app\service\admin\ErpSaleService())->create([
            'party_id' => $party, 'party_name' => '退货测试客户', 'settle_mode' => 'credit',
            'items' => [['asset_id' => $online['assets'][0], 'sale_price' => 5000]],
        ]), '尚未确认实物收回');
    } finally { request()->uid($oldUid); }
    $ref = \addon\hsx_erp\app\support\ErpMallOrderReference::fromSale($site, $online['sale'], (int)$q('erp_sale_item')->where('sale_order_id', $online['sale'])->value('id'));
    $pending = $mallReturn->fromErp($site, $online['assets'][0], 'ONLINE', $ref + ['snapshot_at' => time(), 'return_type' => 'online_payment_refund', 'received' => 0]);
    $assert(!$pending['received'] && (int)$q('phone_shop_goods_sku')->where('sku_id', $online['skus'][0]['sku_id'])->value('stock') === 0, '退款到账不等于实物已收回');
    $throws(fn() => $guard($online['assets'][0]), '尚未确认实物收回');
    $publish = (new \addon\phone_shop\app\listener\erp\ErpPublishListing())->handle(['site_id' => $site, 'payload' => ['erp_asset_id' => $online['assets'][0]]]);
    $assert($publish['status'] === 'failed' && str_contains($publish['message'], '尚未确认实物收回'), 'ERP发布商城同样拒绝退款未收回的设备');
    $mallReturn->confirmReceived($site, $online['lineIds'][0], '收货员');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $online['skus'][0]['sku_id'])->value('stock') === 1, '确认实物收回后才可恢复库存');
    $assert((int)$q('phone_shop_goods')->where('goods_id', $online['skus'][0]['goods_id'])->value('status') === 1, '线上全额退款并确认收回后恢复上架');
    $assert($mallReturn->confirmReceived($site, $online['lineIds'][0], '收货员')['duplicate'], '确认收回重复点击不增库存');
    $assert($guard($online['assets'][0])[0]['data']['allowed'], '确认实物收回后解除ERP再售限制');
    Db::startTrans();
    try {
        $guard($online['assets'][0], 0, true);
        $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $online['skus'][0]['sku_id'])->value('stock') === 0, 'ERP销售事务立即占用商城库存，不等待通知');
    } finally { Db::rollback(); }
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $online['skus'][0]['sku_id'])->value('stock') === 1, 'ERP销售失败回滚商城占库');
    $throws(fn() => $mallReturn->confirmReceived($site + 1, $online['lineIds'][0], '其他站点'), '不存在');
    $rawLine = $q('phone_shop_order_goods')->where('order_goods_id', $online['lineIds'][0])->find();
    $view = OrderDeviceView::decorate($rawLine + ['sku' => ['sku_no' => 'OTHER']], ['payment_mode' => 'online']);
    $assert($view['device_identity']['imei'] === $online['skus'][0]['sku_no'], '显示原成交IMEI，不随当前SKU变化');
    $assert($view['return_receiver'] === '收货员' && $view['return_state'] === 'returned', '明细保留接收人及退回状态');
    $context = (new PhoneShopOrderReturnContext())->handle(['site_id' => $site, 'orders' => [['order_id' => $online['order'], 'relate_source' => ''], ['order_id' => $mixed['order'], 'relate_source' => '']]]);
    $assert($context['orders'][$online['order']]['staff_name'] === '原业务员', '退回指向原业务员，不混淆接收人员');
    $assert($context['orders'][$mixed['order']]['refund_pending'] === 1000.0 && $context['orders'][$mixed['order']]['receivable_remaining'] === 0.0, '商城退回提示显示真实剩余应收和待退款，而非原价');
    $detail = (new \addon\phone_shop\app\service\admin\order\OrderService())->getDetail($online['order']);
    $assert($detail['order_goods'][0]['device_identity']['imei'] === $online['skus'][0]['sku_no'], '真实订单详情接口包含原设备IMEI');
    $assert($detail['return_handler_name'] === '原业务员', '真实详情包含原业务员');
    $filters = ['search_type' => '', 'search_name' => '', 'status' => '', 'pay_type' => '', 'order_from' => '', 'create_time' => [], 'pay_time' => [], 'activity_type' => '', 'keyword' => '', 'payment_mode' => '', 'offline_workflow' => 0, 'offline_keyword' => '', 'order_id' => $online['order']];
    $page = (new \addon\phone_shop\app\service\admin\order\OrderService())->getPage($filters);
    $assert(count($page['data']) === 1 && $page['data'][0]['order_goods'][0]['device_identity']['imei'] === $online['skus'][0]['sku_no'], '真实订单列表接口显示原设备IMEI');
    $filters['offline_workflow'] = 1;
    $filters['order_id'] = $mixed['order'];
    $offlinePage = (new \addon\phone_shop\app\service\admin\order\OrderService())->getPage($filters);
    $assert(count($offlinePage['data']) === 1 && $offlinePage['data'][0]['order_goods'][0]['device_identity']['imei'] === $mixed['skus'][0]['sku_no'], '真实线下订单列表接口显示原设备IMEI');
    $assert($offlinePage['data'][0]['erp_return_context']['refund_pending'] === 1000.0, '线下订单列表显示实际待退款，不把原成交金额再次当应收');
    $throws(fn() => (new \addon\phone_shop\app\service\admin\order\OrderService())->confirmDeviceReceived($online['lineIds'][0], false), '确认');
    // 2026-10-01：从商城入口直接退回，覆盖无 ERP 资产、重复提交、金额变化、财务登记和批量交付。
    $offline = new \addon\phone_shop\app\service\admin\order\OfflineOrderService();
    $native = static function(float $paid = 0, int $num = 1, bool $noAr = false) use ($makeSale, $q): array {
        $row = $makeSale($paid, $num, $paid === 5000.0 * $num ? 'offline_cash' : 'offline_credit');
        $q('erp_asset')->whereIn('id', $row['assets'])->delete(); // 仅删除本测试刚构造的隔离样本，模拟商城原生商品。
        $q('erp_sale_item')->where('sale_order_id', $row['sale'])->update(['asset_id' => 0, 'model' => '商城原生测试手机']);
        foreach ($row['skus'] as $sku) $q('phone_shop_goods_sku')->where('sku_id', $sku['sku_id'])->update(['erp_asset_id' => 0]);
        if ($noAr) $q('erp_receivable')->where('id', $row['ar'])->delete();
        return $row;
    };
    $preview = static fn($s) => $offline->process(['action' => 'return_preview', 'order_id' => $s['order']]);
    $perform = static function($s, array $ids = []) use ($offline, $preview) {
        $plan = $preview($s);
        return $offline->process(['action' => 'return_received', 'order_id' => $s['order'], 'order_goods_ids' => $ids ?: $s['lineIds'],
            'preview_token' => $plan['preview_token'], 'reason' => '实际收回，隔离回归测试', 'received' => true]);
    };
    $n = $native(0);
    $assert($preview($n)['items'][0]['can_return'], '原生商品不能因 asset_id 为0被禁用');
    $out = $perform($n);
    $assert($out['offset_amount'] === 5000.0 && $out['refund_amount'] === 0.0, '挂账整退只冲应收，不产生退款');
    $assert($q('erp_receivable')->where('id', $n['ar'])->value('status') === 'void', '全额未收应收作废，不再催款');
    $assert((int)$q('phone_shop_order')->where('order_id', $n['order'])->value('status') === -1, '商城直接入口全退关闭原单');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $n['skus'][0]['sku_id'])->value('stock') === 1, '商城原生退回恢复一台');
    $assert((int)$q('phone_shop_goods')->where('goods_id', $n['skus'][0]['goods_id'])->value('status') === 1, '商城原生退回自动恢复上架');
    $returnCount = $q('erp_sale_return')->count();
    $assert($perform($n)['duplicate'], '重复提交退回返回原结果');
    $assert($returnCount === $q('erp_sale_return')->count(), '重复提交不能重复建退款单');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $n['skus'][0]['sku_id'])->value('stock') === 1, '重复提交不重复加库存');
    $q('phone_shop_goods')->where('goods_id', $n['skus'][0]['goods_id'])->update(['status' => 0]);
    $perform($n);
    $assert((int)$q('phone_shop_goods')->where('goods_id', $n['skus'][0]['goods_id'])->value('status') === 0, '重复退回不能覆盖后来人工下架');
    $alreadyStocked = $native(0);
    $q('phone_shop_goods_sku')->where('sku_id', $alreadyStocked['skus'][0]['sku_id'])->update(['stock' => 1]);
    $perform($alreadyStocked);
    $assert((int)$q('phone_shop_goods')->where('goods_id', $alreadyStocked['skus'][0]['goods_id'])->value('status') === 1, '首次确认时库存已回补也不能漏掉恢复上架');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $alreadyStocked['skus'][0]['sku_id'])->value('stock') === 1, '首次确认库存已回补不能增加到两台');
    $offlineOnly = $native(0);
    $q('phone_shop_goods')->where('goods_id', $offlineOnly['skus'][0]['goods_id'])->update(['is_online_sellable' => 0]);
    $perform($offlineOnly);
    $assert((int)$q('phone_shop_goods')->where('goods_id', $offlineOnly['skus'][0]['goods_id'])->value('is_online_sellable') === 0, '退回不擅自打开原本禁用的线上销售权限');
    $atomic = $native(0, 2); $atomicPlan = $preview($atomic);
    $q('phone_shop_goods_sku')->where('sku_id', $atomic['skus'][1]['sku_id'])->update(['erp_asset_id' => 99999999]);
    $beforeReturns = $q('erp_sale_return')->count();
    $throws(fn() => $offline->process(['action' => 'return_received', 'order_id' => $atomic['order'], 'order_goods_ids' => $atomic['lineIds'],
        'reason' => '验证整笔事务回滚', 'received' => true, 'preview_token' => $atomicPlan['preview_token']]), '关联已变化');
    $assert($q('erp_sale_return')->count() === $beforeReturns && (float)$q('erp_receivable')->where('id', $atomic['ar'])->value('amount') === 10000.0, '第二台库存校验失败，第一台退货及整笔冲账一并回滚');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $atomic['skus'][0]['sku_id'])->value('stock') === 0, '事务失败不遗留第一台回库');
    $assert((int)$q('phone_shop_goods')->where('goods_id', $atomic['skus'][0]['goods_id'])->value('status') === 0, '事务失败同时回滚第一台自动上架');

    $nPaid = $native(5000, 1, true);
    $memberId = $add('member', ['nickname' => '原生成交客户', 'username' => 'return-' . $tag, 'mobile' => '13900000001']);
    $q('phone_shop_order')->where('order_id', $nPaid['order'])->update(['member_id' => $memberId]);
    $q('erp_sale_order')->where('id', $nPaid['sale'])->update(['party_id' => 0]);
    $assetCount = $q('erp_asset')->count(); $cashCount = $q('erp_money_ledger')->count();
    $out = $perform($nPaid); $nativeReturnId = $out['items'][0]['return_id'];
    $assert($out['refund_amount'] === 5000.0 && $out['offset_amount'] === 0.0, '现结无应收记录也必须全额转退款');
    $assert($q('erp_asset')->count() === $assetCount, '原生退回不得补造 ERP 设备');
    $assert($q('erp_money_ledger')->count() === $cashCount, '收回设备不表示已经给客户转账');
    $nativePayable = $q('erp_payable')->where('source_type', 'sale_return')->where('source_id', $nativeReturnId)->find();
    $assert((int)$nativePayable['party_id'] > 0 && $nativePayable['status'] === 'pending', '原会员解析为退款对象，待财务处理');
    $assert(str_contains($preview($nPaid)['items'][0]['reason'], '待财务退款'), '再次打开订单明确提示仍待退款，不重复办理');
    $finance = new \addon\hsx_erp\app\service\admin\ErpFinanceService();
    $method = new ReflectionMethod($finance, 'saleReturnPayableItems'); $method->setAccessible(true);
    $page = $method->invoke($finance, (int)$nativePayable['party_id'], ['source_type' => 'sale_return', 'purchase_order_id' => $nativeReturnId]);
    $assert($page['data'][0]['imei'] === $nPaid['skus'][0]['sku_no'], '财务退款明细必须显示原生商品 IMEI');
    $account = $add('erp_capital_account', ['account_name' => '隔离退款账户', 'account_type' => 'bank', 'balance' => 20000, 'status' => 1]);
    $settlement = $finance->confirmPayableItemsInTransaction((int)$nativePayable['party_id'], [['payable_id' => $nativePayable['id'], 'amount' => 5000]],
        ['capital_account_id' => $account, 'remark' => '模拟已实际转账后登记，不调用支付渠道']);
    $assert($settlement > 0 && $q('erp_payable')->where('id', $nativePayable['id'])->value('status') === 'settled', '财务可选择账户登记完整退款');
    $assert((float)$q('erp_capital_account')->where('id', $account)->value('balance') === 15000.0, '登记退款只扣所选账户5000');
    $returnDetail = $erpReturn->info($nativeReturnId);
    $assert((float)$returnDetail['items'][0]['refund_settled_amount'] === 5000.0 && (float)$returnDetail['items'][0]['refund_remain_amount'] === 0.0, '退货详情从应付事实显示已退款，不保留虚假的待退款');
    $assert(str_contains($preview($nPaid)['items'][0]['reason'], '退款已登记'), '财务登记后商城退回提示更新');
    $throws(fn() => $finance->confirmPayableItemsInTransaction((int)$nativePayable['party_id'], [['payable_id' => $nativePayable['id'], 'amount' => 5000]], ['capital_account_id' => $account]), '只能付款');

    $nMulti = $native(10000, 2, true);
    $out = $perform($nMulti, [$nMulti['lineIds'][0]]);
    $assert($out['refund_amount'] === 5000.0, '两台同为asset0的原生商品不能合并退款');
    $assert((int)$q('phone_shop_order')->where('order_id', $nMulti['order'])->value('status') === 2, '部分退回保留其他设备订单');
    $assert((int)$q('phone_shop_goods')->where('goods_id', $nMulti['skus'][0]['goods_id'])->value('status') === 1 && (int)$q('phone_shop_goods')->where('goods_id', $nMulti['skus'][1]['goods_id'])->value('status') === 0, '部分退回仅恢复已收到的设备上架');
    $out = $perform($nMulti, [$nMulti['lineIds'][1]]);
    $assert($out['refund_amount'] === 5000.0 && (int)$q('phone_shop_order')->where('order_id', $nMulti['order'])->value('status') === -1, '剩余设备独立退款，全退才关闭');
    $nPartial = $native(1000, 2);
    $out = $perform($nPartial);
    $assert($out['refund_amount'] === 1000.0 && $out['offset_amount'] === 9000.0, '分次收款异常情形也不多退，逐台分摊后合计守恒');

    $linked = $makeSale(0);
    $out = $perform($linked);
    $assert($out['offset_amount'] === 5000.0 && $q('erp_asset')->where('id', $linked['assets'][0])->value('status') === 'in_stock', '关联ERP设备通过商城入口实际回库');
    $firstLinkedReturn = $out['items'][0]['return_id'];
    $erpReturn->cancel($firstLinkedReturn, '隔离样本：撤销后重新收回');
    $out = $perform($linked);
    $assert($out['items'][0]['return_id'] !== $firstLinkedReturn && $out['offset_amount'] === 5000.0, '合法撤销后可重新退回，不误用已撤销幂等键');
    $changed = $native();
    $stalePlan = $preview($changed);
    $q('erp_receivable')->where('id', $changed['ar'])->update(['settled_amount' => 100]);
    $throws(fn() => $offline->process(['action' => 'return_received', 'order_id' => $changed['order'], 'order_goods_ids' => $changed['lineIds'], 'preview_token' => $stalePlan['preview_token'], 'reason' => '测试金额变更', 'received' => true]), '状态已变化');
    $assert((int)$q('phone_shop_goods_sku')->where('sku_id', $changed['skus'][0]['sku_id'])->value('stock') === 0, '金额变化拒绝后不恢复库存');
    $throws(fn() => $offline->process(['action' => 'return_received', 'order_id' => $changed['order'], 'order_goods_ids' => $changed['lineIds'], 'reason' => '未收实物', 'received' => false]), '实际收回');
    $throws(fn() => $perform($changed, $nPaid['lineIds']), '不属于');
    request()->siteId($site + 1);
    $throws(fn() => (new \addon\phone_shop\app\service\admin\order\OfflineOrderService())->process(['action' => 'return_preview', 'order_id' => $changed['order']]), '不存在');
    request()->siteId($site);
    Event::remove('ErpOfflineSaleReturnRequested');
    $throws(fn() => $preview($changed), '服务未就绪');
    Event::listen('ErpOfflineSaleReturnRequested', \addon\hsx_erp\app\listener\ErpOfflineSaleReturnRequested::class);
    $missing = $native();
    $q('erp_sale_order')->where('id', $missing['sale'])->delete();
    $throws(fn() => $preview($missing), '未找到原销售');

    // 原订单两台已退一台，剩余一台仍可正常交付和完成；按单反馈，不伪造整批成功。
    $ship = $native(0, 2); $perform($ship, [$ship['lineIds'][0]]);
    $q('phone_shop_order')->where('order_id', $ship['order'])->update(['delivery_type' => 'express']);
    $q('phone_shop_order_goods')->where('order_id', $ship['order'])->update(['goods_type' => 'real', 'delivery_status' => 'wait_delivery']);
    $company = $add('phone_shop_delivery_company', ['company_name' => '隔离测试快递', 'express_no' => 'test']);
    $batch = new \addon\phone_shop\app\service\admin\order\OfflineOrderBatchService();
    $result = $batch->process(['action' => 'batch_delivery', 'confirmed' => true, 'items' => [
        ['order_id' => $ship['order'], 'express_company_id' => $company, 'express_number' => 'TEST123456789'],
        ['order_id' => $nPaid['order'], 'express_company_id' => $company, 'express_number' => 'TEST987654321'],
    ]]);
    $assert($result['success_count'] === 1 && $result['failed_count'] === 1, '批量发货逐单反馈：' . json_encode($result, JSON_UNESCAPED_UNICODE));
    $assert((int)$q('phone_shop_order')->where('order_id', $ship['order'])->value('status') === 3, '已退回行不阻止剩余设备进入待收货');
    $assert($q('phone_shop_order_delivery')->where('order_id', $ship['order'])->count() === 1, '批量发货只登记一个真实运单');
    $result = $batch->process(['action' => 'batch_finish', 'confirmed' => true, 'items' => [['order_id' => $ship['order']], ['order_id' => $missing['order']]]]);
    $assert($result['success_count'] === 1 && $result['failed_count'] === 1, '只有已发货订单可以批量完成');
    $assert((int)$q('phone_shop_order')->where('order_id', $ship['order'])->value('status') === 5, '完成后订单状态正确');
    $assert($q('phone_shop_order_goods')->where('order_goods_id', $ship['lineIds'][0])->value('delivery_status') !== 'taked', '已退回行不能被批量完成改成客户收货');
    $throws(fn() => $batch->process(['action' => 'batch_delivery', 'confirmed' => false, 'items' => [['order_id' => $missing['order']]]]), '确认实际');
} finally { Db::rollback(); }
foreach ($tables as $table) if (Db::name($table)->where('site_id', $site)->count()) throw new RuntimeException('样本未回滚：' . $table);
echo "PASS: {$assertions} assertions; all isolated rows rolled back.\n";
