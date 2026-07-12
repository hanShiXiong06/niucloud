<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDownstreamMirrorService;
use think\facade\Db;

$app = new think\App();
$app->initialize();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$siteId = 100005;
$suffix = date('YmdHis') . substr(md5((string)microtime(true)), 0, 6);

Db::startTrans();
try {
    $now = time();
    $orderId = (int)Db::name('recycle_order')->insertGetId([
        'site_id' => $siteId,
        'flow_mode' => 'device',
        'order_no' => 'ERP_RETURN_TEST_' . $suffix,
        'status' => RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
        'member_id' => 1,
        'device_count' => 2,
        'count' => 2,
        'create_at' => $now,
        'update_at' => $now,
    ]);
    $deviceIds = [];
    foreach ([0, 1] as $index) {
        $deviceIds[] = (int)Db::name('recycle_device')->insertGetId([
            'site_id' => $siteId,
            'order_id' => $orderId,
            'imei' => 'ERP_RETURN_' . $suffix . $index,
            'model' => 'ERP退货联调设备' . ($index + 1),
            'status' => RecycleOrderDict::DEVICE_STATUS_RECYCLED,
            'confirm_status' => RecycleOrderDict::CONFIRM_STATUS_CONFIRMED,
            'pay_status' => $index === 0 ? RecycleOrderDict::PAY_STATUS_PAID : RecycleOrderDict::PAY_STATUS_UNPAID,
            'pay_amount' => $index === 0 ? 1000 : 0,
            'final_price' => 1000,
            'dispose_type' => RecycleOrderDict::DISPOSE_TYPE_RECYCLE,
            'dispose_status' => RecycleOrderDict::DISPOSE_STATUS_RECYCLED,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    $mirror = new CoreRecycleDownstreamMirrorService();
    $first = $mirror->applyPurchaseReturn($deviceIds[0], [
        'site_id' => $siteId,
        'erp_asset_id' => 900001,
        'return_no' => 'PR_TEST_' . $suffix,
    ], 'EV_RETURN_' . $suffix . ':1');
    $assert(!empty($first['updated']), '第一台ERP退货应同步成功');
    $firstDevice = Db::name('recycle_device')->where('id', $deviceIds[0])->find();
    $assert((int)$firstDevice['status'] === RecycleOrderDict::DEVICE_STATUS_RETURNED, 'ERP退货设备应标记为已退回');
    $assert((int)$firstDevice['pay_status'] === RecycleOrderDict::PAY_STATUS_PAID, 'ERP退货不得清除已经发生的付款事实');
    $assert((int)Db::name('recycle_order')->where('id', $orderId)->value('status') === RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT, '部分设备退货时订单不能提前关闭');

    $second = $mirror->applyPurchaseReturn($deviceIds[1], [
        'site_id' => $siteId,
        'erp_asset_id' => 900002,
        'return_no' => 'PR_TEST_' . $suffix,
    ], 'EV_RETURN_' . $suffix . ':2');
    $assert(!empty($second['updated']), '第二台ERP退货应同步成功');
    $order = Db::name('recycle_order')->where('id', $orderId)->find();
    $assert((int)$order['status'] === RecycleOrderDict::ORDER_STATUS_CLOSED, '全部设备退货后回收订单应关闭');
    $assert((int)$order['close_time'] > 0 && str_contains((string)$order['close_reason'], 'ERP采购退货'), '关闭订单必须保留ERP退货原因');
    $assert((int)Db::name('recycle_device_log')->where('order_id', $orderId)->where('action', 'erp_purchase_return')->count() === 2, '每台设备必须记录ERP退货日志');

    echo "[PASS] recycle ERP return state integration test\n";
} finally {
    Db::rollback();
}
