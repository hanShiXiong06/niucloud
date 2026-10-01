<?php
declare(strict_types=1);

/**
 * 执行真实 ExpressOrderService / PickupState，模型、渠道、事件和锁边界为内存替身。
 * 不加载框架或 .env；不连接数据库，不发外部请求，不测试真实锁并发。
 * 运行：php niucloud/addon/hsx_recycle/scripts/verify_pickup_orchestration.php
 */
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace core\exception { class CommonException extends \RuntimeException {} }

namespace pickup_orchestration_test {
    final class Store
    {
        public static array $rows = [], $snapshots = [], $providers = [], $outcomes = [], $events = [], $locks = [], $logs = [];
        public static int $nextId = 1;
    }

    final class Query
    {
        private array $filters = [];
        private bool $descending = false;
        public function where($field, $operator = null, $value = null): self
        {
            if (is_array($field)) {
                foreach ($field as $condition) $this->filters[] = $condition;
            } else {
                $this->filters[] = func_num_args() === 2 ? [$field, '=', $operator] : [$field, $operator, $value];
            }
            return $this;
        }
        public function order(string $order): self
        {
            if ($order !== 'id desc') throw new \RuntimeException('Unsupported offline order: ' . $order);
            $this->descending = true;
            return $this;
        }
        public function find(): ?\addon\hsx_recycle\app\model\express\ExpressOrderRecord
        {
            $rows = array_values(Store::$rows);
            if ($this->descending) usort($rows, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
            foreach ($rows as $row) {
                $matches = true;
                foreach ($this->filters as [$field, $operator, $value]) {
                    if ($operator !== '=') throw new \RuntimeException('Unsupported offline operator');
                    if (($row[$field] ?? null) != $value) { $matches = false; break; }
                }
                if ($matches) return new \addon\hsx_recycle\app\model\express\ExpressOrderRecord($row);
            }
            return null;
        }
    }

