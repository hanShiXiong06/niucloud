<?php
declare(strict_types=1);

namespace {
    // 真实 ThinkORM + SQLite 内存库，绝不加载应用 .env 或连接开发/生产 MySQL。
    require dirname(__DIR__) . '/niucloud/vendor/autoload.php';
}
namespace core\base {
    class BaseAdminService {
        public int $site_id = 100005;
        public int $uid = 21;
        public string $username = '测试员工';
        public function __construct() {}
    }
    class BaseCoreService { public function __construct() {} }
}
namespace addon\hsx_recycle\app\model\order {
    // 只替换与本次聚合无关的展示属性，查询构造和 SQL 执行仍使用真实 ThinkORM。
    class RecycleDevice extends \think\Model { protected $name = 'recycle_device'; }
    class RecycleOrder extends \think\Model { protected $name = 'recycle_order'; }
    class RecycleDevicePayment extends \think\Model { protected $name = 'recycle_device_payment'; }
    class RecycleReturnOrder extends \think\Model { protected $name = 'recycle_return_order'; }
}
namespace addon\hsx_recycle\app\model\stat {
    class RecycleStatDaily extends \think\Model { protected $name = 'recycle_stat_daily'; }
    class RecycleStatCurrent extends \think\Model { protected $name = 'recycle_stat_current'; }
    class RecycleTaskClaim extends \think\Model { protected $name = 'recycle_task_claim'; }
}
namespace {
    use think\facade\Db;
    use addon\hsx_recycle\app\dict\stat\RecycleStageDict as Stage;
    use addon\hsx_recycle\app\dict\dashboard\RecycleDashboardFilterDict as Filter;
    use addon\hsx_recycle\app\service\core\stat\CoreRecycleWorkloadService;
    use addon\hsx_recycle\app\service\core\stat\CoreRecycleStatService;
    use addon\hsx_recycle\app\service\admin\dashboard\RecycleDashboardFilterService;
    use addon\hsx_recycle\app\service\admin\stat\TaskService;
    use addon\hsx_recycle\app\service\admin\dashboard\RecycleDashboardMetricService;

    $root = dirname(__DIR__) . '/niucloud/';
    foreach ([
        'app/dict/order/RecycleOrderDict.php', 'app/dict/order/RecycleReturnOrderDict.php',
        'app/dict/stat/RecycleStageDict.php', 'app/dict/dashboard/RecycleDashboardFilterDict.php',
        'app/dict/dashboard/RecycleDashboardMetricDict.php',
        'app/service/core/RecycleDateRangeService.php', 'app/service/core/stat/CoreRecycleWorkloadService.php',
        'app/service/core/stat/CoreRecycleStatService.php', 'app/service/admin/dashboard/RecycleDashboardFilterService.php',
        'app/service/admin/stat/TaskService.php',
        'app/service/admin/dashboard/RecycleDashboardMetricService.php',
    ] as $file) require $root . 'addon/hsx_recycle/' . $file;

