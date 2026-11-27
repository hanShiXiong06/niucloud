<?php
declare(strict_types=1);

// 内存替身覆盖领取/完成的条件更新，不加载框架、不连接数据库、不调用任何付款服务。
namespace core\base {
    class BaseAdminService { protected int $site_id = 1; protected int $uid = 0; protected string $username = 'test'; }
}

namespace addon\hsx_erp\app\model {
    class MemoryRow {
        public function __construct(private array $data) {}
        public function isEmpty(): bool { return $this->data === []; }
        public function toArray(): array { return $this->data; }
        public function __get(string $key): mixed { return $this->data[$key] ?? null; }
    }
    class MemoryCollection {
        public function __construct(private array $data) {}
        public function toArray(): array { return $this->data; }
    }
    class MemoryQuery {
        private array $conditions = [];
        private int $rowLimit = PHP_INT_MAX;
        public function __construct(private string $model) {}
        public function where(mixed $field, mixed $operator = null, mixed $value = null): self {
            if (is_array($field)) $this->conditions = array_merge($this->conditions, $field);
            else $this->conditions[] = [$field, $operator, $value];
            return $this;
        }
        public function whereIn(string $field, array $values): self { return $this->where($field, 'in', $values); }
        public function order(string $order): self { return $this; }
        public function limit(int $limit): self { $this->rowLimit = $limit; return $this; }
        private function matches(array $row): bool {
            foreach ($this->conditions as [$field, $operator, $value]) {
                $actual = $row[$field] ?? null;
                $match = match ($operator) {
                    '=' => $actual === $value,
                    '>' => $actual > $value,
                    '<=' => $actual <= $value,
                    'in' => in_array($actual, $value, true),
                    default => throw new \RuntimeException('Unsupported test query'),
                };
                if (!$match) return false;
            }
            return true;
        }
        public function select(): MemoryCollection {
            $rows = $this->model::$rows;
            ksort($rows);
            return new MemoryCollection(array_slice(array_values(array_filter($rows, fn(array $row): bool => $this->matches($row))), 0, $this->rowLimit));
        }
        public function findOrEmpty(): MemoryRow { return new MemoryRow($this->select()->toArray()[0] ?? []); }
        public function update(array $values): int {
            if (is_callable($GLOBALS['beforeUpdate'] ?? null)) {
                $hook = $GLOBALS['beforeUpdate'];
                $GLOBALS['beforeUpdate'] = null;
                $hook($values);
            }
            $count = 0;
            foreach ($this->model::$rows as $id => $row) {
                if (!$this->matches($row)) continue;
                $this->model::$rows[$id] = array_merge($row, $values);
                $count++;
            }
            return $count;
        }
    }
    class ErpOutboxEvent {
        public static array $rows = [];
        public static function where(mixed ...$args): MemoryQuery { return (new MemoryQuery(self::class))->where(...$args); }
        public static function whereIn(string $field, array $values): MemoryQuery { return (new MemoryQuery(self::class))->whereIn($field, $values); }
    }
    class ErpInboxEvent {
        public static array $rows = [];
        public static function where(mixed ...$args): MemoryQuery { return (new MemoryQuery(self::class))->where(...$args); }
    }
}

namespace addon\hsx_erp\app\listener {
    class ErpDeviceInboundRequested {
        public static function forSite(int $siteId): self { return new self(); }
        public function handle(array $request): array { $GLOBALS['inboxCalls'][] = $request; return ['status' => 'processed']; }
    }
    class ErpSaleCreatedRequested extends ErpDeviceInboundRequested {}
}
namespace addon\hsx_erp\app\service\admin {
    class ErpExternalSaleRecordedService { public const EVENT_NAME = 'erp.external_sale.recorded_requested.v1'; }
    class ErpExternalSaleRefundedService { public const EVENT_NAME = 'erp.external_sale.refunded_requested.v1'; }
}

namespace {
    use addon\hsx_erp\app\model\ErpOutboxEvent;
    use addon\hsx_erp\app\model\ErpInboxEvent;
    use addon\hsx_erp\app\service\admin\ErpIntegrationService;
    use addon\hsx_erp\app\support\ErpDomainEvent;

    require dirname(__DIR__) . '/app/support/ErpDomainEvent.php';
    require dirname(__DIR__) . '/app/service/admin/ErpIntegrationService.php';