    final class Adapter
    {
        public function prepareSnapshot(int $siteId, array $request): array
        {
            return Store::$snapshots[$siteId];
        }
    }
}

namespace addon\hsx_recycle\app\model\express {
    use pickup_orchestration_test\Store;
    final class ExpressOrderRecord
    {
        private array $attributes;
        public function __construct(array $attributes) { $this->attributes = $attributes; }
        public function __get(string $key) { return $this->attributes[$key] ?? null; }
        public function __isset(string $key): bool { return isset($this->attributes[$key]); }
        public static function where(...$args): \pickup_orchestration_test\Query
        {
            return (new \pickup_orchestration_test\Query())->where(...$args);
        }
        public static function createRecord(array $data): self
        {
            $data['id'] = Store::$nextId++;
            Store::$rows[$data['id']] = $data;
            Store::$events[] = ['persist_placeholder', $data['id'], $data['order_status']];
            return new self($data);
        }
        public function save(array $data): bool
        {
            $this->attributes = array_replace($this->attributes, $data);
            Store::$rows[$this->attributes['id']] = $this->attributes;
            Store::$events[] = ['persist_update', $this->attributes['id'], $this->attributes['order_status']];
            return true;
        }
        public function refresh(): self
        {
            $this->attributes = Store::$rows[$this->attributes['id']];
            return $this;
        }
    }
    final class ExpressAddressBook
    {
        public static function where(...$args): object { return new class { public function find() { return null; } }; }
        public static function create(array $data): void { Store::$events[] = ['address_book', $data['site_id']]; }
    }
}

namespace think\facade {
    final class Log
    {
        public static function error(...$args): void { \pickup_orchestration_test\Store::$logs[] = $args; }
        public static function warning(...$args): void { \pickup_orchestration_test\Store::$logs[] = $args; }
    }
}

namespace addon\hsx_recycle\app\service\core\express {
    use pickup_orchestration_test\Store;
    final class ExpressOperationLock
    {
        public static function run(int $siteId, string $key, callable $operation)
        {
            Store::$locks[] = [$siteId, $key];
            return $operation();
        }
    }
    final class ExpressDomainEventService
    {
        public function dispatch(string $name, array $payload): void { Store::$events[] = ['domain_event', $name, $payload]; }
    }
    final class ExpressProviderRegistry
    {
        public function resolve(int $siteId, string $provider = ''): \pickup_orchestration_test\Adapter
        {
            if ($provider !== Store::$snapshots[$siteId]['provider']) throw new \RuntimeException('Unexpected provider resolution');
            return new \pickup_orchestration_test\Adapter();
        }
    }
    final class ExpressGatewayService
    {
        public array $calls = [];
        public bool $failActive = false;
        public bool $cancelUnknown = false;
        public function activeProvider(int $siteId): array
        {
            if ($this->failActive) throw new \RuntimeException('Current provider unavailable');
            return ['key' => Store::$providers[$siteId], 'name' => '离线测试渠道'];
        }
        public function products(int $siteId): array
        {
            return [['product_code' => 'TEST_PICKUP', 'product_name' => '离线取件产品', 'enabled' => 1]];
        }
        public function create(int $siteId, array $request): array
        {
            $row = Store::$rows[$request['record_id'] ?? 0] ?? [];
            if (($row['site_id'] ?? 0) !== $siteId || ($row['order_status'] ?? '') !== 'submitting'
                || ($row['api_response']['booking_state'] ?? '') !== 'submitting'
                || empty($row['api_response']['callback_salt'])) {
                throw new \RuntimeException('External create called before durable placeholder/snapshot');
            }
            $this->calls[] = ['create', $siteId, $request, $row];
            Store::$events[] = ['gateway_create', $row['id']];
            $outcome = Store::$outcomes[$siteId . '|' . $request['thirdOrderNo']] ?? 'accepted';
            if ($outcome instanceof ExpressSubmissionException) throw $outcome;
            if ($outcome !== 'accepted') throw new ExpressSubmissionException('Simulated ' . $outcome, $outcome);
            return ['booking_state' => 'accepted', 'orderNo' => 'ORDER_' . $siteId . '_' . $row['id'],
                'deliveryId' => '', 'provider_task_id' => 'TASK_' . $row['id']];
        }
        public function cancel(int $siteId, array $request): array
        {
            $this->calls[] = ['cancel', $siteId, $request];
            if ($this->cancelUnknown) throw new ExpressSubmissionException('mock cancellation not confirmed', 'unknown');
            return ['booking_state' => 'cancelled'];
        }
        public function detail(int $siteId, array $request): array
        {
            $this->calls[] = ['detail', $siteId, $request];
            return []; // 本脚本只测主服务路由，状态合并由真实 PickupState 单独覆盖。
        }
    }
    final class RecyclePickupService
    {
        public function applyResult(\addon\hsx_recycle\app\model\express\ExpressOrderRecord $record, array $result): void
        {
            // 此边界只保留本地快递记录投影，不触及回收订单/通知。
            $data = PickupState::merge((array)$record->api_response, $result);
            $record->save(['api_response' => $data, 'order_status' => $data['booking_state']]);
        }
    }
}

namespace {
    use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
    use addon\hsx_recycle\app\service\core\ExpressOrderService;
    use addon\hsx_recycle\app\service\core\express\ExpressGatewayService;
    use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;
    use pickup_orchestration_test\Store;