    Db::setConfig(['default' => 'isolated', 'auto_timestamp' => false, 'connections' => ['isolated' => [
        'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'ut_', 'fields_strict' => true,
    ]]]);
    foreach ([
        'recycle_order' => 'id INTEGER PRIMARY KEY, site_id INTEGER, status INTEGER, delete_at INTEGER DEFAULT 0, delivery_type TEXT DEFAULT "1", sign_at INTEGER DEFAULT 1, create_at INTEGER DEFAULT 1, update_at INTEGER DEFAULT 1, pay_time INTEGER DEFAULT 0, confirm_time INTEGER DEFAULT 1, complete_at INTEGER DEFAULT 0',
        'recycle_device' => 'id INTEGER PRIMARY KEY, site_id INTEGER, order_id INTEGER, status INTEGER, pay_status INTEGER DEFAULT 0, pay_amount DECIMAL DEFAULT 0, pay_time INTEGER DEFAULT 0, final_price DECIMAL DEFAULT 4700, initial_price DECIMAL DEFAULT 4700, confirm_status INTEGER DEFAULT 0, confirm_time INTEGER DEFAULT 1, dispose_type TEXT DEFAULT "pending", return_order_id INTEGER DEFAULT 0, create_at INTEGER DEFAULT 1, update_at INTEGER DEFAULT 1, price_at INTEGER DEFAULT 0, final_price_at INTEGER DEFAULT 0, check_uid INTEGER DEFAULT 0, price_uid INTEGER DEFAULT 0, imei TEXT DEFAULT "测试IMEI", sn TEXT DEFAULT "", model TEXT DEFAULT "测试型号"',
        'recycle_device_payment' => 'id INTEGER PRIMARY KEY, site_id INTEGER, order_id INTEGER, device_id INTEGER, pay_time INTEGER, amount DECIMAL, pay_type TEXT DEFAULT "现金"',
        'recycle_return_order' => 'id INTEGER PRIMARY KEY, site_id INTEGER, order_id INTEGER, status INTEGER, delete_at INTEGER DEFAULT 0, operator_uid INTEGER DEFAULT 0, over_at DATETIME DEFAULT NULL',
        'recycle_return_device' => 'id INTEGER PRIMARY KEY, return_order_id INTEGER, device_id INTEGER, status INTEGER DEFAULT 0',
        'recycle_stat_daily' => 'id INTEGER PRIMARY KEY, site_id INTEGER, uid INTEGER, stat_date INTEGER, metric_key TEXT, value INTEGER, amount DECIMAL DEFAULT 0',
        'recycle_stat_current' => 'id INTEGER PRIMARY KEY, site_id INTEGER, uid INTEGER, metric_key TEXT, value INTEGER, update_time INTEGER DEFAULT 0',
        'recycle_task_claim' => 'id INTEGER PRIMARY KEY, site_id INTEGER, device_id INTEGER, stage_key TEXT, assignee_uid INTEGER, assignee_name TEXT DEFAULT "测试员工"',
    ] as $table => $fields) Db::execute('CREATE TABLE ut_' . $table . ' (' . $fields . ')');

    $n = 0;
    $check = static function (bool $ok, string $name) use (&$n): void {
        if (!$ok) throw new \RuntimeException('FAIL ' . $name);
        $n++;
        echo "PASS {$name}\n";
    };
    $orders = [1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8, 10 => 9, 11 => 10, 12 => 3, 13 => 2, 14 => 2];
    foreach ($orders as $id => $status) Db::name('recycle_order')->insert([
        'id' => $id, 'site_id' => $id === 14 ? 100024 : 100005, 'status' => $status,
        'delete_at' => $id === 12 ? 100 : 0, 'delivery_type' => $id === 2 ? '3' : '1',
    ]);
    $device = static function (int $id, int $orderId, int $status, array $extra = []): void {
        Db::name('recycle_device')->insert(array_replace(['id' => $id, 'site_id' => 100005, 'order_id' => $orderId, 'status' => $status], $extra));
    };
    foreach ([[1,1,1], [2,2,1], [3,3,1], [4,4,2], [5,5,3], [6,6,4], [7,7,5], [11,10,4], [12,12,2],
        [13,999,5], [14,14,4], [25,5,7], [26,5,8], [27,13,1], [28,8,1], [32,9,4]] as $args) $device(...$args);
    $device(8, 8, 5, ['pay_status' => 2, 'pay_amount' => 4600, 'pay_time' => 100]);
    $device(9, 7, 5, ['pay_status' => 1, 'pay_amount' => 4700, 'pay_time' => 100]);
    $device(10, 7, 5, ['pay_status' => 2, 'pay_amount' => 4700, 'pay_time' => 100]);
    $device(15, 14, 1, ['site_id' => 100024]);
    $device(22, 6, 4, ['confirm_status' => 1]);
    $device(23, 6, 4, ['confirm_status' => 2]);
    $device(24, 3, 1, ['pay_status' => 1, 'pay_amount' => 4700, 'pay_time' => 100]);
    $device(29, 7, 5, ['final_price' => 0]);
    $device(30, 7, 5, ['pay_time' => 100]);
    $device(31, 7, 5, ['pay_status' => 2, 'pay_amount' => 5000, 'pay_time' => 100]);
    $device(33, 3, 1, ['dispose_type' => 'consign']);
    $device(34, 7, 5, ['dispose_type' => 'return']);

