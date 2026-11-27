<?php
declare(strict_types=1);

/**
 * 离线预约状态/连接锁回归：只加载两个真实业务类，其余边界使用内存替身。
 * 不加载框架、不读 .env、不连接数据库、不发快递或通知请求。
 * 运行：php niucloud/addon/hsx_recycle/scripts/verify_pickup_state.php
 * 源码断言只证明关键调用顺序存在，不替代 MySQL 并发或真实渠道验收。
 */
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
}

namespace core\exception {
    class CommonException extends \RuntimeException {}
}

namespace pickup_test {
    final class MockConnection
    {
        public array $events = [];
        public bool $initialized = false;
        public $lockResult;
        public bool $failRelease = false;
        private MockPDO $pdo;

        public function __construct($lockResult = 1)
        {
            $this->lockResult = $lockResult;
            $this->pdo = new MockPDO($this);
        }

        public function query(string $sql, array $bind = [], bool $master = false): array
        {
            $this->events[] = ['query', $sql, $bind, $master];
            if ($sql !== 'SELECT 1' || $bind !== [] || !$master) {
                throw new \RuntimeException('冷连接必须先显式初始化主库');
            }
            $this->initialized = true;
            return [['1' => 1]];
        }

        public function getPdo(): MockPDO
        {
            $this->events[] = ['getPdo'];
            if (!$this->initialized) throw new \RuntimeException('冷连接尚无 PDO');
            return $this->pdo;
        }
    }

    final class MockPDO
    {
        private MockConnection $connection;
        public function __construct(MockConnection $connection) { $this->connection = $connection; }
        public function prepare(string $sql): MockStatement
        {
            if (!in_array($sql, ['SELECT GET_LOCK(?, 2)', 'SELECT RELEASE_LOCK(?)'], true)) {
                throw new \RuntimeException('离线替身不允许其他 SQL：' . $sql);
            }
            $this->connection->events[] = ['prepare', $sql];
            return new MockStatement($this->connection, $sql);
        }
    }

    final class MockStatement
    {
        private MockConnection $connection;
        private string $sql;
        public function __construct(MockConnection $connection, string $sql)
        {
            $this->connection = $connection;
            $this->sql = $sql;
        }
        public function execute(array $params): bool
        {
            $this->connection->events[] = ['execute', $this->sql, $params];
            if ($this->sql === 'SELECT RELEASE_LOCK(?)' && $this->connection->failRelease) {
                throw new \RuntimeException('simulated disconnected PDO');
            }
            return true;
        }
        public function fetchColumn()
        {
            $this->connection->events[] = ['fetchColumn'];
            return $this->connection->lockResult;
        }
    }
}

namespace think\facade {
    final class Db
    {
        public static ?\pickup_test\MockConnection $connection = null;
        public static function connect(): \pickup_test\MockConnection
        {
            if (!self::$connection) throw new \RuntimeException('未设置离线连接替身');
            self::$connection->events[] = ['connect'];
            return self::$connection;
        }
    }
    final class Log
    {
        public static array $warnings = [];
        public static function warning(string $message, array $context = []): void
        {
            self::$warnings[] = [$message, $context];
        }
    }
}

namespace {
    use addon\hsx_recycle\app\service\core\express\ExpressOperationLock;
    use addon\hsx_recycle\app\service\core\express\PickupState;
    use pickup_test\MockConnection;
    use think\facade\Db;
    use think\facade\Log;

    $plugin = dirname(__DIR__);
    require $plugin . '/app/service/core/express/PickupState.php';
    require $plugin . '/app/service/core/express/ExpressOperationLock.php';
    $checks = 0;
    $failures = [];