    $plugin = dirname(__DIR__);
    require $plugin . '/app/service/core/express/PickupState.php';
    require $plugin . '/app/service/core/express/ExpressSubmissionException.php';
    require $plugin . '/app/service/core/ExpressOrderService.php';
    $checks = 0;
    function same($expected, $actual, string $label): void
    {
        if ($expected !== $actual) throw new \RuntimeException('FAIL ' . $label . ': ' . json_encode([$expected, $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
        echo 'PASS ' . $label . PHP_EOL;
    }
    function params(string $thirdOrderNo): array
    {
        return ['thirdOrderNo' => $thirdOrderNo, 'deliveryType' => 'TEST_PICKUP', 'goods' => '离线回收设备',
            'weight' => 1, 'packageCount' => 1, 'senderName' => '模拟客户', 'senderMobile' => '13800000000',
            'senderProvince' => '测试省', 'senderCity' => '测试市', 'senderDistrict' => '测试区', 'senderAddress' => '测试地址',
            'receiveName' => '模拟门店', 'receiveMobile' => '13900000000', 'receiveProvince' => '测试省',
            'receiveCity' => '测试市', 'receiveDistrict' => '测试区', 'receiveAddress' => '门店地址',
            'orderSendTime' => '2026-10-01 14:00-16:00', 'provider' => 'UNTRUSTED_PROVIDER',
            'provider_mode' => 'UNTRUSTED_MODE', 'provider_environment' => 'UNTRUSTED_ENV',
            'provider_account_fingerprint' => 'UNTRUSTED_ACCOUNT', 'product_code' => 'UNTRUSTED_PRODUCT'];
    }
    function calls(ExpressGatewayService $gateway, string $method): array
    {
        return array_values(array_filter($gateway->calls, static fn(array $call): bool => $call[0] === $method));
    }
    function record(int $siteId, string $thirdOrderNo): ExpressOrderRecord
    {
        $record = ExpressOrderRecord::where('site_id', $siteId)->where('third_order_no', $thirdOrderNo)->find();
        if (!$record) throw new \RuntimeException('Missing offline record');
        return $record;
    }

    try {
        foreach ([1, 2] as $site) {
            Store::$providers[$site] = 'ORIGINAL_' . $site;
            Store::$snapshots[$site] = ['provider' => 'ORIGINAL_' . $site, 'provider_mode' => 'online',
                'provider_environment' => 'production', 'provider_account_fingerprint' => hash('sha256', 'mock-account-' . $site),
                'provider_scene' => 'pickup', 'provider_site_id' => $site, 'provider_order_id' => 'STABLE_ORDER_' . $site,
                'product_code' => 'TEST_PICKUP', 'carrier_code' => 'mock_carrier', 'carrier_name' => '模拟承运商',
                'service_type' => '测试时效', 'payment' => '测试结算', 'callback_base' => 'https://example.invalid/push'];
        }
        $service = new ExpressOrderService();
        $gateway = new ExpressGatewayService();
        $property = new \ReflectionProperty($service, 'expressGateway');
        $property->setAccessible(true);
        $property->setValue($service, $gateway);

        $first = $service->createOrder(1, params('same-request'));
        same('accepted', $first['booking_state'], '真实主服务保留渠道受理状态');
        same(1, count(calls($gateway, 'create')), '首次预约只调用渠道一次');
        $call = calls($gateway, 'create')[0];
        same('submitting', $call[3]['order_status'], '渠道调用时占位已持久化');
        same('submitting', $call[3]['api_response']['booking_state'], '渠道调用时快照已持久化');
        same($call[3]['api_response']['callback_salt'], $call[2]['callback_salt'], '发送验签盐与已持久化盐一致');
        same(true, strlen($call[2]['callback_salt']) >= 16, '每单验签盐已生成');
        same($first['record_id'], $call[2]['record_id'], '发送时已取得本地记录 ID');
        same('https://example.invalid/push?record_id=' . $first['record_id'], $call[2]['callback_url'], '回调地址定位到该订单记录');
        foreach (Store::$snapshots[1] as $key => $value) {
            same($value, $call[2][$key] ?? null, '服务端快照覆盖请求字段 ' . $key);
        }
        same([1, 'same-request'], Store::$locks[0], '创建锁使用站点及商户请求号');

        $gateway->failActive = true; // 当前配置不可用时仍应优先复用旧请求。
        $repeat = $service->createOrder(1, ['thirdOrderNo' => 'same-request']);
        $gateway->failActive = false;
        same(true, $repeat['idempotent'], '相同请求复用已存在预约');
        same($first['record_id'], $repeat['record_id'], '重复请求返回原本地记录');
        same(1, count(calls($gateway, 'create')), '重复请求没有第二次渠道下单');
        same(false, array_key_exists('callback_salt', $repeat), '幂等返回不泄漏验签盐');

        foreach (['unknown' => 'unknown', 'rejected' => 'failed'] as $outcome => $state) {
            $key = 'request-' . $outcome;
            Store::$outcomes['1|' . $key] = $outcome;
            $before = count(calls($gateway, 'create'));
            $caught = null;
            try { $service->createOrder(1, params($key)); }
            catch (ExpressSubmissionException $e) { $caught = $e; }
            same(true, $caught instanceof ExpressSubmissionException, $outcome . ' 传递真实适配器异常');
            same($state, record(1, $key)->api_response['booking_state'], $outcome . ' 结果已落库');
            $again = $service->createOrder(1, params($key));
            same($state, $again['booking_state'], $outcome . ' 重复提交仍返回原状态');
            same(true, $again['idempotent'], $outcome . ' 重复提交命中幂等');
            same($before + 1, count(calls($gateway, 'create')), $outcome . ' 重复提交不再请求渠道');
        }

        foreach ([['provider_task_id' => 'PARTIAL_TASK'], ['orderNo' => 'PARTIAL_ORDER', 'deliveryId' => 'PARTIAL_WAYBILL']] as $index => $identifiers) {
            $key = 'partial-identifiers-' . $index;
            Store::$outcomes['1|' . $key] = new ExpressSubmissionException('Partial upstream response', 'unknown', null, $identifiers);
            $before = count(calls($gateway, 'create'));
            try { $service->createOrder(1, params($key)); }
            catch (ExpressSubmissionException $e) { same('unknown', $e->outcome(), '部分编号响应保持结果未知'); }
            $saved = record(1, $key);
            same('unknown', $saved->order_status, '部分编号不能把预约标成成功');
            foreach ($identifiers as $field => $value) same($value, $saved->api_response[$field] ?? null, '部分响应编号已持久化 ' . $field);
            same($identifiers['orderNo'] ?? '', $saved->order_no, '已取得的渠道订单号同步到索引列');
            same($identifiers['deliveryId'] ?? '', $saved->delivery_id, '已取得的运单号同步到索引列');
            $again = $service->createOrder(1, params($key));
            same('unknown', $again['booking_state'], '部分响应重试复用原未知状态');
            same(true, $again['idempotent'], '部分响应重试仍然命中幂等');
            same($before + 1, count(calls($gateway, 'create')), '部分响应后不重复调用创建');
            if (isset($identifiers['provider_task_id'])) {
                $service->getOrderDetail(1, ['thirdOrderNo' => $key]);
                $detailCalls = calls($gateway, 'detail');
                same('PARTIAL_TASK', end($detailCalls)[2]['provider_task_id'], '仅有 taskId 也能沿原账号继续核实');
            }
        }

        $otherSite = $service->createOrder(2, params('same-request'));
        same(false, $otherSite['record_id'] === $first['record_id'], '相同商户请求号按站点隔离记录');
        same(2, record(2, 'same-request')->site_id, '第二站点使用自己的预约记录');
        same('ORIGINAL_2', record(2, 'same-request')->api_response['provider'], '第二站点使用自己的渠道快照');

        Store::$providers[1] = 'NEW_DEFAULT';
        $service->getOrderDetail(1, ['order_no' => $first['orderNo'], 'provider' => 'ATTACKER',
            'provider_mode' => 'ATTACKER', 'provider_account_fingerprint' => 'ATTACKER',
            'provider_scene' => 'waybill', 'provider_site_id' => 2, 'provider_order_id' => 'OTHER_ORDER']);
        $detailCalls = calls($gateway, 'detail');
        $detail = end($detailCalls);
        same('ORIGINAL_1', $detail[2]['provider'], '查询沿用存储的服务商而非请求或新默认');
        same('online', $detail[2]['provider_mode'], '查询沿用存储的模式');
        same(Store::$snapshots[1]['provider_account_fingerprint'], $detail[2]['provider_account_fingerprint'], '查询沿用原账号指纹');
        same($first['orderNo'], $detail[2]['orderNo'], '查询沿用原渠道订单号');
        same('pickup', $detail[2]['provider_scene'], '查询不能被请求切换为电子面单');
        same(1, $detail[2]['provider_site_id'], '查询不能被请求切换站点');
        same('STABLE_ORDER_1', $detail[2]['provider_order_id'], '查询不能被请求覆盖原顺丰可查询订单号');

        $gateway->cancelUnknown = true;
        try { $service->cancelOrInterceptOrder(1, ['order_no' => $first['orderNo']]); }
        catch (ExpressSubmissionException $e) { same('unknown', $e->outcome(), '渠道未确认取消保持待核实'); }
        same('accepted', record(1, 'same-request')->api_response['booking_state'], '取消超时不能把原预约标成已取消');
        same(false, \addon\hsx_recycle\app\service\core\express\PickupState::view(record(1, 'same-request')->api_response)['can_manual'], '取消未知不能自行寄件');
        $gateway->cancelUnknown = false;
        $cancelCallsBeforeSuccess = count(calls($gateway, 'cancel'));
        same(true, $service->cancelOrInterceptOrder(1, ['order_no' => $first['orderNo'], 'provider' => 'ATTACKER']), '真实主服务取消已受理');
        $cancelCalls = calls($gateway, 'cancel');
        $cancel = end($cancelCalls);
        same('ORIGINAL_1', $cancel[2]['provider'], '取消不允许请求覆盖已存服务商');
        same(Store::$snapshots[1]['provider_account_fingerprint'], $cancel[2]['provider_account_fingerprint'], '取消使用原账号指纹');
        same('cancelled', record(1, 'same-request')->api_response['booking_state'], '取消结果投影到原记录');
        $service->cancelOrder(1, $first['orderNo'], 'ATTACKER');
        same($cancelCallsBeforeSuccess + 1, count(calls($gateway, 'cancel')), '已取消订单重复取消不再请求渠道');

        $syncDetail = new \ReflectionMethod($service, 'syncLocalExpressRecordFromDetail');
        $syncDetail->setAccessible(true);
        $syncDetail->invoke($service, 2, ['thirdOrderNo' => 'same-request'],
            ['status' => 1, 'courierPhone' => '13800000000', 'courierInfo' => '模拟取件员']);
        same('assigned', record(2, 'same-request')->api_response['booking_state'], '新易速预约查询也更新统一取件状态');
        same('模拟取件员', record(2, 'same-request')->api_response['courier_name'], '新易速预约查询保存取件员');
        $syncDetail->invoke($service, 1, ['thirdOrderNo' => 'same-request'], ['status' => 2]);
        same('cancelled', record(1, 'same-request')->api_response['booking_state'], '晚到易速查询不能覆盖已取消状态');
        same(true, record(1, 'same-request')->api_response['conflict'], '晚到易速揽收查询标记冲突供人工核实');

        foreach ([['thirdOrderNo' => 'missing-request'], ['order_no' => $otherSite['orderNo']]] as $missing) {
            $beforeDetail = count(calls($gateway, 'detail'));
            $beforeCancel = count(calls($gateway, 'cancel'));
            foreach (['getOrderDetail', 'cancelOrInterceptOrder'] as $method) {
                $blocked = false;
                try { $service->$method(1, $missing + ['provider' => 'ATTACKER']); }
                catch (\core\exception\CommonException $e) { $blocked = true; }
                same(true, $blocked, $method . ' 拒绝不存在/其他站点记录 ' . json_encode($missing));
            }
            same($beforeDetail, count(calls($gateway, 'detail')), '无本站记录不调用渠道查询');
            same($beforeCancel, count(calls($gateway, 'cancel')), '无本站记录不调用渠道取消');
        }
        same([], Store::$logs, '离线编排无被吞掉的意外错误');
        echo "PASS {$checks} checks (real ExpressOrderService/PickupState; memory boundaries; no network/database)\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->__toString() . PHP_EOL);
        exit(1);
    }
}