    foreach ([[1,16,0,0,0], [2,17,2,0,0], [3,18,3,0,0], [4,19,0,100,0], [5,20,1,0,3],
        [6,21,1,0,6], [7,35,0,0,0], [8,36,0,0,0]] as [$returnId, $deviceId, $orderStatus, $deleted, $rowStatus]) {
        $device($deviceId, 9, 6, ['return_order_id' => $returnId === 7 ? 999 : $returnId, 'dispose_type' => 'return']);
        Db::name('recycle_return_order')->insert(['id' => $returnId, 'site_id' => 100005,
            'order_id' => $returnId === 8 ? 3 : 9, 'status' => $orderStatus, 'delete_at' => $deleted]);
        Db::name('recycle_return_device')->insert(['id' => $returnId, 'device_id' => $deviceId,
            'return_order_id' => $returnId, 'status' => $rowStatus]);
    }
    Db::name('recycle_return_device')->insert(['id' => 99, 'device_id' => 16, 'return_order_id' => 1, 'status' => 0]);
    foreach (['stage_check' => -99, 'stage_pay' => -7, 'stage_confirm' => 999, 'stage_abnormal' => 888] as $metric => $value) {
        Db::name('recycle_stat_current')->insert(['site_id' => 100005, 'uid' => 0, 'metric_key' => $metric, 'value' => $value]);
    }

    $workload = new CoreRecycleWorkloadService();
    $expected = ['pickup' => 1, 'sign' => 1, 'check' => 3, 'price' => 3, 'confirm' => 1, 'pay' => 2, 'abnormal' => 2];
    $actual = $workload->getCurrentCounts(100005);
    $check($actual === $expected, '七个在途环节使用真实 SQL 计数：' . json_encode($actual));
    $ids = static fn(string $stage): array => array_map('intval', $workload->deviceQuery(100005, [$stage])->order('id')->column('id'));
    $check($ids('check') === [3,4,27], '质检排除未签收、删除、完成及已付款残留；正常旧订单仍保留');
    $check($ids('price') === [5,25,26], '已质检、已定价、重新定价合并为定价在途');
    $check($ids('confirm') === [6], '报价确认排除已确认、已拒绝、取消及跨站关联污染');
    $check($ids('pay') === [7,8], '打款保留未付款和真实部分付款，排除已付、零差额、孤儿及矛盾付款时间');
    $check($ids('abnormal') === [16,21], '异常只统计有效未完成退回；重复行、已完成/取消/删除和过期关联不污染');
    $check($workload->pendingPayAmount(100005) === 4800.0, '4700未付款加100补差，只统计4800待付而非9400');
    $check(Stage::stageOf(5, 2) === 'pay' && Stage::stageOf(5, 1) === '' && Stage::stageOf(5, 99) === '', '部分付款仍属于打款环节，未知付款状态不冒充待付');
    $check($workload->getCurrentCounts(100024)['check'] === 1, '其他站点独立统计');
    $check(array_sum($workload->getCurrentCounts(999999)) === 0, '无数据站点各环节为零');
    $check($ids('not_a_stage') === [], '无效环节不会意外查询全部设备');

    $before = Db::name('recycle_stat_current')->order('id')->select()->toArray();
    $board = (new CoreRecycleStatService())->getBoard(100005);
    $check(array_column($board['stages'], 'count', 'stage_key') === $expected, '看板完全不受旧汇总表负数及虚高值影响');
    $check($before === Db::name('recycle_stat_current')->order('id')->select()->toArray(), '查询看板不重建或修改旧计数表');
    $check($board['stages'][0]['unit'] === '单' && $board['stages'][1]['unit'] === '单'
        && $board['stages'][2]['unit'] === '台', '取货、签收使用订单单位，其余使用设备单位');
    $check($board['count_source'] === 'current_business' && $board['scope'] === 'site' && $board['explain'] !== '', '接口明确提供统计来源、范围和口径');

    $filter = new RecycleDashboardFilterService();
    $pairs = [Filter::PENDING_CHECK => 'check', Filter::PENDING_QUOTE => 'price', Filter::PENDING_CONFIRM => 'confirm',
        Filter::PENDING_PAY => 'pay', Filter::PENDING_RETURN => 'abnormal'];
    foreach ($pairs as $filterKey => $stage) {
        $check($filter->countDevices($filterKey) === $expected[$stage], '经营看板待办与在途共用设备口径：' . $stage);
        $drilldownIds = $filter->getOrderIds($filterKey);
        $expectedOrders = array_unique(array_map('intval', $workload->deviceQuery(100005, [$stage])->column('order_id')));
        sort($drilldownIds); sort($expectedOrders);
        $check($drilldownIds === $expectedOrders, '订单下钻不漏部分在途设备，也不带入整单无关设备：' . $stage);
    }
    $check($filter->countDevices(Filter::DEVICE_PENDING_CHECK) === 2 && $filter->countDevices(Filter::DEVICE_CHECKING) === 1,
        '待质检和质检中拆分之和等于质检在途');
    $check($filter->countOrders(Filter::PENDING_SIGN) === 1, '签收过滤不混入物流车待取货');
    $check($filter->sumDeviceFinalPrice(Filter::PENDING_PAY) === 4800.0, '待打款金额接口使用剩余差额');
    $check($filter->countDevices(Filter::PENDING_CONFIRM, ['start_time' => '2000-01-01', 'end_time' => '2000-01-02']) === 1,
        '选择历史时间不改变当前待办口径');
    $check($filter->countDevices(Filter::CHECK_TIMEOUT) === 3 && $filter->countDevices(Filter::PAY_TIMEOUT) === 2,
        '超时筛选在有效在途范围内执行');

