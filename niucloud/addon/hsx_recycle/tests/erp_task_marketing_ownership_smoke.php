<?php
declare(strict_types=1);

// 仅内存替身：没有数据库、任务落库、实际付款或营销积分写入。
namespace core\base {
    class BaseAdminService { protected int $site_id = 1; protected int $uid = 10; protected string $username = 'test'; }
    class BaseCoreService {}
}
namespace core\exception { class AdminException extends \RuntimeException {} }
namespace think\facade {
    class Log {
        public static array $messages = [];
        public static function __callStatic(string $method, array $args): void { self::$messages[] = [$method, $args]; }
    }
}
namespace addon\hsx_recycle\app\service\core\recycle_order {
    class RecycleErpCapabilityService {
        public static array $devices = [];
        public static array $calls = [];
        public static bool $fail = false;
        public function isPaymentManaged(int $siteId, int $orderId = 0, array $deviceIds = []): bool {
            if ($deviceIds === []) throw new \RuntimeException('must not use site-wide payment ownership');
            self::$calls[] = [$siteId, $orderId, $deviceIds];
            $owner = self::$devices[$deviceIds[0]]['owner'] ?? 'unknown';
            if ($owner === 'unknown' || self::$fail) throw new \RuntimeException('unknown payment owner');
            return $owner === 'self_erp';
        }
        public function paymentCapability(int $siteId, int $orderId = 0, array $deviceIds = []): array {
            if (self::$fail) throw new \RuntimeException('ownership lookup failed');
            self::$calls[] = [$siteId, $orderId, $deviceIds];
            return ['payment_owner' => 'mixed', 'devices' => array_values(array_intersect_key(self::$devices, array_flip($deviceIds)))];
        }
    }
}
namespace addon\hsx_recycle\app\model\order {
    class MemoryCollection {
        public function __construct(private array $rows) {}
        public function select(): self { return $this; }
        public function toArray(): array { return $this->rows; }
    }
    class MemoryOrder implements \ArrayAccess {
        public function isEmpty(): bool { return RecycleOrder::$row === []; }
        public function toArray(): array { return RecycleOrder::$row; }
        public function devices(): MemoryCollection { return new MemoryCollection(RecycleOrder::$devices); }
        public function offsetExists(mixed $offset): bool { return isset(RecycleOrder::$row[$offset]); }
        public function offsetGet(mixed $offset): mixed { return RecycleOrder::$row[$offset] ?? null; }
        public function offsetSet(mixed $offset, mixed $value): void { throw new \RuntimeException('no writes in this test'); }
        public function offsetUnset(mixed $offset): void { throw new \RuntimeException('no writes in this test'); }
    }
    class RecycleOrder {
        public static array $row = ['id' => 7, 'site_id' => 1, 'member_id' => 8, 'order_no' => 'RC-7', 'complete_at' => 100];
        public static array $devices = [];
        public function where(mixed ...$args): self { return $this; }
        public function field(string $fields): self { return $this; }
        public function findOrEmpty(): MemoryOrder { return new MemoryOrder(); }
    }
}
namespace {
    require dirname(__DIR__) . '/app/dict/order/RecycleOrderDict.php';
    require dirname(__DIR__) . '/app/dict/stat/RecycleStageDict.php';
    require dirname(__DIR__) . '/app/service/admin/stat/TaskService.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/CoreRecycleOrderEventService.php';
    use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
    use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
    use addon\hsx_recycle\app\model\order\RecycleOrder;
    use addon\hsx_recycle\app\service\admin\stat\TaskService;
    use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService as Capability;
    use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderEventService as OrderEvents;

