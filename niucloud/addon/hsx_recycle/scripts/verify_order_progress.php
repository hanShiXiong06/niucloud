<?php
declare(strict_types=1);

// 真实 ThinkORM + SQLite 内存库；不读 .env，不连接任何业务库。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    function get_lang($key, $params = []) { return $key; }
    function event($name, $data = []) { $GLOBALS['events'][] = [$name, $data]; return []; }
}
namespace core\base {
    class BaseCoreService { public function __construct() {} }
    class BaseAdminService { public int $site_id = 100005; public int $uid = 9; public function __construct() {} }
}
namespace {
    use addon\hsx_recycle\app\service\core\recycle_order\RecycleOrderProgressPolicy as Policy;
    use addon\hsx_recycle\app\service\core\recycle_order\RecycleOrderProgressService;
    use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleDeviceService;
    use addon\hsx_recycle\app\service\admin\order\RecycleOrderFlowModeService;
    use think\facade\Db;

    Db::setConfig(['default' => 'isolated', 'auto_timestamp' => false, 'connections' => ['isolated' => [
        'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'ut_', 'fields_strict' => true,
    ]]]);
    foreach ([
        'recycle_order' => 'id INTEGER PRIMARY KEY, site_id INTEGER, member_id INTEGER DEFAULT 11, status INTEGER DEFAULT 6, flow_mode TEXT DEFAULT "device", delivery_type TEXT DEFAULT "2", create_at INTEGER DEFAULT 100, update_at INTEGER DEFAULT 100, complete_at INTEGER DEFAULT 0, delete_at INTEGER DEFAULT 0, pay_status INTEGER DEFAULT 2, pay_time INTEGER DEFAULT 123, total_amount TEXT DEFAULT "300.00"',
        'recycle_device' => 'id INTEGER PRIMARY KEY, site_id INTEGER, order_id INTEGER, status INTEGER, member_id INTEGER DEFAULT 0, initial_price TEXT DEFAULT "100.00", final_price TEXT DEFAULT "100.00", pay_status INTEGER DEFAULT 0, pay_amount TEXT DEFAULT "0.00", pay_time INTEGER DEFAULT 0, confirm_status INTEGER DEFAULT 0, confirm_time INTEGER DEFAULT 0, confirm_member_id INTEGER DEFAULT 0, confirm_remark TEXT DEFAULT "", dispose_type TEXT DEFAULT "recycle", create_at INTEGER DEFAULT 100, update_at INTEGER DEFAULT 100',
        'recycle_order_log' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER, order_id INTEGER, operator_id INTEGER, operator_name TEXT, old_status INTEGER, new_status INTEGER, remark TEXT, create_at INTEGER',
        'recycle_device_log' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER, order_id INTEGER, device_id INTEGER, operator_id INTEGER, operator_name TEXT DEFAULT "", action TEXT, old_status INTEGER, new_status INTEGER, remark TEXT, create_at INTEGER',
        'recycle_return_order' => 'id INTEGER PRIMARY KEY, site_id INTEGER, order_id INTEGER, status INTEGER',
    ] as $table => $columns) Db::execute('CREATE TABLE ut_' . $table . ' (' . $columns . ')');
    $checks = 0;
    function same($expected, $actual, string $label): void {
        if ($expected !== $actual) throw new \RuntimeException('FAIL ' . $label . ': ' . json_encode([$expected, $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
        echo 'PASS ' . $label . PHP_EOL;
    }
    function seed(int $id, array $devices, array $order = []): void {
        Db::name('recycle_order')->insert(['id' => $id, 'site_id' => 100005] + $order);
        foreach ($devices as $index => $device) Db::name('recycle_device')->insert(['id' => $id * 10 + $index, 'order_id' => $id, 'site_id' => 100005] + $device);
    }
    $paid = ['status' => 5, 'pay_status' => 1, 'confirm_status' => 1, 'pay_amount' => '100.00', 'pay_time' => 120];
    $refused = ['status' => 6, 'confirm_status' => 2];
    $pending = ['status' => 4];
    $service = new RecycleOrderProgressService();
    try {
        seed(1, [$paid, $pending, $pending]);
        Db::name('recycle_return_order')->insert(['id' => 1, 'site_id' => 100005, 'order_id' => 1, 'status' => 0]);
        $firstPayment = Db::name('recycle_device')->where('id', 10)->find();
        $financeBefore = Db::name('recycle_order')->where('id', 1)->field('pay_status,pay_time,total_amount')->find();
        $core = new CoreRecycleDeviceService();
        $core->confirmMemberPrice(11, 100005, 11, false);
        same(5, (int)Db::name('recycle_order')->where('id', 1)->value('status'), '第一台已付款，第二台拒绝，第三台仍待确认');
        $core->confirmMemberPrice(12, 100005, 11, false);
        same(7, (int)Db::name('recycle_order')->where('id', 1)->value('status'), '最后一台拒绝后自动完成，不再回到待打款');
        same($firstPayment, Db::name('recycle_device')->where('id', 10)->find(), '自动完成不改已付设备和金额');
        same($financeBefore, Db::name('recycle_order')->where('id', 1)->field('pay_status,pay_time,total_amount')->find(), '核对不重写订单付款凭据或金额');
        same(0, (int)Db::name('recycle_return_order')->where('id', 1)->value('status'), '回收订单完成后，退货单仍为待处理');
        $completeAt = Db::name('recycle_order')->where('id', 1)->value('complete_at');
        $logCount = Db::name('recycle_order_log')->count();
        $again = $service->sync(100005, 1, 9);
        same(false, $again['changed'], '重复刷新不重复迁移');
        same($completeAt, Db::name('recycle_order')->where('id', 1)->value('complete_at'), '重复刷新不改完成时间');
        same($logCount, Db::name('recycle_order_log')->count(), '重复刷新不重复写状态日志');
        same(true, str_contains($again['message'], '退货单继续处理'), '明确提示还需跟进实物退回');
        same(2, $again['flow_summary']['returned'], '汇总拒绝台数正确');
        same(1, $again['flow_summary']['paid'], '汇总已付款台数正确');
        seed(2, [$paid, $refused, $refused]);
        $beforeDevices = Db::name('recycle_device')->where('order_id', 2)->select()->toArray();
        same(7, $service->sync(100005, 2, 9)['status'], '手动刷新可修复混合终态订单');
        same($beforeDevices, Db::name('recycle_device')->where('order_id', 2)->select()->toArray(), '手动刷新只写父订单，不改设备');
        same(9, (int)Db::name('recycle_order_log')->where('order_id', 2)->value('operator_id'), '手动刷新有操作者审计记录');
        seed(3, [$refused, $refused]);
        same(7, $service->sync(100005, 3)['status'], '全部拒绝可完成回收决定，不等同退货签收');
        seed(4, [['status' => 5, 'confirm_status' => 1], $refused]);
        same(6, $service->sync(100005, 4)['status'], '仍有一台未打款不能完成');
        seed(5, [['status' => 5, 'confirm_status' => 1, 'pay_status' => 2, 'pay_amount' => '50.00'], $refused]);
        same(6, $service->sync(100005, 5)['status'], '部分付款不能算结清');
        seed(6, [['status' => 2], $refused]);
        same(3, $service->sync(100005, 6)['status'], '仍有质检中设备不能完成');
        seed(7, [$paid, ['status' => 9, 'dispose_type' => 'consign']]);
        same(7, $service->sync(100005, 7)['status'], '已付款加转代卖沿用原有回收终态口径');
        seed(8, []);
        same(6, $service->sync(100005, 8)['status'], '空设备列表不误判完成');
        foreach ([1, 7, 8, 9, 10] as $status) {
            seed(20 + $status, [$paid], ['status' => $status, 'complete_at' => 55]);
            same(false, $service->sync(100005, 20 + $status)['changed'], '保留已结束/未签收状态 ' . $status);
        }
        try { $service->sync(100024, 2); throw new \RuntimeException('越站未拦截'); }
        catch (\core\exception\CommonException $e) { same(true, str_contains($e->getMessage(), '不存在'), '其他站点不可查询或刷新'); }
        seed(40, [$paid], ['delete_at' => 999]);
        try { $service->sync(100005, 40); throw new \RuntimeException('软删除未拦截'); }
        catch (\core\exception\CommonException $e) { same(true, str_contains($e->getMessage(), '已删除'), '软删除订单不可刷新'); }
        $flow = new RecycleOrderFlowModeService();
        same(Policy::summarize([$paid, $refused]), $flow->buildSummary([$paid, $refused]), '列表汇总和父订单使用同一口径');
        echo "PASS {$checks} checks (SQLite in-memory)\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->__toString() . PHP_EOL); exit(1);
    }
}