    $metric = new RecycleDashboardMetricService();
    $range = $filter->normalizeParams(['start_time'=>date('Y-m-d'), 'end_time'=>date('Y-m-d')]);
    $ledger = (new \ReflectionMethod($metric, 'buildLedger'))->invoke($metric, $range);
    $check($ledger['pending_check_device_count'] + $ledger['checking_device_count'] === 3
        && $ledger['pending_confirm_count'] === 1 && $ledger['pending_pay_device_count'] === 2
        && $ledger['pending_return_count'] === 2, 'overview 台账待办实际调用统一口径');
    foreach (['buildCheckingTask'=>3, 'buildPendingQuoteTask'=>3, 'buildPendingConfirmTask'=>1,
        'buildPendingPayTask'=>2, 'buildPendingReturnTask'=>2] as $method => $expectedCount) {
        $task = (new \ReflectionMethod($metric, $method))->invoke($metric, $range);
        $check($task['device_count'] === $expectedCount
            && array_sum(array_column($task['owners'], 'device_count')) === $expectedCount,
            '责任分布统计与看板一致，退回关联重复不放大且不统计整单无关设备：' . $method);
    }

    foreach ([[100005,8,8,4600,'现金',0], [100005,8,8,100,'现金',-86400], [100005,12,12,500,'ERP折账',0],
        [100024,14,15,9999,'现金',0], [100005,8,8,-20,'脏金额',0]] as [$site,$order,$deviceId,$amount,$type,$offset]) {
        Db::name('recycle_device_payment')->insert(['site_id'=>$site,'order_id'=>$order,'device_id'=>$deviceId,
            'pay_time'=>time()+$offset,'amount'=>$amount,'pay_type'=>$type]);
    }
    $sumSettled = new \ReflectionMethod($metric, 'sumActualPaidAmount');
    $check($sumSettled->invoke($metric, $range) === 5100.0, '结算按真实流水发生日期和站点求和，含折账，排除无效负金额');
    $check($metric->getTrend($range)['series'][2]['data'] === [5100.0], '趋势金额与实际结算口径一致');
    $check($filter->getOrderIds(Filter::PAID_TODAY, $range) === [8]
        && array_map('intval', $filter->applyDeviceFilter($filter->newDeviceQuery($range), Filter::PAID_TODAY, $range)->column('id')) === [8],
        '结算下钻根据当期实际流水找到设备，不依赖整单完成付款；列表仍排除已删除订单');
    $paidCard = (new \ReflectionMethod($metric, 'todayPaidAmount'))->invoke($metric, $range);
    $check((float)$paidCard['value'] === 5100.0 && $paidCard['title'] === '已结算金额', '关键指标不再用整单报价替代实际结算');
    $check($sumSettled->invoke($metric, $range) === 5100.0, '已删除订单的真实历史资金流水不会因订单状态被抹掉');
    Db::name('recycle_order')->where('id',7)->update(['pay_time'=>time()]);
    $check($sumSettled->invoke($metric, $range) === 5100.0, '只有订单付款标识但没有流水，不推算虚假付款金额');
    $check($filter->getOrderIds(Filter::PAID_TODAY, $range) === [8], '没有结算流水的整单付款标识不会混入当期结算明细');
    Db::name('recycle_device')->where('id',9)->update(['final_price'=>99999]);
    $check($sumSettled->invoke($metric, $range) === 5100.0, '设备后来调价不会改变已经发生的结算金额');
    $device(40,4,5,['final_price'=>100]);
    $check($filter->countDevices(Filter::PENDING_PAY) === 3 && in_array(4, $filter->getOrderIds(Filter::PENDING_PAY)),
        '同单还有设备在质检时，已确认设备仍能计入待打款');
    Db::name('recycle_device')->where('id',40)->delete();