    function event(string $name, array $payload): void {
        if ($name !== 'HsxMarketingFactRecorded') throw new \RuntimeException('unexpected external operation');
        $GLOBALS['facts'][] = $payload;
    }
    class FakeTaskService extends TaskService {
        public array $assignments = [];
        protected function getCurrentUserMenuKeys(): array { return ['recycle_order_payment_confirm']; }
        public function getAssignableUsers(string $stageKey): array { return [['uid' => 10, 'name' => '财务']]; }
        public function defaultAssignees(): array { return ['pay' => 10]; }
        public function assign(int $deviceId, string $stageKey, int $assigneeUid): bool {
            $this->assignments[] = [$deviceId, $stageKey, $assigneeUid];
            return true;
        }
    }
    $assertions = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions): void {
        if (!$condition) throw new \RuntimeException($message);
        $assertions++;
    };
    $throws = static function (callable $callback) use ($assert): void {
        try { $callback(); } catch (\Throwable $error) { $assert(str_contains($error->getMessage(), 'unknown payment owner'), 'unknown must fail before any write'); return; }
        throw new \RuntimeException('expected unknown ownership rejection');
    };
    Capability::$devices = [
        1 => ['device_id' => 1, 'owner' => 'local', 'pay_status' => 1],
        2 => ['device_id' => 2, 'owner' => 'self_erp', 'pay_status' => 1],
        3 => ['device_id' => 3, 'owner' => 'self_erp', 'pay_status' => 0],
        4 => ['device_id' => 4, 'owner' => 'unknown', 'pay_status' => 1],
        5 => ['device_id' => 5, 'owner' => 'local', 'pay_status' => 0],
        6 => ['device_id' => 6, 'owner' => 'local', 'pay_status' => 1],
    ];
    $task = new FakeTaskService();
    $assert(in_array('pay', $task->getMyStages(), true), 'local payment stage stays visible regardless of current site setting');
    $assert(in_array('pay', array_column($task->assignmentSettings(), 'stage_key'), true), 'local payment assignee configuration stays available');
    $assert(Capability::$calls === [], 'stage visibility and settings never query site-wide payment ownership');
    $assert($task->assignPreferredOrDefault(1, 'pay') && $task->assignments === [[1, 'pay', 10]], 'actual local device receives local task assignment');
    $assert($task->assignPreferredOrDefault(2, 'pay') && count($task->assignments) === 1, 'ERP-owned device avoids duplicate local automatic assignment');
    $assert(!$task->assignPreferredOrDefault(4, 'pay') && count($task->assignments) === 1, 'unknown device receives no payment assignment');
    $assert(Capability::$calls[0] === [1, 0, [1]], 'assignment check is scoped to actual device');
    $target = new \ReflectionMethod(TaskService::class, 'taskTarget');
    $target->setAccessible(true);
    $assert($target->invoke($task, 'pay', 7, 1, 'RC-7', 'IMEI-1')['plugin'] === 'hsx_recycle', 'local device notification targets local detail');
    $erpTarget = $target->invoke($task, 'pay', 7, 2, 'RC-7', 'IMEI-2');
    $assert($erpTarget['plugin'] === 'hsx_erp' && $erpTarget['params']['source_device_id'] === 2, 'ERP device notification targets scoped ERP payable');
    $throws(fn() => $target->invoke($task, 'pay', 7, 4, 'RC-7', 'IMEI-4'));
    $upsert = new \ReflectionMethod(TaskService::class, 'upsertAssignment');
    $upsert->setAccessible(true);
    $throws(fn() => $upsert->invoke($task, 4, 'pay', 10, '财务', 'claim', false));

    foreach (array_keys(Capability::$devices) as $id) {
        RecycleOrder::$devices[] = ['id' => $id, 'site_id' => 1, 'order_id' => 7, 'imei' => 'IMEI-' . $id,
            'status' => $id === 6 ? RecycleOrderDict::DEVICE_STATUS_RETURNED : RecycleOrderDict::DEVICE_STATUS_RECYCLED,
            'pay_amount' => 100, 'final_price' => 100, 'pay_status' => Capability::$devices[$id]['pay_status']];
    }
    $emit = new \ReflectionMethod(OrderEvents::class, 'emitMarketingFact');
    $emit->setAccessible(true);
    $GLOBALS['facts'] = [];
    $emit->invoke(null, ['order_id' => 7, 'site_id' => 1, 'source_plugin' => 'hsx_recycle'], false);
    $assert(array_column($GLOBALS['facts'], 'device_id') === [1, 2], 'only actually paid known owners emit; unpaid unknown and returned devices do not');
    $assert(array_column($GLOBALS['facts'], 'source_plugin') === ['hsx_recycle', 'hsx_erp'], 'mixed-order source is chosen per actual device owner');
    $originalIds = array_column($GLOBALS['facts'], 'event_id');
    $assert($originalIds === ['hsx_recycle:device_delivered:1:1', 'hsx_recycle:device_delivered:1:2'], 'existing stable device event IDs are preserved');
    $GLOBALS['facts'] = [];
    OrderEvents::marketingDeliveryFactAfter(['order_id' => 7, 'site_id' => 1, 'source_plugin' => 'hsx_erp']);
    $assert(array_column($GLOBALS['facts'], 'event_id') === $originalIds, 'ERP-last completion also covers earlier paid local devices with unchanged idempotency keys');
    $GLOBALS['facts'] = [];
    $emit->invoke(null, ['order_id' => 7, 'site_id' => 1, 'device_ids' => [2]], false);
    $assert(array_column($GLOBALS['facts'], 'device_id') === [2], 'explicit device set is never expanded to unrelated order devices');
    $assert(end(Capability::$calls) === [1, 7, [2]], 'marketing ownership inspection is order and device scoped');
    $GLOBALS['facts'] = [];
    Capability::$fail = true;
    OrderEvents::marketingDeliveryFactAfter(['order_id' => 7, 'site_id' => 1]);
    $assert($GLOBALS['facts'] === [], 'failed ownership lookup emits no marketing facts');
    $emit->invoke(null, ['order_id' => 7, 'site_id' => 1], true);
    $assert(count($GLOBALS['facts']) === 6 && $GLOBALS['facts'][0]['event_id'] === $originalIds[0] . ':reversed', 'existing reversal behavior and identifiers remain independent of current ownership lookup');
    echo "[PASS] recycle task and marketing ownership: {$assertions} assertions; in-memory only\n";
}
