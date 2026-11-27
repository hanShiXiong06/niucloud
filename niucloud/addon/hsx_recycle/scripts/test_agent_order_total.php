<?php
declare(strict_types=1);

// 执行真实 CoreRecycleOrderService::create；外围配置、事务与持久化使用内存替身。
// 不加载 .env、不连接业务数据库、不调用通知、打印、支付或外网。
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace AgentOrderTotalFixture {
    final class Store {
        public static array $orders = [], $devices = [], $events = [], $errors = [], $transactions = [];
        public static function reset(): void { self::$orders = self::$devices = self::$events = self::$errors = self::$transactions = []; }
        public static function snapshot(): array { return [self::$orders, self::$devices, self::$events]; }
    }
    final class Row {
        private array $data;
        public function __construct(array $data) { $this->data = $data; }
        public function __get(string $key) { return $this->data[$key] ?? null; }
        public function toArray(): array { return $this->data; }
    }
}
namespace core\base { class BaseCoreService { public function __construct() {} } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace think\facade {
    final class Db {
        public static function startTrans(): void { \AgentOrderTotalFixture\Store::$transactions[] = \AgentOrderTotalFixture\Store::snapshot(); }
        public static function commit(): void { array_pop(\AgentOrderTotalFixture\Store::$transactions); }
        public static function rollback(): void {
            $snapshot = array_pop(\AgentOrderTotalFixture\Store::$transactions);
            if ($snapshot !== null) [\AgentOrderTotalFixture\Store::$orders, \AgentOrderTotalFixture\Store::$devices, \AgentOrderTotalFixture\Store::$events] = $snapshot;
        }
    }
    final class Log { public static function error($message, $context = []): void { \AgentOrderTotalFixture\Store::$errors[] = (string)$message; } }
}
namespace addon\hsx_recycle\app\model\order {
    final class RecycleOrder {
        public static function create(array $data): \AgentOrderTotalFixture\Row {
            $data['id'] = count(\AgentOrderTotalFixture\Store::$orders) + 1;
            \AgentOrderTotalFixture\Store::$orders[] = $data;
            return new \AgentOrderTotalFixture\Row($data);
        }
    }
    final class RecycleDevice {
        public function saveAll(array $rows): array {
            \AgentOrderTotalFixture\Store::$devices = array_merge(\AgentOrderTotalFixture\Store::$devices, $rows);
            return [];
        }
    }
}
namespace addon\hsx_recycle\app\dict\order {
    final class RecycleOrderDict {
        public const FLOW_MODE_ORDER = 'order', FLOW_MODE_DEVICE = 'device', DELIVERY_TYPE_EXPRESS = '1', ORDER_STATUS_PENDING_SIGN = 1, DEVICE_STATUS_PENDING_CHECK = 1;
    }
}
namespace addon\hsx_recycle\app\service\core\order {
    final class OrderSubmitConfigService { public function getConfig(int $siteId): array { return ['flow' => ['mode' => 'device']]; } }
    final class LogisticsVehicleService { public function prepareOrderData(int $siteId, array $data): array { return $data; } }
    final class CoreRecycleOrderEventService { public static function orderAddAfter(array $data): void { \AgentOrderTotalFixture\Store::$events[] = ['order_add', $data]; } }
}
namespace addon\hsx_recycle\app\service\core\recycle_order {
    final class CoreRecycleOrderEventService { public static function orderCreateAfter(array $data): void { \AgentOrderTotalFixture\Store::$events[] = ['order_create', $data]; } }
}
namespace {
    use AgentOrderTotalFixture\Store;
    use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderService;
    require dirname(__DIR__) . '/app/service/core/recycle_order/CoreRecycleOrderService.php';

    $checks = 0;
    function same($expected, $actual, string $message): void {
        if ($expected !== $actual) throw new \RuntimeException($message . ': expected=' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', actual=' . json_encode($actual, JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
    }
    function createDraft(array $overrides = []): array {
        Store::reset();
        return (new CoreRecycleOrderService())->create(array_replace([
            'site_id' => 100005, 'member_id' => 75, 'order_source' => 'agent',
            'draft_device_entry' => true, 'devices' => [],
        ], $overrides))->toArray();
    }
    try {
        foreach ([1, '1', 10, '10', 10000, '10000'] as $value) {
            $order = createDraft(['count' => $value]);
            same((int)$value, $order['count'], '草稿保留确认后的总台数 ' . json_encode($value));
            same(0, $order['device_count'], '确认总台数不伪造已录入进度');
            same([], Store::$devices, '确认总台数不提前创建占位设备');
            same(1, count(Store::$orders), '只创建一张草稿订单');
            same(2, count(Store::$events), '沿用既有创建事件');
            same([], Store::$transactions, '创建成功不残留事务');
        }
        foreach ([[], ['count' => 0], ['count' => '0']] as $legacy) {
            $order = createDraft($legacy);
            same(0, $order['count'], '既有未提供总数的移动草稿不被误拦截');
            same(0, $order['device_count'], '既有草稿实际录入进度仍为0');
            same([], Store::$devices, '既有草稿不伪造设备');
        }
        foreach ([-1, '-1', 1.5, '1.5', '1e2', true, false, null, '', ' 10 ', [], ['10'], 10001, '10001'] as $bad) {
            Store::reset();
            try {
                (new CoreRecycleOrderService())->create(['site_id' => 100005, 'member_id' => 75, 'order_source' => 'agent', 'draft_device_entry' => true, 'devices' => [], 'count' => $bad]);
                throw new \LogicException('非法台数被接受：' . json_encode($bad));
            } catch (\core\exception\CommonException $error) {
                same(true, strpos($error->getMessage(), '台数') !== false || strpos($error->getMessage(), '数量') !== false, '非法台数有明确中文提示');
                same([], Store::$orders, '非法总数不创建订单');
                same([], Store::$devices, '非法总数不创建设备');
                same([], Store::$events, '非法总数不发送创建事件');
                same([], Store::$transactions, '非法总数回滚事务');
            }
        }
        Store::reset();
        $normal = (new CoreRecycleOrderService())->create(['site_id' => 100005, 'member_id' => 75, 'count' => 3, 'devices' => []])->toArray();
        same(3, $normal['count'], '普通客户按数量下单保持原语义');
        same(3, $normal['device_count'], '普通客户提交设备数量保留原行为');
        same([], Store::$devices, '未填型号串号不会伪造具体设备记录');
        echo '[通过] 代下单总台数核心创建 ' . $checks . ' 项；真实核心服务与内存外围替身，无业务库/通知/打印。' . PHP_EOL;
    } catch (\Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL); exit(1);
    }
}