    class TaskProbe extends TaskService {
        public function getMyStages(): array { return array_column(Stage::getStages(), 'stage_key'); }
    }
    foreach ([[3,'check',21], [4,'check',22], [4,'price',21], [12,'check',21], [6,'confirm',21], [7,'pay',21], [8,'pay',21],
        [16,'abnormal',21], [17,'abnormal',21], [21,'abnormal',22]] as [$deviceId,$stage,$uid]) {
        Db::name('recycle_task_claim')->insert(['site_id'=>100005,'device_id'=>$deviceId,'stage_key'=>$stage,'assignee_uid'=>$uid]);
    }
    $tasks = new TaskProbe();
    foreach (['check' => [3], 'pay' => [7,8], 'confirm' => [6], 'abnormal' => [16]] as $stage => $expectedIds) {
        $result = $tasks->getTaskList(['stage' => $stage]);
        $check(array_column($result['list'], 'id') === $expectedIds && $result['count'] === count($expectedIds),
            '真实任务列表共用口径并保留本人责任范围：' . $stage);
        $check((new \ReflectionMethod($tasks, 'countAssignedPending'))->invoke($tasks, 21, $stage) === count($expectedIds),
            '分配时的个人在途计数与任务列表一致：' . $stage);
    }
    $check($tasks->getTaskList([])['count'] === 5, '全部设备任务不被其他环节条件误伤且不显示其他员工的任务');
    $check(!in_array(4, array_column($tasks->getTaskList([])['list'], 'id')),
        '曾负责另一环节的历史归属不会混入当前个人待办');
    $check($tasks->getTaskList(['stage' => 'pay', 'keyword' => '无匹配'])['count'] === 0, '关键字仍在当前环节及责任范围内生效');

    Db::name('recycle_device')->where('id', 8)->update(['pay_amount'=>4700,'pay_status'=>1]);
    Db::name('recycle_return_order')->where('id',1)->update(['status'=>2]);
    Db::name('recycle_device')->where('id',6)->update(['status'=>5,'confirm_status'=>1,'pay_status'=>1,'pay_amount'=>4700]);
    $after = $workload->getCurrentCounts(100005);
    $check($after['pay'] === 1 && $workload->pendingPayAmount(100005) === 4700.0, '补款结清后立即离开打款队列，无需增量埋点');
    $check($after['abnormal'] === 1 && $after['confirm'] === 0, '退回完成和报价处理后立即退出原环节');
    $check($filter->countDevices(Filter::PENDING_RETURN) === 1 && $tasks->getTaskList(['stage'=>'abnormal'])['count'] === 0,
        '状态变化后待办和个人任务同步反映，不依赖重算按钮');

    // 退回看板回归：创建退回时设备已经是 status=6，不能把它当作退回完成凭据。
    $todayAt = $range['start_at'] + 3600;
    Db::name('recycle_device')->where('status', 6)->update(['update_at' => $todayAt]);
    $check($filter->countDevices(Filter::RETURNED_DEVICES, $range) === 0,
        '仅设备状态为退回且今天更新，不冒充已退回客户');
    $check($filter->countDevices(Filter::PENDING_RETURN, ['start_time' => '2000-01-01', 'end_time' => '2000-01-01']) === 1,
        '退回未完成是当前全部，不随日期筛选隐藏积压设备');

    // 为同一原订单构造多台、多退回单，以及跨站、删除、取消、错关联等污染样本。
    $returnCase = static function (int $id, array $returnExtra = [], array $deviceExtra = [], array $rowExtra = []) use ($device, $todayAt): void {
        $device($id, 9, 6, array_replace(['return_order_id' => $id, 'dispose_type' => 'return', 'update_at' => $todayAt], $deviceExtra));
        Db::name('recycle_return_order')->insert(array_replace([
            'id' => $id, 'site_id' => 100005, 'order_id' => 9, 'status' => 2, 'over_at' => date('Y-m-d H:i:s', $todayAt),
        ], $returnExtra));
        Db::name('recycle_return_device')->insert(array_replace([
            'id' => $id, 'return_order_id' => $id, 'device_id' => $id, 'status' => 2,
        ], $rowExtra));
    };
    $returnCase(101, [], ['update_at' => 1]);
    $returnCase(102);
    $returnCase(103, ['over_at' => date('Y-m-d H:i:s', $todayAt - 86400)]);
    $returnCase(104, ['status' => 0]);
    $returnCase(105, ['status' => 1]);
    $returnCase(106, ['status' => 3]);
    $returnCase(107, ['delete_at' => $todayAt]);
    $returnCase(108, ['site_id' => 100024]);
    $returnCase(109, [], ['site_id' => 100024]);
    $returnCase(110, [], ['return_order_id' => 999]);
    $returnCase(111, ['order_id' => 3]);
    $returnCase(112, [], [], ['status' => 1]);
    $returnCase(113, ['order_id' => 999], ['order_id' => 999]);
    $returnCase(114, ['order_id' => 12], ['order_id' => 12]);
    $returnCase(115, ['order_id' => 14], ['order_id' => 14]);
    $returnCase(116, ['over_at' => null]);
    $returnCase(117, [], [], ['device_id' => 999]);
    Db::name('recycle_return_device')->insert(['id' => 118, 'device_id' => 101, 'return_order_id' => 101, 'status' => 2]);

