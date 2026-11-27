<?php
declare(strict_types=1);

/** Real pickup projection/manual/refresh service with memory model, gateway and notification boundaries. */
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace core\base { class BaseCoreService {} }
namespace pickup_projection_test {
    final class Store {
        public static array $orders = [], $records = [], $notifications = [], $calls = [], $locks = [], $writes = [];
        public static string $outcome = 'ok';
        public static bool $verifyFlow = false, $failReturn = false;
        public static int $lockDepth = 0;
    }
    class Model {
        protected array $data;
        protected static string $table;
        public function __construct(array $data) { $this->data = $data; }
        public function __get(string $key) { return $this->data[$key] ?? null; }
        public function __isset(string $key): bool { return isset($this->data[$key]); }
        public static function where(...$args): Query { return (new Query(static::class, static::$table))->where(...$args); }
        public function save(array $data): bool {
            $this->data = array_replace($this->data, $data);
            Store::${static::$table}[$this->data['id']] = $this->data;
            Store::$writes[] = [static::$table, $this->data['id'], $data];
            return true;
        }
        public function refresh(): self { $this->data = Store::${static::$table}[$this->data['id']]; return $this; }
        public function toArray(): array { return $this->data; }
        public function isEmpty(): bool { return empty($this->data); }
    }
    final class Query {
        private string $class, $table;
        private array $conditions = [];
        public function __construct(string $class, string $table) { $this->class = $class; $this->table = $table; }
        public function where($key, $value): self { $this->conditions[$key] = $value; return $this; }
        public function order(string $value): self { return $this; }
        public function findOrEmpty(): Model { return $this->find() ?? new $this->class([]); }
        public function update(array $data): int { $row = $this->find(); if (!$row) return 0; $row->save($data); return 1; }
        public function find(?int $id = null): ?Model {
            foreach (array_reverse(Store::${$this->table}, true) as $row) {
                if ($id !== null && $row['id'] !== $id) continue;
                foreach ($this->conditions as $key => $value) if (($row[$key] ?? null) != $value) continue 2;
                return new $this->class($row);
            }
            return null;
        }
    }
}
namespace addon\hsx_recycle\app\model\order {
    class RecycleOrder extends \pickup_projection_test\Model { protected static string $table = 'orders'; }
}
namespace addon\hsx_recycle\app\model\express {
    class ExpressOrderRecord extends \pickup_projection_test\Model { protected static string $table = 'records'; }
}
namespace addon\hsx_recycle\app\service\core\express {
    class ExpressProviderRegistry {
        public function queryRequirements(): array { return ['kuaidi100' => ['provider_task_id'], 'sf_direct' => ['provider_order_id']]; }
    }
    class ExpressOperationLock {
        public static function run(int $site, string $key, callable $run) {
            \pickup_projection_test\Store::$locks[] = [$site, $key];
            ++\pickup_projection_test\Store::$lockDepth;
            try { return $run(); } finally { --\pickup_projection_test\Store::$lockDepth; }
        }
    }
}
namespace addon\hsx_recycle\app\service\core {
    class ExpressOrderService {
        public function cancelOrInterceptOrder(int $site, array $params): bool {
            if (\pickup_projection_test\Store::$verifyFlow) {
                \same(false, \think\facade\Db::$active, '真实流程提交后才请求快递取消');
                \same(9, \pickup_projection_test\Store::$orders[5]['status'], '请求快递时业务已取消');
                \same(true, \pickup_projection_test\Store::$lockDepth > 0, '持有原预约锁直至渠道处理完毕');
            }
            \pickup_projection_test\Store::$calls[] = ['cancel', $site, $params];
            if (\pickup_projection_test\Store::$outcome === 'error') throw new \RuntimeException('secret=DO_NOT_RETURN timeout');
            $record = \addon\hsx_recycle\app\model\express\ExpressOrderRecord::where('site_id', $site)->where('third_order_no', $params['third_order_no'])->find();
            (new \addon\hsx_recycle\app\service\core\express\RecyclePickupService())->applyResult($record,
                ['booking_state' => 'cancelled', 'cancellation' => ['state' => 'confirmed']]);
            return true;
        }
        public function getOrderDetail(int $site, array $params): array {
            \pickup_projection_test\Store::$calls[] = [$site, $params];
            if (\pickup_projection_test\Store::$outcome === 'error') throw new \RuntimeException('secret=DO_NOT_RETURN timeout');
            $record = \addon\hsx_recycle\app\model\express\ExpressOrderRecord::where('site_id', $site)->where('third_order_no', $params['thirdOrderNo'])->find();
            $result = ['booking_state' => 'assigned', 'courier_name' => '测试取件员', 'courier_phone' => '00000000000'];
            (new \addon\hsx_recycle\app\service\core\express\RecyclePickupService())->applyResult($record, $result);
            return $result;
        }
    }
}
namespace addon\hsx_recycle\app\service\core\recycle_order {
    class CoreRecycleOrderStatusService {
        public function transition(int $id, int $status, array $data): void { \pickup_projection_test\Store::$orders[$id]['status'] = $status; }
    }
    class CoreRecycleOrderCancelReturnService {
        public function sync(...$args): void {
            if (\pickup_projection_test\Store::$failReturn) throw new \core\exception\CommonException('模拟取消订单事务失败');
        }
    }
    class CoreRecyclePickupNotifyService {
        public function notify(int $site, int $id, array $pickup): void { \pickup_projection_test\Store::$notifications[] = [$site, $id, $pickup]; }
    }
}
namespace addon\hsx_recycle\app\dict\order {
    class RecycleOrderApiFlowDict {
        public static function getFlowConfig(int $status): array {
            return ['status_name' => '测试待签收', 'actions' => $status === 1 ? ['cancel'] : [],
                'transitions' => ['cancel' => ['to_status' => 9, 'handler' => 'CancelHandler', 'require_data' => ['reason']]]];
        }
    }
}
namespace think\facade {
    class Log { public static function warning(...$args): void {} public static function error(...$args): void {} public static function info(...$args): void {} }
    class Db {
        public static bool $active = false;
        private static array $snapshot = [];
        public static function startTrans(): void {
            self::$snapshot = [\pickup_projection_test\Store::$orders, \pickup_projection_test\Store::$records];
            self::$active = true;
        }
        public static function commit(): void { self::$active = false; }
        public static function rollback(): void {
            if (self::$active) [\pickup_projection_test\Store::$orders, \pickup_projection_test\Store::$records] = self::$snapshot;
            self::$active = false;
        }
    }
}
namespace {
    use pickup_projection_test\Store;
    use addon\hsx_recycle\app\service\core\express\RecyclePickupService;
    use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
    require dirname(__DIR__) . '/app/service/core/express/PickupState.php';
    require dirname(__DIR__) . '/app/service/core/express/RecyclePickupService.php';
    require dirname(__DIR__) . '/app/service/core/express/RecyclePickupCancellationService.php';
    require dirname(__DIR__) . '/app/service/core/express/RecycleExpressService.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/handler/BaseFlowHandler.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/handler/CancelHandler.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/CoreRecycleOrderFlowService.php';
    $checks = 0;
    function same($expected, $actual, string $label): void {
        if ($expected !== $actual) throw new \RuntimeException('FAIL ' . $label . ': ' . json_encode([$expected, $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
    }
    function fixture(string $state = 'accepted', array $extra = []): RecyclePickupService {
        Store::$orders = [5 => ['id' => 5, 'site_id' => 1, 'member_id' => 7, 'delete_at' => 0, 'status' => 1,
            'pay_status' => 0, 'total_price' => '4600.00', 'delivery_data' => '{}', 'express_no' => '', 'delivery_status' => 0]];
        Store::$records = [11 => ['id' => 11, 'site_id' => 1, 'recycle_order_id' => 5, 'third_order_no' => 'recycle_1_5',
            'order_no' => 'CHANNEL11', 'delivery_id' => 'TRACK11', 'status_history' => [], 'estimated_cost' => 0, 'estimated_weight' => 1,
            'api_response' => array_replace(['provider' => 'kuaidi100', 'provider_task_id' => 'TASK11', 'booking_state' => $state,
                'carrier_name' => '测试快递', 'pickup_time' => '2030-01-01 09:00-11:00', 'callback_salt' => 'PRIVATE'], $extra),
            'receiver_name' => '测试门店', 'receiver_mobile' => '00000000000', 'receiver_province' => '省',
            'receiver_city' => '市', 'receiver_district' => '区', 'receiver_address' => '门店地址']];
        Store::$notifications = Store::$calls = Store::$locks = Store::$writes = [];
        Store::$outcome = 'ok';
        return new RecyclePickupService();
    }
    function row(): ExpressOrderRecord { return ExpressOrderRecord::where('site_id', 1)->find(11); }
    function blocks(callable $callback, string $label): void {
        try { $callback(); } catch (\core\exception\CommonException $e) { same(true, true, $label); return; }
        throw new \RuntimeException('FAIL expected business rejection: ' . $label);
    }
    foreach (['null', 'false', 'true', '123', '"text"', 'invalid', '[]'] as $value) same([], RecyclePickupService::decode($value), 'non-object metadata safely decoded ' . $value);
    $service = fixture();
    $service->syncOrder(row());
    same('accepted', $service->view(Store::$orders[5])['state'], 'record projected to original order');
    same('TRACK11', Store::$orders[5]['express_no'], 'actual waybill projected');
    same('4600.00', Store::$orders[5]['total_price'], 'price untouched');
    same(0, Store::$orders[5]['pay_status'], 'payment untouched');
    same(1, Store::$orders[5]['status'], 'recycle stage untouched');
    same(false, strpos(json_encode($service->view(Store::$orders[5])), 'PRIVATE') !== false, 'no callback secret to customer');

    $service = fixture();
    $result = $service->refresh(1, 5, 7);
    same('updated', $result['refresh_result']['status'], 'successful query has explicit feedback');
    same('assigned', $result['state'], 'query result reflected in returned view');
    same(1, count(Store::$calls), 'one original-record query');
    same([1, ['thirdOrderNo' => 'recycle_1_5']], Store::$calls[0], 'query cannot switch site or record');
    same(true, isset(Store::$records[11]['api_response']['last_query_success_at']), 'success timestamp recorded');
    $result = $service->refresh(1, 5, 7);
    same('throttled', $result['refresh_result']['status'], 'immediate refresh clearly throttled');
    same(true, $result['refresh_result']['retry_after'] > 0, 'client gets remaining cooldown');
    same(1, count(Store::$calls), 'throttle makes no second query');

    $service = fixture('confirmed', ['last_query_at' => time()]);
    $result = $service->refresh(1, 5, 7);
    same('confirmed', $result['state'], 'throttle repairs stale order projection from persisted record');
    same([], Store::$calls, 'projection repair does not rebook or query');

    $service = fixture('unknown', ['provider_task_id' => '']);
    $result = $service->refresh(1, 5, 7);
    same('waiting_callback', $result['refresh_result']['status'], 'missing task does not pretend query success');
    same([], Store::$calls, 'missing task avoids impossible upstream query');
    same(false, $result['can_manual'], 'missing task is not a rejection');

    $service = fixture('unknown', ['provider' => 'sf_direct', 'provider_task_id' => '', 'provider_order_id' => '']);
    $result = $service->refresh(1, 5, 7);
    same('waiting_callback', $result['refresh_result']['status'], 'SF missing original order number remains pending verification');
    same([], Store::$calls, 'SF missing identifier never sends a query or create');
    same(false, $result['can_manual'], 'SF missing identifier cannot open self-send');
    $service = fixture('unknown', ['provider' => 'sf_direct', 'provider_task_id' => '', 'provider_order_id' => 'SFP_ORIGINAL_ID']);
    $result = $service->refresh(1, 5, 7);
    same('updated', $result['refresh_result']['status'], 'SF registered identifier permits original-record refresh without kuaidi100 taskId');
    same(1, count(Store::$calls), 'SF refresh is exactly one query, not a new booking');

    $service = fixture('assigned');
    Store::$outcome = 'error';
    $result = $service->refresh(1, 5, 7);
    same('unavailable', $result['refresh_result']['status'], 'query failure explicit');
    same('assigned', $result['state'], 'query failure preserves last confirmed state and repairs projection');
    same(false, strpos(json_encode($result), 'DO_NOT_RETURN') !== false, 'raw query exception hidden');
    same(false, $result['can_manual'], 'query failure does not enable manual shipping');

    $service = fixture();
    blocks(fn() => $service->refresh(2, 5, 7), 'cross-site refresh blocked');
    blocks(fn() => $service->refresh(1, 5, 8), 'other member refresh blocked');
    same([], Store::$calls, 'authorization checked before query');
    same([], Store::$writes, 'authorization checked before writes');

    $service = fixture('failed');
    $service->syncOrder(row());
    blocks(fn() => $service->manual(1, 5, 7, ['express_company' => '快递', 'express_no' => 'short']), 'short waybill blocked');
    $manual = $service->manual(1, 5, 7, ['express_company' => '自行寄件快递', 'express_no' => 'NEWTRACK12']);
    same('manual', $manual['state'], 'known rejection allows manual on original order');
    same(1, count(Store::$orders), 'manual does not create second recycle order');
    same('NEWTRACK12', Store::$orders[5]['express_no'], 'manual real waybill stored');
    blocks(fn() => $service->manual(1, 5, 7, ['express_company' => '快递', 'express_no' => 'SECOND12']), 'manual duplicate/overwrite blocked');
    same('not_required', $service->refresh(1, 5, 7)['refresh_result']['status'], 'manual has explicit no-provider-query result');
    $service->applyResult(row(), ['booking_state' => 'picked_up', 'deliveryId' => 'OLDTRACK22']);
    $view = $service->view(Store::$orders[5]);
    same('manual', $view['state'], 'late original callback preserves manual record');
    same('NEWTRACK12', $view['tracking_no'], 'late original callback cannot overwrite actual new waybill');
    same(true, $view['conflict'], 'late pickup marks conflict');
    same('取件记录待人工核实', $view['title'], 'conflict has no misleading success title');
    same(false, $view['can_manual'], 'conflict cannot enable another shipment');
    foreach (Store::$writes as [$table, $id, $data]) if ($table === 'orders') {
        same([], array_intersect(array_keys($data), ['status', 'pay_status', 'total_price']), 'projection never writes recycle/payment fields');
    }
    // 真实回收取消处理器：状态 0 / 无运单号不能漏取消，且外部调用在提交阶段之后。
    $cancellation = new \addon\hsx_recycle\app\service\core\express\RecyclePickupCancellationService();
    $handler = new \addon\hsx_recycle\app\service\core\recycle_order\handler\CancelHandler();
    foreach (['accepted', 'unknown', 'submitting', 'assigned'] as $state) {
        $service = fixture($state, ['provider' => 'sf_direct', 'provider_order_id' => 'SF_ORIGINAL']);
        Store::$records[11]['delivery_id'] = '';
        $result = $handler->handle(Store::$orders[5], ['reason' => '本地测试取消'], ['operator_id' => 7]);
        same('pending', $result['data']['pickup_cancellation']['state'], $state . ' 创建取消意图');
        same([], Store::$calls, $state . ' 事务内不发送外部取消');
        same('pending', Store::$records[11]['api_response']['cancellation']['state'], $state . ' 后台可查取消意图');
        Store::$orders[5]['status'] = 9; // 模拟流程引擎提交回收取消，以下才运行提交后动作。
        $result = $cancellation->complete(1, 5);
        same('confirmed', $result['state'], $state . ' 通过原预约完成取消');
        same([['cancel', 1, ['third_order_no' => 'recycle_1_5']]], Store::$calls, $state . ' 使用原预约编号而不是运单号');
        same('回收订单和取件预约均已取消，无需寄件。', $service->view(Store::$orders[5])['message'], $state . ' 不再引导已取消客户寄件');
    }
    $service = fixture('accepted');
    $handler->handle(Store::$orders[5], ['reason' => '取消'], []);
    Store::$orders[5]['status'] = 9;
    Store::$outcome = 'error';
    $result = $cancellation->complete(1, 5);
    same('unknown', $result['state'], '取消超时不报确定成功');
    $view = $service->view(Store::$orders[5]);
    same('取件取消待核实', $view['title'], '客户详情显示取消异常');
    same(false, str_contains(json_encode($view), 'DO_NOT_RETURN'), '异常不泄漏上游原始信息');
    same(9, Store::$orders[5]['status'], '取消快递失败不撤销已提交的回收取消');
    same(false, $view['can_manual'], '未知取消结果不开放自行寄件');
    foreach (['picked_up', 'in_transit', 'delivered'] as $state) {
        fixture($state);
        $result = $handler->handle(Store::$orders[5], ['reason' => '取消'], []);
        same('manual_review', $result['data']['pickup_cancellation']['state'], $state . ' 提醒联系快递拦截/退回');
        $cancellation->complete(1, 5);
        same([], Store::$calls, $state . ' 不误取消已履约包裹');
    }
    foreach (['cancelled', 'failed'] as $state) {
        fixture($state);
        $handler->handle(Store::$orders[5], ['reason' => '取消'], []);
        $cancellation->complete(1, 5);
        same([], Store::$calls, $state . ' 不重复联系快递');
    }
    fixture('accepted'); Store::$orders[5]['delivery_platform'] = 'manual';
    same('not_required', $cancellation->prepare(Store::$orders[5])['state'], '自行寄件不冒充平台预约取消');
    same([], Store::$calls, '自行寄件不调用取消');
    fixture('accepted'); Store::$records = []; Store::$orders[5]['delivery_order_id'] = 'ORPHAN';
    same('manual_review', $cancellation->prepare(Store::$orders[5])['state'], '有预约编号但缺原记录明确留人工核实');
    same([], Store::$calls, '缺失原记录不切换默认服务商盲目取消');
    $flow = new \addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService();
    fixture('accepted'); Store::$verifyFlow = true;
    $result = $flow->execute(5, 'cancel', ['reason' => '测试取消'], 'api', ['site_id' => 1]);
    same(true, $result['success'], '真实流程引擎执行取消成功');
    same('confirmed', $result['data']['data']['pickup_cancellation']['state'], '渠道取消结果合并回流程响应');
    same(0, Store::$lockDepth, '成功释放预约锁');
    fixture('accepted'); Store::$failReturn = true;
    blocks(fn() => $flow->execute(5, 'cancel', ['reason' => '测试取消'], 'api', ['site_id' => 1]), '提交前失败正确回滚');
    same(1, Store::$orders[5]['status'], '事务失败保留原回收状态');
    same(false, isset(Store::$records[11]['api_response']['cancellation']), '事务失败不残留取消意图');
    same([], Store::$calls, '事务失败绝不先取消外部预约');
    same(0, Store::$lockDepth, '失败释放预约锁');
    Store::$failReturn = false;
    fixture('accepted'); Store::$outcome = 'error';
    $result = $flow->execute(5, 'cancel', ['reason' => '测试取消'], 'api', ['site_id' => 1]);
    same(true, $result['success'], '渠道超时仍明确业务已取消');
    same('unknown', $result['data']['data']['pickup_cancellation']['state'], '渠道超时不冒充取消成功');
    same(9, Store::$orders[5]['status'], '渠道超时不回滚已经提交的业务状态');
    fixture('accepted');
    blocks(fn() => $flow->execute(5, 'cancel', ['reason' => '测试取消'], 'api', ['site_id' => 2]), '跨站取消被流程阻断');
    same([], Store::$calls, '跨站取消不联系服务商');
    blocks(fn() => $flow->execute(5, 'cancel', [], 'api', ['site_id' => 1]), '缺少取消理由不执行');
    same([], Store::$calls, '业务校验不通过不联系服务商');
    foreach ([8, 9, -1] as $closedStatus) {
        fixture('accepted'); Store::$orders[5]['status'] = $closedStatus;
        blocks(fn() => (new \addon\hsx_recycle\app\service\core\express\RecycleExpressService())->createOrder(1, 5, []), '已取消/关闭订单不再叫件 ' . $closedStatus);
        same([[1, 'recycle_1_5']], Store::$locks, '叫件与取消按原单共用锁');
        same([], Store::$calls, '关闭闸门先于任何渠道请求');
    }
    echo "PASS {$checks} projection/cancellation checks (real service, memory models/gateway/notifications; no DB/network)\n";
}
