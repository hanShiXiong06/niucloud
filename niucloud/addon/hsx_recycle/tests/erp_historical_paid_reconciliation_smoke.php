<?php
declare(strict_types=1);

// 执行真实监听器与纯校验，数据库和事件均为内存替身，不连接数据库、不执行付款。
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace think\facade {
    class MemoryPdo { public function inTransaction(): bool { return Db::$transaction; } }
    class MemoryConnection { public function getPdo(): MemoryPdo { return new MemoryPdo(); } }
    class MemoryCollection {
        public function __construct(private array $rows) {}
        public function toArray(): array { return $this->rows; }
    }
    class MemoryQuery {
        private array $filters = [];
        private bool $locked = false;
        private string $ordering = '';
        public function where(string $field, mixed $value): self { $this->filters[$field] = $value; return $this; }
        public function whereIn(string $field, array $values): self { $this->filters[$field] = $values; return $this; }
        public function field(string $fields): self { Db::$fields = $fields; return $this; }
        public function order(string $order): self { $this->ordering = $order; return $this; }
        public function lock(bool $lock): self { $this->locked = $lock; return $this; }
        public function select(): MemoryCollection {
            if (Db::$fail) throw new \RuntimeException('source database unavailable');
            if (!$this->locked || !Db::$transaction || $this->ordering !== 'id') throw new \RuntimeException('source rows must stay locked in ID order');
            if (!isset($this->filters['site_id'], $this->filters['id'])) throw new \RuntimeException('site and selected IDs are required');
            $rows = array_values(array_filter(Db::$rows, function (array $row): bool {
                return $row['site_id'] === $this->filters['site_id'] && in_array($row['id'], $this->filters['id'], true);
            }));
            usort($rows, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
            Db::$locks[] = ['site_id' => $this->filters['site_id'], 'ids' => array_column($rows, 'id')];
            return new MemoryCollection($rows);
        }
    }
    class Db {
        public static bool $transaction = true;
        public static bool $fail = false;
        public static array $rows = [];
        public static array $locks = [];
        public static string $fields = '';
        public static function connect(): MemoryConnection { return new MemoryConnection(); }
        public static function name(string $table): MemoryQuery {
            if ($table !== 'recycle_device') throw new \RuntimeException('historical reconciliation must not write payment or ownership tables');
            return new MemoryQuery();
        }
    }
}
namespace {
    require dirname(__DIR__) . '/app/service/core/recycle_order/RecyclePaymentOwnershipService.php';
    require dirname(__DIR__) . '/app/listener/erp/ErpHistoricalPaidReconciliation.php';
    require dirname(__DIR__) . '/app/listener/erp/ErpSourcePaymentGuardRequested.php';

    use addon\hsx_recycle\app\listener\erp\ErpSourcePaymentGuardRequested as Guard;
    use addon\hsx_recycle\app\service\core\recycle_order\RecyclePaymentOwnershipService as Ownership;
    use think\facade\Db;

    class MemoryOwnership extends Ownership {
        public static array $calls = [];
        public static int $payStatus = 0;
        public function claim(int $siteId, int $orderId = 0, array $deviceIds = [], string $expectedOwner = 'local'): array {
            self::$calls[] = [$siteId, $orderId, $deviceIds, $expectedOwner];
            return ['devices' => array_map(static fn(int $id): array => ['device_id' => $id, 'pay_status' => self::$payStatus], $deviceIds)];
        }
    }
    class TestGuard extends Guard {
        protected function ownershipService(): Ownership { return new MemoryOwnership(); }
    }
    function event(string $name, array $payload): array {
        if ($name !== 'RecycleErpPaymentOwnershipRequested') throw new \RuntimeException('no payment event may be emitted');
        if (Db::$locks === [] || !Db::$transaction) throw new \RuntimeException('ownership must be read while source locks remain held');
        $GLOBALS['events'][] = [$name, $payload];
        if ($GLOBALS['failEvent']) throw new \RuntimeException('ERP read unavailable');
        return $GLOBALS['responses'];
    }
    function source(int $id = 1, array $changes = []): array {
        return array_merge(['id' => $id, 'site_id' => 7, 'order_id' => 10, 'pay_status' => 1,
            'pay_amount' => '100.00', 'pay_time' => 100, 'final_price' => '100.00'], $changes);
    }
    function evidence(int $id = 1, array $changes = []): array {
        return array_merge(['has_asset' => true, 'has_payable' => true, 'ambiguous' => false,
            'asset_ids' => [100 + $id], 'paid_amount' => '0.00', 'remaining_amount' => '100.00'], $changes);
    }
    function ack(array $devices): array { return ['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => $devices]; }
    function payload(array $changes = []): array {
        return array_merge(['event_name' => 'erp.source_payment.guard_requested.v1', 'event_version' => 1,
            'source_plugin' => 'hsx_recycle', 'site_id' => 7, 'source_device_ids' => [1],
            'purpose' => 'historical_paid_reconciliation', 'device_amounts' => [['device_id' => 1, 'amount' => '100.00']],
            'prior_erp_paid_amounts' => [['device_id' => 1, 'amount' => '0.00']]], $changes);
    }
    function resetState(): void {
        Db::$transaction = true;
        Db::$fail = false;
        Db::$rows = [source()];
        Db::$locks = MemoryOwnership::$calls = $GLOBALS['events'] = [];
        Db::$fields = '';
        MemoryOwnership::$payStatus = 0;
        $GLOBALS['responses'] = [ack([1 => evidence()])];
        $GLOBALS['failEvent'] = false;
    }
    $count = 0;
    $failures = [];
    function check(bool $value, string $message): void {
        $GLOBALS['count']++;
        if (!$value) $GLOBALS['failures'][] = $message;
    }
    function rejects(callable $callback, string $message): void {
        try { $callback(); check(false, $message); }
        catch (\core\exception\CommonException | \RuntimeException $error) { check(true, $message); }
    }
    $guard = new TestGuard();
    resetState();
    $result = $guard->handle(payload());
    check($result['status'] === 'processed' && $result['source_device_ids'] === [1], 'paid local history can be mirrored');
    check(MemoryOwnership::$calls === [], 'historical reconciliation never claims ERP ownership');
    check(Db::$locks === [['site_id' => 7, 'ids' => [1]]] && Db::$transaction, 'site device lock survives inside caller transaction');
    check(Db::$fields === 'id,site_id,order_id,pay_status,pay_amount,pay_time,final_price', 'all source payment evidence is selected explicitly');
    check($GLOBALS['events'] === [['RecycleErpPaymentOwnershipRequested', ['site_id' => 7, 'device_ids' => [1]]]], 'only read-only ERP evidence event is called');

    foreach ([0, 2] as $status) {
        resetState();
        Db::$rows = [source(1, ['pay_status' => $status, 'pay_amount' => '40.00', 'pay_time' => 0])];
        check($guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => '40.00']]]))['status'] === 'processed', 'true partial source payment without timestamp is accepted');
        rejects(fn() => $guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => '40.01']]])), 'partial paid source amount is a strict cap');
    }
    resetState();
    Db::$rows = [source(1, ['pay_amount' => '0.00', 'pay_time' => 0, 'final_price' => '80.00'])];
    check($guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => '80.00']]]))['status'] === 'processed', 'legacy paid flag can confirm final price without payment time');
    rejects(fn() => $guard->handle(payload()), 'legacy paid final price is capped');
    foreach ([['pay_status' => 0, 'pay_amount' => 0, 'pay_time' => 100], ['pay_status' => 0, 'pay_amount' => 0, 'pay_time' => 0],
        ['pay_amount' => 0, 'final_price' => 0, 'initial_price' => 100], ['pay_amount' => 0, 'final_price' => null, 'initial_price' => 100],
        ['pay_amount' => -1], ['pay_status' => 3], ['pay_time' => -1], ['pay_amount' => null]] as $changes) {
        resetState();
        Db::$rows = [source(1, $changes)];
        rejects(fn() => $guard->handle(payload(['source_paid_amount' => 9999])), 'uncertain source facts cannot be replaced by inbound paid amount or initial price');
    }

    resetState();
    $GLOBALS['responses'] = [ack([1 => evidence(1, ['paid_amount' => '30.00', 'remaining_amount' => '70.00'])])];
    check($guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => '70.00']]]))['status'] === 'processed', 'ERP already reconciled amount is subtracted');
    rejects(fn() => $guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => '70.01']]])), 'ERP prior amount cannot be reconciled twice');
    resetState();
    rejects(fn() => $guard->handle(payload(['prior_erp_paid_amounts' => [['device_id' => 1, 'amount' => '100.00']]])), 'RR stale ERP read of zero cannot bypass locked current prior amount of 100');
    check($guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => '40.00']],
        'prior_erp_paid_amounts' => [['device_id' => 1, 'amount' => '60.00']]]))['status'] === 'processed', 'locked current prior amount preserves exact unreconciled remainder');
    $GLOBALS['responses'] = [ack([1 => evidence(1, ['paid_amount' => '80.00'])])];
    rejects(fn() => $guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => '40.00']],
        'prior_erp_paid_amounts' => [['device_id' => 1, 'amount' => '60.00']]])), 'a smaller locked snapshot never overrides larger read-only ERP paid evidence');

    $badAcks = [[], [ack([])], [ack([1 => evidence()]), ack([1 => evidence()])],
        [array_merge(ack([1 => evidence()]), ['status' => 'failed'])], [array_merge(ack([1 => evidence()]), ['error' => true])],
        [array_merge(ack([1 => evidence()]), ['consumer' => 'other'])], [ack([1 => evidence(), 2 => evidence(2)])]];
    foreach ([['ambiguous' => true], ['has_asset' => false], ['has_payable' => false], ['has_asset' => 1], ['ambiguous' => null],
        ['asset_ids' => []], ['asset_ids' => [1, 2]], ['asset_ids' => ['1broken']], ['paid_amount' => null], ['paid_amount' => -1],
        ['paid_amount' => INF], ['remaining_amount' => null], ['remaining_amount' => '99.99'], ['paid_amount' => '101.00']] as $changes) {
        $badAcks[] = [ack([1 => evidence(1, $changes)])];
    }
    foreach ($badAcks as $badResponse) {
        resetState();
        $GLOBALS['responses'] = $badResponse;
        rejects(fn() => $guard->handle(payload()), 'missing, failed, malformed or ambiguous ERP evidence is fail closed');
        check(MemoryOwnership::$calls === [], 'failed historical evidence does not transfer payment ownership');
    }

    foreach ([null, [], [['device_id' => 2, 'amount' => 100]], [['device_id' => '1abc', 'amount' => 100]],
        [['device_id' => 1.0, 'amount' => 100]], [['device_id' => true, 'amount' => 100]], [['device_id' => '01', 'amount' => 100]],
        [['device_id' => 1, 'amount' => 100], ['device_id' => 1, 'amount' => 1]]] as $rows) {
        resetState();
        rejects(fn() => $guard->handle(payload(['device_amounts' => $rows])), 'requested amount rows require exact complete unambiguous IDs');
        rejects(fn() => $guard->handle(payload(['prior_erp_paid_amounts' => $rows])), 'locked prior rows require exact complete unambiguous IDs');
    }
    foreach ([0, -1, true, null, '1e2', 'NaN', INF, NAN, '1.001', 1.001, '9999999999999999999999'] as $amount) {
        resetState();
        rejects(fn() => $guard->handle(payload(['device_amounts' => [['device_id' => 1, 'amount' => $amount]]])), 'invalid requested monetary values fail without truncation');
    }
    resetState();
    rejects(fn() => $guard->handle(payload(['source_device_ids' => [1, 1]])), 'repeated source selector is rejected for historical reconciliation');
    Db::$transaction = false;
    rejects(fn() => $guard->handle(payload()), 'historical lock cannot run outside financial transaction');
    check(Db::$locks === [] && $GLOBALS['events'] === [], 'transaction check fails before source or ERP reads');
    resetState();
    Db::$rows = [source(1, ['site_id' => 8])];
    rejects(fn() => $guard->handle(payload()), 'other site source payment cannot authorize reconciliation');
    resetState();
    Db::$rows = [];
    rejects(fn() => $guard->handle(payload()), 'missing source row blocks');
    resetState();
    Db::$fail = true;
    rejects(fn() => $guard->handle(payload()), 'source database errors propagate');
    check($GLOBALS['events'] === [], 'source read failure does not query ERP');
    resetState();
    $GLOBALS['failEvent'] = true;
    rejects(fn() => $guard->handle(payload()), 'ERP read errors propagate');

    resetState();
    Db::$rows = [source(2, ['pay_amount' => '20.00']), source(1, ['pay_amount' => '80.00'])];
    $GLOBALS['responses'] = [ack([2 => evidence(2), 1 => evidence()])];
    $two = payload(['source_device_ids' => [2, 1], 'device_amounts' => [['device_id' => 2, 'amount' => 20], ['device_id' => 1, 'amount' => 80]],
        'prior_erp_paid_amounts' => [['device_id' => 1, 'amount' => 0], ['device_id' => 2, 'amount' => 0]]]);
    check($guard->handle($two)['source_device_ids'] === [1, 2], 'batch device IDs are normalized and locked in ascending order');
    $two['device_amounts'] = [['device_id' => 1, 'amount' => 20], ['device_id' => 2, 'amount' => 80]];
    rejects(fn() => $guard->handle($two), 'one device cannot spend another device historical paid balance');
    $two['device_amounts'] = [['device_id' => 1, 'amount' => 80], ['device_id' => 2, 'amount' => 20]];
    $GLOBALS['responses'] = [ack([1 => evidence(), 2 => evidence(2, ['asset_ids' => [101]])])];
    rejects(fn() => $guard->handle($two), 'same ERP asset cannot fund two source devices');

    resetState();
    $ordinary = payload(['purpose' => 'payment']);
    unset($ordinary['device_amounts'], $ordinary['prior_erp_paid_amounts']);
    check($guard->handle($ordinary)['status'] === 'processed', 'explicit ordinary payment does not require historical payload');
    check(MemoryOwnership::$calls === [[7, 0, [1], 'self_erp']], 'ordinary payment still claims exact ERP ownership');
    unset($ordinary['purpose']);
    check($guard->handle($ordinary)['status'] === 'processed', 'absent purpose preserves ordinary payment');
    MemoryOwnership::$payStatus = 1;
    rejects(fn() => $guard->handle($ordinary), 'ordinary payment still rejects already paid source');
    rejects(fn() => $guard->handle(payload(['purpose' => 'anything_else'])), 'unknown purpose cannot bypass ordinary or historical guard');

    if ($failures !== []) {
        fwrite(STDERR, implode("\n", $failures) . "\n");
        exit(1);
    }
    echo "PASS {$count} historical paid reconciliation assertions (no database or payment)\n";
}
