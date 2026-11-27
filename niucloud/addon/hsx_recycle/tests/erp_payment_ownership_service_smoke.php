<?php
declare(strict_types=1);

// 执行真实归属/能力/财务桥/来源付款闸门，仅替换 DB、配置和事件边界；没有真实数据库或付款。
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace think\facade {
    class MemoryCollection {
        public function __construct(private array $rows) {}
        public function toArray(): array { return $this->rows; }
    }
    class MemoryQuery {
        private array $filters = [];
        private bool $locked = false;
        public function __construct(private string $table) {}
        public function where(mixed $field, mixed $operator = null, mixed $value = null): self {
            if (is_array($field)) { foreach ($field as $condition) $this->filters[] = $condition; }
            else $this->filters[] = func_num_args() === 2 ? [$field, '=', $operator] : [$field, $operator, $value];
            return $this;
        }
        public function whereIn(string $field, array $values): self { return $this->where($field, 'in', $values); }
        public function order(string $order): self { return $this; }
        public function lock(bool $lock): self { $this->locked = $lock; return $this; }
        private function rows(): array {
            if (Db::$failRead) throw new \RuntimeException('database unavailable');
            $rows = array_values(array_filter(Db::$tables[$this->table] ?? [], function (array $row): bool {
                foreach ($this->filters as [$field, $operator, $value]) {
                    $actual = $row[$field] ?? null;
                    if ($operator === 'in' ? !in_array($actual, $value, true) : $actual != $value) return false;
                }
                return true;
            }));
            usort($rows, static fn(array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);
            if ($this->locked) {
                if (Db::$depth <= 0) throw new \RuntimeException('locking read requires test transaction');
                Db::$locks[] = ['table' => $this->table, 'ids' => array_column($rows, 'id'), 'depth' => Db::$depth];
            }
            return $rows;
        }
        public function find(): array { return $this->rows()[0] ?? []; }
        public function select(): MemoryCollection { return new MemoryCollection($this->rows()); }
        public function insert(array $row): int {
            if ($this->table !== 'recycle_device_log' || Db::$depth <= 0) throw new \RuntimeException('only owner log inserts inside transaction are permitted');
            $id = count(Db::$tables[$this->table] ?? []) + 1;
            $row['id'] = $id;
            Db::$tables[$this->table][] = $row;
            Db::$writes[] = $row;
            return 1;
        }
    }
    class Db {
        public static array $tables = [];
        public static array $locks = [];
        public static array $writes = [];
        public static bool $failRead = false;
        public static int $depth = 0;
        public static function name(string $table): MemoryQuery { return new MemoryQuery($table); }
        public static function transaction(callable $callback): mixed {
            $before = self::$tables;
            self::$depth++;
            try { return $callback(); }
            catch (\Throwable $e) { self::$tables = $before; throw $e; }
            finally { self::$depth--; }
        }
    }
    class Log {
        public static array $messages = [];
        public static function __callStatic(string $method, array $args): void { self::$messages[] = [$method, $args]; }
    }
}
namespace addon\hsx_recycle\app\service\core\recycle_order {
    class RecycleErpIntegrationService {
        public static array $configs = [];
        public static bool $fail = false;
        public function history(int $siteId): array {
            if (self::$fail) throw new \RuntimeException('configuration unavailable');
            return self::$configs[$siteId] ?? throw new \RuntimeException('site configuration unavailable');
        }
        public function get(int $siteId): array { return $this->history($siteId) + ['message' => 'test']; }
        public function isInstalled(int $siteId): bool { return $this->history($siteId)['installed']; }
    }
}
namespace {
    require dirname(__DIR__) . '/app/support/RecycleErpOwnershipPolicy.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/RecyclePaymentOwnershipService.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/RecycleErpCapabilityService.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/RecycleErpFinanceBridgeService.php';
    require dirname(__DIR__) . '/app/listener/erp/ErpSourcePaymentGuardRequested.php';
    use think\facade\Db;
    use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpIntegrationService as Config;
    use addon\hsx_recycle\app\service\core\recycle_order\RecyclePaymentOwnershipService as Ownership;
    use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService as Capability;
    use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpFinanceBridgeService as Bridge;
    use addon\hsx_recycle\app\listener\erp\ErpSourcePaymentGuardRequested as Guard;

    function event(string $name, array $payload): array {
        $GLOBALS['events'][] = [$name, $payload];
        if (isset($GLOBALS['eventOverrides'][$name])) return ($GLOBALS['eventOverrides'][$name])($payload);
        if ($name === 'RecycleErpPaymentOwnershipRequested') {
            $devices = [];
            foreach ($payload['device_ids'] as $id) $devices[$id] = $GLOBALS['evidence'][$id] ?? evidence();
            return [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => $devices]];
        }
        if ($name === 'ErpCapitalAccountOptionsRequested') return [['consumer' => 'hsx_erp', 'status' => 'processed', 'list' => [['id' => 1]]]];
        if ($name === 'ErpSourcePayableSettlementRequested') return [['consumer' => 'hsx_erp', 'status' => 'processed', 'settlements' => []]];
        throw new \RuntimeException('unexpected event: ' . $name);
    }
    function evidence(array $changes = []): array {
        if (($changes['has_asset'] ?? false) === true && !array_key_exists('asset_ids', $changes)) {
            $changes['asset_ids'] = [88];
        }
        return array_merge(['has_asset' => false, 'ambiguous' => false, 'asset_ids' => [], 'has_payable' => false,
            'paid_amount' => 0.0, 'remaining_amount' => 0.0], $changes);
    }
    function device(int $id, int $site = 1, int $order = 10, int $created = 100): array {
        return ['id' => $id, 'site_id' => $site, 'order_id' => $order, 'create_at' => $created,
            'status' => 5, 'pay_status' => 0, 'pay_amount' => 0.0, 'pay_time' => 0, 'downstream_erp_asset_id' => 0];
    }
    function resetState(): void {
        Db::$tables = ['recycle_order' => [['id' => 10, 'site_id' => 1], ['id' => 11, 'site_id' => 1], ['id' => 20, 'site_id' => 2]],
            'recycle_device' => [device(1), device(2, 1, 10, 250), device(3, 1, 10, 350), device(4, 1, 11), device(5, 2, 20)],
            'recycle_device_log' => []];
        Db::$locks = Db::$writes = [];
        Db::$failRead = false;
        Db::$depth = 0;
        Config::$fail = false;
        Config::$configs = [1 => ['mode' => 'local', 'initial_mode' => 'local', 'configured' => true, 'installed' => true,
            'history' => [['at' => 200, 'mode' => 'self_erp'], ['at' => 300, 'mode' => 'local']]],
            2 => ['mode' => 'local', 'initial_mode' => 'local', 'configured' => true, 'installed' => false, 'history' => []]];
        $GLOBALS['events'] = $GLOBALS['eventOverrides'] = $GLOBALS['evidence'] = [];
    }
    function rowChange(int $id, array $changes): void {
        foreach (Db::$tables['recycle_device'] as &$row) if ($row['id'] === $id) $row = array_merge($row, $changes);
        unset($row);
    }
    function ownerLog(int $id, string $owner, int $site = 1): void {
        Db::$tables['recycle_device_log'][] = ['id' => count(Db::$tables['recycle_device_log']) + 1,
            'site_id' => $site, 'device_id' => $id, 'operation_type' => $owner === 'local' ? Ownership::LOCAL_LOG : Ownership::ERP_LOG];
    }
    $count = 0;
    $failures = [];
    function check(bool $condition, string $message): void {
        $GLOBALS['count']++;
        if (!$condition) $GLOBALS['failures'][] = $message;
    }
    function rejects(callable $callback, string $message): void {
        try { $callback(); check(false, $message); }
        catch (\Throwable $error) { check($error instanceof \core\exception\CommonException || $error instanceof \RuntimeException, $message . ': unexpected exception'); }
    }
    $service = new Ownership();
    $capability = new Capability();
    $bridge = new Bridge();
    $guard = new Guard();

    resetState();
    check(Ownership::deviceIds(['3', 1, '1']) === [1, 3], 'device selection is strictly validated, unique and sorted');
    foreach ([[0], ['01'], [1.0], ['1abc'], [-1], [true], [[]]] as $bad) rejects(fn() => Ownership::deviceIds($bad), 'invalid device selector must fail');
    check($service->inspect(1, 10)['owner'] === 'mixed', 'whole order detects mixed ownership across both switch directions');
    check($service->inspect(1, 10, [1])['owner'] === 'local', 'device before ERP enable keeps local');
    check($service->inspect(1, 10, [2])['owner'] === 'self_erp', 'device created while ERP enabled remains ERP after disable');
    check($service->inspect(1, 10, [3])['owner'] === 'local', 'new device after disable returns to local');
    Config::$configs[1]['history'][] = ['at' => 400, 'mode' => 'self_erp'];
    Config::$configs[1]['mode'] = 'self_erp';
    check($service->inspect(1, 10, [3])['owner'] === 'local', 're-enable does not move older local device');
    rowChange(3, ['create_at' => 450]);
    check($service->inspect(1, 10, [3])['owner'] === 'self_erp', 'new device after re-enable becomes ERP');

    resetState();
    ownerLog(1, 'self_erp');
    ownerLog(2, 'local');
    check($service->inspect(1, 10, [1])['owner'] === 'self_erp', 'recorded ERP owner survives closed setting');
    check($service->inspect(1, 10, [2])['owner'] === 'local', 'recorded local owner survives ERP-enabled creation window');
    $GLOBALS['evidence'][2] = evidence(['has_asset' => true, 'asset_ids' => [88]]);
    check($service->inspect(1, 10, [2])['owner'] === 'unknown', 'actual ERP record conflicting with local snapshot requires reconciliation');
    ownerLog(1, 'local');
    check($service->inspect(1, 10, [1])['owner'] === 'unknown', 'opposite historical owner logs cannot latest-win');
    resetState();
    ownerLog(1, 'self_erp', 2);
    check($service->inspect(1, 10, [1])['owner'] === 'local', 'another site owner log is ignored');
    $GLOBALS['evidence'][1] = evidence(['has_asset' => true]);
    check($service->inspect(1, 10, [1])['owner'] === 'self_erp', 'actual ERP asset has priority over current local config');
    resetState();
    rowChange(1, ['downstream_erp_asset_id' => 88]);
    check($service->inspect(1, 10, [1])['owner'] === 'self_erp', 'existing local ERP asset mirror is durable ownership evidence');
    rowChange(1, ['downstream_erp_asset_id' => 0, 'create_at' => 0]);
    check($service->inspect(1, 10, [1])['owner'] === 'unknown', 'missing creation time with switch history is ambiguous');
    Config::$configs[1] = ['mode' => 'self_erp', 'configured' => false, 'installed' => true];
    check($service->inspect(1, 10, [1])['owner'] === 'self_erp', 'unconfigured legacy mode remains compatible');

    $ackCases = [
        'missing ACK' => [],
        'unrelated ACK' => [['consumer' => 'other', 'status' => 'processed', 'devices' => [1 => evidence()]]],
        'failed ACK' => [['consumer' => 'hsx_erp', 'status' => 'failed', 'devices' => [1 => evidence()]]],
        'duplicate ACK' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence()]], ['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence()]]],
        'missing selected device' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => []]],
        'missing has_asset' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => []]]],
        'error flag overrides processed' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'error' => true, 'devices' => [1 => evidence()]]],
        'null has_asset is not no ERP record' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['has_asset' => null])]]],
        'malformed has_asset is not no ERP record' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['has_asset' => 'bad'])]]],
        'asset IDs contradict absent asset' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['asset_ids' => [88]])]]],
        'present asset has no ID' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['has_asset' => true, 'asset_ids' => []])]]],
        'duplicate asset IDs' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['has_asset' => true, 'asset_ids' => [88, 88]])]]],
        'malformed asset ID' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['has_asset' => true, 'asset_ids' => ['88bad']])]]],
        'absent payable contradicts paid amount' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['paid_amount' => 10.0])]]],
        'absent payable contradicts remaining amount' => [['consumer' => 'hsx_erp', 'status' => 'processed', 'devices' => [1 => evidence(['remaining_amount' => 10.0])]]],
    ];
    foreach ($ackCases as $name => $response) {
        resetState();
        $GLOBALS['eventOverrides']['RecycleErpPaymentOwnershipRequested'] = static fn(array $event): array => $response;
        check($service->inspect(1, 10, [1])['owner'] === 'unknown', $name . ' must fail closed');
        rejects(fn() => $service->claim(1, 10, [1], 'local'), $name . ' cannot claim local payment');
        check(Db::$tables['recycle_device_log'] === [], $name . ' cannot leave owner writes');
    }
    resetState();
    $GLOBALS['eventOverrides']['RecycleErpPaymentOwnershipRequested'] = static function (array $event): array { throw new \RuntimeException('event transport unavailable'); };
    check($service->inspect(1, 10, [1])['owner'] === 'unknown', 'ownership event exception is unknown');
    Config::$fail = true;
    rejects(fn() => $service->inspect(1, 10, [1]), 'config lookup failure must propagate');
    Config::$fail = false;
    Db::$failRead = true;
    rejects(fn() => $service->inspect(1, 10, [1]), 'database lookup failure must propagate');

    resetState();
    rejects(fn() => $service->inspect(1, 20, [5]), 'foreign site order rejected');
    rejects(fn() => $service->inspect(1, 10, [5]), 'foreign site device rejected');
    rejects(fn() => $service->inspect(1, 10, [4]), 'same-site other-order device rejected');
    rejects(fn() => $service->inspect(1, 10, [999]), 'missing device rejected');
    rejects(fn() => $service->inspect(0, 10, [1]), 'missing site rejected');
    rejects(fn() => $service->inspect(1), 'missing business context rejected');
    check(Db::$writes === [], 'read-only inspect performs no writes');

    resetState();
    rowChange(1, ['pay_amount' => 10]);
    check($service->inspect(1, 10, [1])['owner'] === 'unknown', 'unpaid flag with existing amount is contradictory');
    rowChange(1, ['pay_amount' => 0, 'pay_time' => 50]);
    check($service->inspect(1, 10, [1])['owner'] === 'unknown', 'unpaid flag with existing payment time is contradictory');
    rowChange(1, ['pay_status' => 1]);
    $GLOBALS['evidence'][1] = evidence(['has_asset' => true, 'has_payable' => true, 'remaining_amount' => 100]);
    check($service->inspect(1, 10, [1])['owner'] === 'unknown', 'local paid completion and open ERP payable requires reconciliation');
    rowChange(1, ['pay_status' => 0, 'pay_amount' => 10]);
    $GLOBALS['evidence'][1] = evidence(['has_asset' => true, 'has_payable' => true, 'paid_amount' => 10, 'remaining_amount' => 90]);
    check($service->inspect(1, 10, [1])['owner'] === 'self_erp', 'known ERP partial payment remains ERP responsibility');
    rowChange(1, ['pay_amount' => 100]);
    check($service->inspect(1, 10, [1])['owner'] === 'unknown', 'mismatched existing source payment amount cannot be explained by any positive ERP payment');
    rejects(fn() => $service->claim(1, 10, [1], 'self_erp'), 'mismatched paid amount blocks further ERP ownership claim for payment');

    resetState();
    $claimed = $service->claim(1, 10, [3, 1, 1], 'local');
    check($claimed['owner'] === 'local' && count(Db::$tables['recycle_device_log']) === 2, 'claim writes one owner fact per selected local device');
    $service->claim(1, 10, [1, 3], 'local');
    check(count(Db::$tables['recycle_device_log']) === 2, 'repeated claim is idempotent');
    check(Db::$locks[0]['table'] === 'recycle_device' && Db::$locks[0]['ids'] === [1, 3] && Db::$locks[0]['depth'] > 0, 'claim locks selected device rows in sorted order');
    check(Db::$locks[1]['table'] === 'recycle_device_log', 'claim reads owner logs under transaction lock');
    check(array_column(Db::$tables['recycle_device_log'], 'operation_type') === [Ownership::LOCAL_LOG, Ownership::LOCAL_LOG], 'claim only writes ownership audit facts');
    rejects(fn() => $service->claim(1, 10, [1, 2], 'local'), 'mixed selection cannot claim whole-order local payment');
    rejects(fn() => $service->claim(1, 10, [1], 'self_erp'), 'claim cannot transfer frozen local responsibility');
    rejects(fn() => $service->claim(1, 10, [1], 'unknown'), 'invalid expected owner rejected');
    resetState();
    Config::$configs[1]['installed'] = false;
    check($service->inspect(1, 10, [2])['owner'] === 'self_erp', 'uninstalled ERP does not release historical ERP owner');
    rejects(fn() => $service->claim(1, 10, [2], 'self_erp'), 'ERP claim requires installed receiver');
    check($GLOBALS['events'] === [], 'uninstalled mode does not pretend to query ERP');
    check($service->inspect(2, 20, [5])['owner'] === 'local', 'standalone site remains functional');

    resetState();
    check(!$capability->paymentCapability(1)['local_allowed'], 'global capability display grants no local payment permission');
    check($capability->paymentCapability(1, 10)['payment_owner'] === 'mixed', 'capability exposes mixed order with per-device detail');
    check($capability->paymentCapability(1, 10, [1])['local_allowed'], 'selected local-only device is allowed');
    rejects(fn() => $capability->isPaymentManaged(1, 10), 'mixed isPaymentManaged raises instead of choosing one side');
    $capability->assertLocalPaymentAllowed(1, 10, [1]);
    check(count(Db::$tables['recycle_device_log']) === 1, 'local final gate uses real scoped claim');
    rejects(fn() => $capability->assertLocalPaymentAllowed(1), 'local final gate rejects context-free call');
    rejects(fn() => $bridge->settleSourceDevices(1, [1], ['capital_account_id' => 1]), 'finance bridge cannot route local owner to ERP');
    $bridge->settleSourceDevices(1, [2], ['capital_account_id' => 1, 'request_id' => 'REQUEST-STABLE']);
    $settlements = array_values(array_filter($GLOBALS['events'], static fn(array $entry): bool => $entry[0] === 'ErpSourcePayableSettlementRequested'));
    check(count($settlements) === 1 && $settlements[0][1]['event_id'] === 'REQUEST-STABLE' && $settlements[0][1]['source_device_ids'] === [2], 'ERP bridge preserves explicit request id and selected devices');

    foreach ([
        'missing finance ACK' => [],
        'failed finance ACK' => [['consumer' => 'hsx_erp', 'status' => 'failed']],
        'skipped finance ACK' => [['consumer' => 'hsx_erp', 'status' => 'skipped']],
        'blank finance ACK' => [['consumer' => 'hsx_erp']],
        'duplicate finance ACK' => [['consumer' => 'hsx_erp', 'status' => 'processed'], ['consumer' => 'hsx_erp', 'status' => 'processed']],
    ] as $name => $responses) {
        resetState();
        $GLOBALS['eventOverrides']['ErpCapitalAccountOptionsRequested'] = static fn(array $event): array => $responses;
        rejects(fn() => $bridge->capitalAccountOptions(1), $name . ' must not report success');
    }

    $guardEvent = ['event_name' => 'erp.source_payment.guard_requested.v1', 'event_version' => 1,
        'site_id' => 1, 'source_plugin' => 'hsx_recycle', 'source_device_ids' => [2]];
    resetState();
    $GLOBALS['evidence'][2] = evidence(['has_asset' => true, 'has_payable' => true, 'remaining_amount' => 100]);
    $result = Db::transaction(fn() => $guard->handle($guardEvent));
    check($result['status'] === 'processed' && $result['source_device_ids'] === [2], 'unpaid ERP-owned source passes guard with explicit device ACK');
    check(Db::$tables['recycle_device_log'][0]['operation_type'] === Ownership::ERP_LOG, 'ERP source guard freezes ERP ownership');
    resetState();
    rowChange(2, ['pay_status' => 1, 'pay_amount' => 100, 'pay_time' => 50]);
    $GLOBALS['evidence'][2] = evidence(['has_asset' => true, 'has_payable' => true, 'paid_amount' => 100]);
    rejects(fn() => Db::transaction(fn() => $guard->handle($guardEvent)), 'already-paid source device cannot pay again even if ERP is selected');
    check(Db::$tables['recycle_device_log'] === [], 'guard failure rolls owner write back with outer settlement transaction');
    resetState();
    rejects(fn() => Db::transaction(fn() => $guard->handle(array_merge($guardEvent, ['source_device_ids' => [1]]))), 'local-owned source cannot be paid from ERP');
    rejects(fn() => $guard->handle(array_merge($guardEvent, ['source_plugin' => 'other'])), 'guard rejects another source plugin');
    rejects(fn() => $guard->handle(array_merge($guardEvent, ['source_device_ids' => ['02']])) , 'guard rejects malformed source ids');
    rejects(fn() => $guard->handle(array_merge($guardEvent, ['site_id' => 2])), 'guard cannot cross site');
    check(Db::$tables['recycle_device_log'] === [], 'all rejected guards leave no ownership facts');

    foreach ($failures as $failure) fwrite(STDERR, '[FAIL] ' . $failure . "\n");
    if ($failures !== []) { fwrite(STDERR, count($failures) . " / {$count} assertions failed; in-memory only\n"); exit(1); }
    echo "[PASS] recycle ERP payment ownership service: {$count} assertions; real services, in-memory boundaries only\n";
}