    function same($expected, $actual, string $label): void
    {
        if ($expected !== $actual) {
            $failure = 'FAIL ' . $label . ': ' . json_encode([
                'expected' => $expected, 'actual' => $actual,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $GLOBALS['failures'][] = $failure;
            fwrite(STDERR, $failure . PHP_EOL);
            return;
        }
        $GLOBALS['checks']++;
        echo 'PASS ' . $label . PHP_EOL;
    }

    function executed(MockConnection $connection, string $sql): array
    {
        return array_values(array_filter($connection->events, static function (array $event) use ($sql): bool {
            return $event[0] === 'execute' && $event[1] === $sql;
        }));
    }

    /** 提取真实方法体并忽略注释；花括号字符串不会影响层级。 */
    function methodSource(string $file, string $method): string
    {
        $source = file_get_contents($file);
        if ($source === false) throw new \RuntimeException('无法读取 ' . $file);
        $tokens = token_get_all($source);
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
                if ($tokens[$j] === '&') continue;
                if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $method) break;
                while ($j < $count && $tokens[$j] !== '{') $j++;
                $depth = 0;
                $body = '';
                for (; $j < $count; $j++) {
                    $token = $tokens[$j];
                    if ($token === '{') $depth++;
                    if (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) $depth++;
                    if ($token === '}' && --$depth === 0) return $body . '}';
                    if (is_array($token)) {
                        if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) $body .= $token[1];
                    } else {
                        $body .= $token;
                    }
                }
            }
        }
        throw new \RuntimeException('未找到方法 ' . $method . ' in ' . $file);
    }

    function before(string $body, string $first, string $second, string $label): void
    {
        $a = strpos($body, $first);
        $b = strpos($body, $second);
        same(true, $a !== false && $b !== false && $a < $b, $label);
    }

    try {
        // 调重/结算没有新的物流阶段，不能自动解除已确认存在的冲突。
        $conflict = PickupState::merge(['booking_state' => 'cancelled'], [
            'booking_state' => 'confirmed', 'conflict' => false,
        ]);
        same(true, $conflict['conflict'], '取消后晚到确认形成待核实冲突');
        foreach (['', null] as $emptyState) {
            $result = PickupState::merge($conflict, ['booking_state' => $emptyState, 'conflict' => false, 'actual_weight' => 2.5]);
            same('cancelled', $result['booking_state'], '金融空状态不覆盖预约状态 ' . var_export($emptyState, true));
            same(true, $result['conflict'], '金融空状态不能清除冲突 ' . var_export($emptyState, true));
            same(false, PickupState::view($result, ['status' => 1])['can_manual'], '冲突后仍禁止自行寄件 ' . var_export($emptyState, true));
        }

        $manual = ['booking_state' => 'manual', 'deliveryId' => 'MANUAL123', 'carrier_name' => '自行寄件公司',
            'carrier_code' => 'manual', 'pickup_time' => '2026-09-29 09:00-11:00',
            'courier_name' => '本人登记', 'courier_phone' => '13800000001', 'courier_mobile' => '13800000001'];
        foreach (['accepted', 'confirmed', 'assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled', 'failed', 'unknown', 'exception', ''] as $lateState) {
            $incoming = array_fill_keys(array_keys($manual), 'OLD_VALUE');
            $incoming['booking_state'] = $lateState;
            $result = PickupState::merge($manual, $incoming);
            same($manual, array_intersect_key($result, $manual), '自行寄件不被旧回调覆盖 ' . ($lateState ?: '空状态'));
        }

        $delivered = PickupState::merge(['booking_state' => 'delivered'], ['booking_state' => 'exception']);
        same('delivered', $delivered['highest_booking_state'], '异常仍保留最高签收阶段');
        $oldConfirmed = PickupState::merge($delivered, ['booking_state' => 'confirmed']);
        same('exception', $oldConfirmed['booking_state'], '签收经异常后不被旧确认回调倒退');
        same('delivered', $oldConfirmed['highest_booking_state'], '异常后的旧确认不抹去签收事实');
        $lateFailed = PickupState::merge($oldConfirmed, ['booking_state' => 'failed']);
        same(true, $lateFailed['conflict'], '已签收经异常后的失败回调需核实');
        same(false, PickupState::view($lateFailed, ['status' => 1])['can_manual'], '已签收经异常后不开放自行寄件');
        same('assigned', PickupState::merge(['booking_state' => 'assigned'], ['booking_state' => 'confirmed'])['booking_state'], '常规晚回调不降低取件阶段');
        same('KNOWN123', PickupState::merge(['deliveryId' => 'KNOWN123'], ['deliveryId' => ''])['deliveryId'], '空运单号不清除已知运单号');

        $knownBilling = ['chargeable_weight' => '2.50', 'actual_weight' => 2.3,
            'fee_details' => ['baseFreight' => '12.00', 'additionalFreight' => '0.00']];
        foreach ([
            '未携带计费字段' => [],
            '规范化缺失值' => ['chargeable_weight' => null, 'actual_weight' => null, 'fee_details' => []],
            '空字符串及空明细' => ['chargeable_weight' => '', 'actual_weight' => '  ', 'fee_details' => null],
        ] as $label => $incoming) {
            $result = PickupState::merge($knownBilling, $incoming + ['booking_state' => 'assigned']);
            same($knownBilling, array_intersect_key($result, $knownBilling), $label . '保留已知重量和费用明细');
            same('assigned', $result['booking_state'], $label . '仍更新有效预约状态');
        }
        foreach ([0, 0.0, '0', '0.00'] as $zero) {
            $result = PickupState::merge($knownBilling, [
                'chargeable_weight' => $zero, 'actual_weight' => $zero, 'fee_details' => ['baseFreight' => $zero],
            ]);
            same($zero, $result['chargeable_weight'], '有效零计费重量不被忽略 ' . var_export($zero, true));
            same($zero, $result['actual_weight'], '有效零实际重量不被忽略 ' . var_export($zero, true));
            same(['baseFreight' => $zero], $result['fee_details'], '全零费用明细仍可更新 ' . var_export($zero, true));
        }
        $updatedBilling = ['chargeable_weight' => '3.00', 'actual_weight' => 2.8,
            'fee_details' => ['baseFreight' => '15.00', 'additionalFreight' => '1.00']];
        same($updatedBilling, PickupState::merge($knownBilling, $updatedBilling), '明确的新计费数据替换旧值');
        $withoutBilling = PickupState::merge([], ['chargeable_weight' => null, 'actual_weight' => null, 'fee_details' => []]);
        same([], array_intersect_key($withoutBilling, $knownBilling), '没有历史计费信息时不伪造空值或零值');

        foreach (['submitting', 'unknown', 'accepted', 'confirmed', 'assigned', 'picked_up', 'in_transit', 'delivered', 'manual'] as $state) {
            same(false, PickupState::view(['booking_state' => $state], ['status' => 1])['can_manual'], $state . ' 不允许自行寄件');
        }
        foreach (['failed', 'cancelled'] as $state) {
            same(true, PickupState::view(['booking_state' => $state], ['status' => 1])['can_manual'], $state . ' 且回收待寄件可补填');
            foreach ([0, 2, 3, 6, 7, 8, 9, 10] as $orderStatus) {
                same(false, PickupState::view(['booking_state' => $state], ['status' => $orderStatus])['can_manual'], $state . ' 但回收状态 ' . $orderStatus . ' 不可补填');
            }
        }
        $view = PickupState::view(['booking_state' => 'confirmed', 'callback_salt' => 'SECRET_CALLBACK_SALT',
            'secret' => 'SECRET_API_KEY', 'raw' => ['salt' => 'SECRET_RAW'], 'failure_reason' => 'SECRET_ERROR',
            'receiver' => ['contact_name' => '门店', 'address' => '门店地址', 'callback_salt' => 'SECRET_RECEIVER']], ['status' => 1]);
        same(false, strpos(json_encode($view), 'SECRET_') !== false, '客户 view 不泄露顶层或嵌套敏感快照');
        same(['contact_name' => '门店', 'address' => '门店地址'], $view['receiver'], '收件人仅输出白名单字段');

        $connection = Db::$connection = new MockConnection();
        $value = ExpressOperationLock::run(100005, 'recycle_100005_7', static function () use ($connection) {
            $connection->events[] = ['operation'];
            return ['accepted' => true];
        });
        same(['accepted' => true], $value, '成功获取锁后原样返回业务结果');
        same([['connect'], ['query', 'SELECT 1', [], true], ['getPdo']], array_slice($connection->events, 0, 3), '冷连接先初始化主库再取得 PDO');
        same(1, count(executed($connection, 'SELECT GET_LOCK(?, 2)')), '仅获取一次锁');
        same(1, count(executed($connection, 'SELECT RELEASE_LOCK(?)')), '成功操作后释放锁');
        $lockName = 'hsx_express_' . hash('sha1', '100005|recycle_100005_7');
        same([$lockName], executed($connection, 'SELECT GET_LOCK(?, 2)')[0][2], '锁名包含站点及幂等业务标识');
        same([$lockName], executed($connection, 'SELECT RELEASE_LOCK(?)')[0][2], '释放与获取同一把锁');

        foreach ([0, null, false] as $lockResult) {
            $connection = Db::$connection = new MockConnection($lockResult);
            $ran = false;
            $thrown = false;
            try { ExpressOperationLock::run(100005, 'busy', static function () use (&$ran) { $ran = true; }); }
            catch (\core\exception\CommonException $e) { $thrown = true; }
            same(true, $thrown, '锁未获得时返回明确异常 ' . var_export($lockResult, true));
            same(false, $ran, '锁未获得时绝不执行下单 ' . var_export($lockResult, true));
            same([], executed($connection, 'SELECT RELEASE_LOCK(?)'), '不释放未取得的锁 ' . var_export($lockResult, true));
        }

        $connection = Db::$connection = new MockConnection('1');
        $failure = new \RuntimeException('simulated business failure');
        $caught = null;
        try { ExpressOperationLock::run(100005, 'throwing', static function () use ($failure) { throw $failure; }); }
        catch (\Throwable $e) { $caught = $e; }
        same($failure, $caught, '业务异常原样向上传递');
        same(1, count(executed($connection, 'SELECT RELEASE_LOCK(?)')), '业务抛错也在 finally 释放锁');
        $connection = Db::$connection = new MockConnection();
        $connection->failRelease = true;
        Log::$warnings = [];
        same('saved', ExpressOperationLock::run(100005, 'release_failure', static fn() => 'saved'), '释放锁异常不改写已完成业务结果');
        same(1, count(Log::$warnings), '释放异常保留告警');

        // 下面只做源码契约检查，不实例化主服务，不执行任何订单、渠道或数据库操作。
        $core = methodSource($plugin . '/app/service/core/ExpressOrderService.php', 'createLocked');
        before($core, '$this->createExpressRecord(', '$this->expressGateway->create(', '静态：持久占位调用先于外部下单');
        before($core, "\$record->save(['api_response' => \$snapshot", '$this->expressGateway->create(', '静态：完整预约快照先于外部下单保存');
        before($core, '$params = array_replace($params, $providerSnapshot)', '$this->expressGateway->create(', '静态：服务端配置快照传给适配器后才下单');
        $persist = methodSource($plugin . '/app/service/core/ExpressOrderService.php', 'createExpressRecord');
        same(true, strpos($persist, 'ExpressOrderRecord::createRecord(') !== false, '静态：占位方法确实调用记录持久化');
        $api = methodSource($plugin . '/app/service/api/recycle_order/RecycleOrderService.php', 'add');
        before($api, 'Db::commit()', '(new RecyclePickupService())->submit(', '静态：新回收订单先提交事务再预约');
        $recordService = $plugin . '/app/service/admin/express/ExpressOrderRecordService.php';
        foreach (['getWeightDiffList', 'getCostDiffList'] as $method) {
            $body = methodSource($recordService, $method);
            same(true, preg_match('/return\s+array_map\s*\(\s*\[\s*\$this\s*,\s*[\'\"]safeRecord[\'\"]\s*\]/', $body) === 1, '静态：' . $method . ' 输出经过 safeRecord');
        }
        $safe = methodSource($recordService, 'safeRecord');
        same(true, strpos($safe, "unset(\$record['api_response']['callback_salt'])") !== false, '静态：safeRecord 剔除订单验签盐');

        if ($failures) {
            fwrite(STDERR, 'FAIL ' . count($failures) . " checks; {$checks} passed (offline only)\n");
            exit(1);
        }
        echo "PASS {$checks} checks (real state rules + mock PDO/Db + static contracts; no network/database)\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->__toString() . PHP_EOL);
        exit(1);
    }
}