    function event(string $name, array $payload): array {
        if ($name !== 'ErpDomainEvent') throw new \RuntimeException('Test must only publish business fact notifications');
        $GLOBALS['deliveryCalls'][] = $payload;
        return ($GLOBALS['deliveryHandler'])($payload);
    }
    $assertions = 0;
    function check(bool $condition, string $message): void {
        $GLOBALS['assertions']++;
        if (!$condition) throw new \RuntimeException($message);
    }
    function resetMemory(): void {
        ErpOutboxEvent::$rows = [];
        ErpInboxEvent::$rows = [];
        $GLOBALS['deliveryCalls'] = [];
        $GLOBALS['inboxCalls'] = [];
        $GLOBALS['beforeUpdate'] = null;
        $GLOBALS['deliveryHandler'] = static fn(array $event): array => [['consumer' => 'hsx_recycle', 'status' => 'processed']];
    }
    function seed(int $id, string $status = 'pending', int $attempts = 0, int $age = 601, int $siteId = 1): array {
        $fact = ErpDomainEvent::create($siteId, 'erp.settlement.completed.v1', 'EV-' . $id, 'settlement', $id,
            ['name' => 'test'], ['plugin' => 'hsx_erp'], ['settlement_no' => 'ST-' . $id, 'amount' => 100], time() - 1000);
        $stored = $fact;
        $stored['_delivery'] = ['required_consumers' => ['hsx_recycle'], 'attempts' => $attempts, 'results' => [], 'last_error' => ''];
        ErpOutboxEvent::$rows[$id] = ['id' => $id, 'site_id' => $siteId, 'event_id' => 'EV-' . $id,
            'event_name' => $fact['event_name'], 'status' => $status, 'update_at' => time() - $age,
            'payload_json' => json_encode($stored, JSON_UNESCAPED_UNICODE)];
        return $fact;
    }
    function stored(int $id): array { return json_decode(ErpOutboxEvent::$rows[$id]['payload_json'], true); }
    $service = ErpIntegrationService::forSite(1);

    resetMemory();
    $original = seed(1);
    check($service->dispatchDomainEvent(1)['ok'], 'pending fact delivered');
    check(ErpOutboxEvent::$rows[1]['status'] === 'done', 'successful required ACK completes delivery');
    check($GLOBALS['deliveryCalls'] === [$original], 'original event id and fact snapshot are unchanged; delivery metadata is private');
    check(stored(1)['_delivery']['attempts'] === 1 && !isset(stored(1)['_delivery']['lease_token']), 'attempt recorded and completed lease cleared');
    check(!empty($service->dispatchDomainEvent(1)['duplicate']) && count($GLOBALS['deliveryCalls']) === 1, 'done notification is not replayed');

    resetMemory();
    seed(1);
    $GLOBALS['deliveryHandler'] = static fn(array $event): array => [['consumer' => 'hsx_recycle', 'status' => 'skipped']];
    check(!$service->dispatchDomainEvent(1)['ok'] && ErpOutboxEvent::$rows[1]['status'] === 'failed', 'unrelated skipped consumer cannot satisfy required ACK');
    check(str_contains(stored(1)['_delivery']['last_error'], 'hsx_recycle'), 'missing ACK remains visible');
    $GLOBALS['deliveryHandler'] = static fn(array $event): array => [['consumer' => 'hsx_recycle', 'status' => 'failed', 'message' => 'mirror unavailable']];
    check(!$service->dispatchDomainEvent(1)['ok'] && stored(1)['_delivery']['last_error'] === 'mirror unavailable', 'failed status without error boolean still fails');

    resetMemory();
    seed(1, 'processing', 2, 100);
    check(!empty($service->dispatchDomainEvent(1)['busy']) && $GLOBALS['deliveryCalls'] === [], 'unexpired lease cannot be claimed');
    seed(1, 'processing', 2, 601);
    check($service->retryPendingDomainEvents(1, 1)['done'] === 1, 'expired processing lease is recovered by scheduler');
    check(stored(1)['_delivery']['attempts'] === 3 && $GLOBALS['deliveryCalls'][0]['event_id'] === 'EV-1', 'recovery preserves event id and increments attempt');

    resetMemory();
    seed(1);
    $GLOBALS['deliveryHandler'] = static function (array $event) use ($service): array {
        check(!empty($service->dispatchDomainEvent(1)['busy']), 'reentrant dispatch sees active lease');
        return [['consumer' => 'hsx_recycle', 'status' => 'processed']];
    };
    check($service->dispatchDomainEvent(1)['ok'] && count($GLOBALS['deliveryCalls']) === 1, 'only one active dispatch executes consumer');

