<?php
declare(strict_types=1);

/** Real pickup projection/manual/refresh service with memory model, gateway and notification boundaries. */
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace pickup_projection_test {
    final class Store {
        public static array $orders = [], $records = [], $notifications = [], $calls = [], $locks = [], $writes = [];
        public static string $outcome = 'ok';
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
    }
    final class Query {
        private string $class, $table;
        private array $conditions = [];
        public function __construct(string $class, string $table) { $this->class = $class; $this->table = $table; }
        public function where($key, $value): self { $this->conditions[$key] = $value; return $this; }
        public function order(string $value): self { return $this; }
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
    class ExpressOperationLock {
        public static function run(int $site, string $key, callable $run) {
            \pickup_projection_test\Store::$locks[] = [$site, $key];
            return $run();
        }
    }
}
namespace addon\hsx_recycle\app\service\core {
    class ExpressOrderService {
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
    class CoreRecyclePickupNotifyService {
        public function notify(int $site, int $id, array $pickup): void { \pickup_projection_test\Store::$notifications[] = [$site, $id, $pickup]; }
    }
}
namespace think\facade { class Log { public static function warning(...$args): void {} } }
namespace {
    use pickup_projection_test\Store;
    use addon\hsx_recycle\app\service\core\express\RecyclePickupService;
    use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
    require dirname(__DIR__) . '/app/service/core/express/PickupState.php';
    require dirname(__DIR__) . '/app/service/core/express/RecyclePickupService.php';
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
    echo "PASS {$checks} projection checks (real service, memory models/gateway/notifications; no DB/network)\n";
}