    $completedIds = array_map('intval', $filter->applyDeviceFilter($filter->newDeviceQuery(), Filter::RETURNED_DEVICES, $range)->order('id')->column('id'));
    $check($completedIds === [101,102], '已退回只保留当前站点真实完成设备，排除取消、删除、孤儿、过期及跨站错关联');
    $check($filter->getOrderIds(Filter::RETURNED_DEVICES, $range) === [9], '完成退回的订单下钻包含已关闭原订单，不混入同单未退回设备');
    $check($filter->countDevices(Filter::RETURNED_DEVICES, $range) === 2
        && $filter->countOrders(Filter::RETURNED_ORDERS, $range) === 1, '一单多台、多个退回单，台数与原订单数分别去重');
    $yesterday = ['start_time' => date('Y-m-d', $todayAt - 86400), 'end_time' => date('Y-m-d', $todayAt - 86400)];
    $check($filter->countDevices(Filter::RETURNED_DEVICES, $yesterday) === 1,
        '昨天退完今天改资料，仍按昨天退回完成时间统计');
    $returnTask = (new \ReflectionMethod($metric, 'buildReturnCompletedTask'))->invoke($metric, $range);
    $pendingReturnTask = (new \ReflectionMethod($metric, 'buildPendingReturnTask'))->invoke($metric, $range);
    $returnLedger = (new \ReflectionMethod($metric, 'buildLedger'))->invoke($metric, $range);
    $check($returnTask['device_count'] === 2 && $returnTask['order_count'] === 1
        && $returnLedger['return_device_count'] === 2, '已退回客户的台账、责任分布、订单与设备明细数量一致');
    $check($pendingReturnTask['device_count'] === 1 && $pendingReturnTask['order_count'] === 1
        && $returnLedger['pending_return_count'] === 1, '退回未完成的台账、责任分布、订单与设备明细数量一致');
    $check(!isset($returnTask['route_path']) && !isset($pendingReturnTask['route_path'])
        && $returnTask['drilldown']['filter_key'] === Filter::RETURNED_DEVICES
        && $pendingReturnTask['drilldown']['filter_key'] === Filter::PENDING_RETURN,
        '责任分布查看入口使用相同过滤条件，不再跳到无筛选的全部退回单');

    Db::name('recycle_return_order')->where('id', 6)->update(['status' => 2, 'over_at' => date('Y-m-d H:i:s', $todayAt)]);
    Db::name('recycle_return_device')->where('return_order_id', 6)->update(['status' => 2]);
    $check($filter->countDevices(Filter::PENDING_RETURN) === 0 && $filter->countDevices(Filter::RETURNED_DEVICES, $range) === 3,
        '退回中转完成，立即退出未完成待办并进入所选日期已退回客户');
    Db::name('recycle_return_order')->where('id', 101)->update(['status' => 3]);
    $check($filter->countDevices(Filter::RETURNED_DEVICES, $range) === 2 && $filter->countDevices(Filter::PENDING_RETURN) === 0,
        '取消退回单即使残留完成时间或设备状态，也不进入退回统计');
    $check(Filter::get(Filter::PENDING_RETURN)['name'] === '退回未完成'
        && Filter::get(Filter::RETURNED_DEVICES)['name'] === '已退回客户', '下钻筛选标签清楚区分未完成和已完成');

    echo "\n{$n} PASS — ThinkORM + SQLite 内存库；无开发/生产数据修改\n";
}