    resetMemory();
    seed(1);
    $GLOBALS['beforeUpdate'] = static function (array $values) use ($service): void {
        check($values['status'] === 'processing', 'race occurs before claim');
        check($service->dispatchDomainEvent(1)['ok'], 'concurrent worker wins claim');
    };
    check(!empty($service->dispatchDomainEvent(1)['busy']), 'stale reader loses compare-and-swap claim');
    check(count($GLOBALS['deliveryCalls']) === 1 && ErpOutboxEvent::$rows[1]['status'] === 'done', 'same-second claim race does not run consumer twice');

    resetMemory();
    seed(1);
    $GLOBALS['deliveryHandler'] = static function (array $event): array {
        $new = stored(1);
        $new['_delivery']['lease_token'] = 'new-owner';
        $new['_delivery']['last_error'] = 'new owner result';
        ErpOutboxEvent::$rows[1]['payload_json'] = json_encode($new, JSON_UNESCAPED_UNICODE);
        return [['consumer' => 'hsx_recycle', 'status' => 'processed']];
    };
    check(!empty($service->dispatchDomainEvent(1)['stale']), 'old lease cannot mark a new lease done');
    check(stored(1)['_delivery']['last_error'] === 'new owner result', 'new lease result is preserved');

    resetMemory();
    seed(1);
    $GLOBALS['deliveryHandler'] = static function (array $event): array {
        $new = stored(1);
        $new['_delivery']['lease_token'] = 'replacement';
        ErpOutboxEvent::$rows[1]['payload_json'] = json_encode($new, JSON_UNESCAPED_UNICODE);
        throw new \RuntimeException('late worker error');
    };
    check(!empty($service->dispatchDomainEvent(1)['stale']), 'late worker failure also cannot overwrite replacement lease');
    check(ErpOutboxEvent::$rows[1]['status'] === 'processing' && stored(1)['_delivery']['lease_token'] === 'replacement', 'replacement remains recoverable after stale failure');

    resetMemory();
    seed(1, 'failed', 10);
    seed(2, 'processing', 10);
    seed(3, 'pending', 0);
    ErpOutboxEvent::$rows[3]['payload_json'] = '{broken';
    seed(4, 'processing', 0, 100);
    seed(5, 'pending');
    seed(6, 'pending', 0, 601, 2);
    $summary = $service->retryPendingDomainEvents(1, 1);
    check($summary['done'] === 1 && $summary['dead'] === 3 && $summary['scanned'] === 5, 'dead/corrupt/leased prefix does not starve later eligible fact');
    check(ErpOutboxEvent::$rows[1]['status'] === 'failed' && ErpOutboxEvent::$rows[2]['status'] === 'failed', 'exhausted facts remain failed for manual diagnosis');
    check(ErpOutboxEvent::$rows[3]['status'] === 'failed', 'corrupt pending fact is visibly failed instead of left pending forever');
    check(ErpOutboxEvent::$rows[5]['status'] === 'done' && ErpOutboxEvent::$rows[6]['status'] === 'pending', 'retry respects selected site');
    check(count($GLOBALS['deliveryCalls']) === 1, 'automatic retry never executes exhausted records');
    check($service->dispatchDomainEvent(1)['ok'], 'explicit manual retry remains available after automatic retry limit');

    resetMemory();
    foreach ([1 => 10, 2 => 10, 3 => 0] as $id => $attempts) {
        ErpInboxEvent::$rows[$id] = ['id' => $id, 'site_id' => 1, 'status' => 'failed', 'update_at' => time() - 100,
            'event_name' => 'recycle.device.inbound_requested',
            'payload_json' => json_encode(['request' => ['event_id' => 'IN-' . $id], '_retry' => ['attempts' => $attempts]])];
    }
    $inboxSummary = $service->retryFailedExternalRequests(1, 1);
    check($inboxSummary['done'] === 1 && $inboxSummary['dead'] === 2, 'exhausted inbox prefix also cannot starve recoverable existing requests');
    check($GLOBALS['inboxCalls'] === [['event_id' => 'IN-3']], 'inbox compensation preserves original request and skips dead records');
    echo '[PASS] domain event delivery reliability: ' . $assertions . " assertions; in-memory only\n";
}
