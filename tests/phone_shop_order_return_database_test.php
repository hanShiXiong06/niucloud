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
foreach ($tables as $table) {
    if (Db::name($table)->where('site_id', $site)->count()) throw new RuntimeException('隔离站点已被占用：' . $table);
    $meta = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [config('database.connections.mysql.prefix') . $table]);
    if (strtoupper($meta[0]['ENGINE'] ?? '') !== 'INNODB') throw new RuntimeException('表不支持回滚：' . $table);
}
request()->siteId($site); request()->uid(1); request()->username('收货测试员'); request()->appType('adminapi');
foreach (['ErpDomainEvent', 'PhoneShopOrderReturnContext', 'PhoneShopSaleReturnCancelled', 'PhoneShopGoodsSaleableChanged', 'HsxPerformanceFactRecorded', 'HsxErpMallInventory'] as $event) Event::remove($event);
Event::listen('PhoneShopOrderReturnContext', PhoneShopOrderReturnContext::class);
Event::listen('PhoneShopSaleReturnCancelled', \addon\phone_shop\app\listener\erp\ErpSaleReturnCancelled::class);
Event::listen('HsxErpMallInventory', \addon\phone_shop\app\listener\erp\ErpMallInventoryProvider::class);
// 只替换外部副作用出口；调用的订单创建/关闭/库存核心代码仍为实际实现。
class ReturnTestEvents {
    public static array $created = [];
    public static function orderCreate($d) { self::$created = $d; }
    public static function orderCreateAfter($d) {}
    public static function orderClose($d) {}
    public static function orderCloseAfter($d) {}
}
class_alias(ReturnTestEvents::class, 'addon\phone_shop\app\service\core\order\CoreOrderEventService');
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
    $goods = $add('phone_shop_goods', ['goods_name' => '退回测试手机', 'goods_type' => 'real', 'stock' => 1, 'status' => 1, 'sale_status' => 'available', 'source' => '1']);
    $sku = $add('phone_shop_goods_sku', ['goods_id' => $goods, 'sku_no' => $imei, 'stock' => 1, 'is_unique' => 1, 'price' => 5000, 'cost_price' => 4500]);
    return ['goods_id' => $goods, 'sku_id' => $sku, 'sku_no' => $imei, 'is_unique' => 1];
};
$checkout = static function(array $sku) use ($site): int {
    $service = new ReturnTestCheckout(); $service->site_id = $site; $service->param = ['order_from' => 'weapp'];
    $result = $service->createOrder(['order_data' => ['site_id' => $site, 'member_id' => 0, 'order_type' => OrderDict::TYPE, 'payment_mode' => 'offline_pending', 'status' => OrderDict::WAIT_PAY, 'goods_money' => 5000, 'order_money' => 5000],
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
    $assert((int)$stock['stock'] === 1 && (int)$stock['status'] === 0 && $stock['sale_status'] === 'available', '退回恢复一台，等待业务员上架');
    $soldMethod = new ReflectionMethod(\addon\phone_shop\app\listener\order\ErpAssetSoldListener::class, 'onSold');
    $soldMethod->setAccessible(true);
    $staleSold = $soldMethod->invoke(new \addon\phone_shop\app\listener\order\ErpAssetSoldListener(), ['site_id' => $site, 'payload' => ['asset_id' => $unpaid['assets'][0], 'sale_order_id' => $unpaid['sale'], 'build_mall_order' => false]]);
    $assert($staleSold['stale'] && (int)$q('phone_shop_goods_sku')->where('sku_id', $unpaid['skus'][0]['sku_id'])->value('stock') === 1, '迟到的旧出库通知不能覆盖已退回的库存');
    $moneyCount = $q('erp_money_ledger')->count(); $arCount = $q('erp_receivable')->count();
    (new GoodsService())->editSingleStatus(['goods_id' => $unpaid['skus'][0]['goods_id'], 'status' => 1]);
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
} finally { Db::rollback(); }
foreach ($tables as $table) if (Db::name($table)->where('site_id', $site)->count()) throw new RuntimeException('样本未回滚：' . $table);
echo "PASS: {$assertions} assertions; all isolated rows rolled back.\n";
