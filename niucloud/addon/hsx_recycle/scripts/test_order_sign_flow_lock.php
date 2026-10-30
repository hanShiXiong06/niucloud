<?php
declare(strict_types=1);

// 隔离回归：执行真实流程引擎与真实状态配置，查询、事务和业务处理器使用内存替身。
// 不加载应用/.env，不连接数据库、不新增真实设备、不调用打印或快递。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
}

namespace SignFlowLockFixture {
    final class Store
    {
        public static array $orders = [], $devices = [], $trace = [], $snapshots = [], $locks = [];
        public static int $depth = 0;
        public static $afterLock = null;
        public static bool $failHandler = false;

        public static function reset(): void
        {
            $base = ['member_id' => 11, 'site_id' => 100005, 'status' => 1, 'sign_at' => 0, 'delete_at' => 0];
            self::$orders = [
                10 => ['id' => 10] + $base,
                20 => ['id' => 20, 'site_id' => 100006] + $base,
                30 => ['id' => 30, 'status' => 2, 'sign_at' => 1790000000] + $base,
                40 => ['id' => 40, 'delete_at' => 1790000000] + $base,
                50 => ['id' => 50, 'status' => 6] + $base,
            ];
            self::$devices = self::$trace = self::$snapshots = self::$locks = [];
            self::$depth = 0; self::$afterLock = null; self::$failHandler = false;
        }

        public static function handler(string $action, array $order, array $data, array $context): array
        {
            if (self::$depth !== 1) throw new \RuntimeException('handler-outside-transaction');
            if (in_array($action, ['sign', 'payment'], true) && empty(self::$locks[(int)$order['id']])) {
                throw new \RuntimeException('handler-without-order-lock');
            }
            if ((int)$context['site_id'] !== (int)$order['site_id']) throw new \RuntimeException('handler-site-mismatch');
            self::$trace[] = ['handler', $action, self::$depth];
            if ($action === 'sign') {
                if ((int)$order['status'] !== 1 || empty($data['devices'])) throw new \RuntimeException('validation-not-before-handler');
                foreach ($data['devices'] as $device) self::$devices[] = ['order_id' => $order['id'], 'imei' => $device['imei']];
            }
            if (self::$failHandler) throw new \RuntimeException('fixture-handler-failure');
            return ['success' => true, 'data' => ['device_ids' => [101], 'sign_info' => ['sign_time' => 1790000000]]];
        }
    }

    final class Request
    {
        public int $siteId = 100005;
        public function siteId(): int { return $this->siteId; }
    }

    final class Query
    {
        private array $conditions = [];
        private bool $locked = false;
        public function where($field, $value): self { $this->conditions[$field] = $value; return $this; }
        public function lock(bool $enabled): self
        {
            if (Store::$depth !== 1) throw new \RuntimeException('lock-outside-transaction');
            $this->locked = $enabled;
            Store::$trace[] = ['lock', $this->conditions, Store::$depth];
            return $this;
        }
        public function findOrEmpty(): Row
        {
            if ($this->locked && Store::$afterLock !== null) {
                $callback = Store::$afterLock; Store::$afterLock = null;
                $callback(); // 模拟等待首请求锁释放后，当前读取得其已提交的新状态。
            }
            Store::$trace[] = ['read', $this->conditions, Store::$depth];
            foreach (Store::$orders as $row) {
                foreach ($this->conditions as $field => $value) {
                    if (($row[$field] ?? null) != $value) continue 2;
                }
                if ($this->locked) Store::$locks[(int)$row['id']] = true;
                return new Row($row);
            }
            return new Row([]);
        }
    }

    final class Row
    {
        private array $data;
        public function __construct(array $data) { $this->data = $data; }
        public function isEmpty(): bool { return $this->data === []; }
        public function toArray(): array { return $this->data; }
    }
}

namespace core\base {
    class BaseCoreService
    {
        public $request;
        public function __construct() { $this->request = new \SignFlowLockFixture\Request(); }
    }
}

namespace core\exception { class CommonException extends \RuntimeException {} }

namespace addon\hsx_recycle\app\model\order {
    final class RecycleOrder
    {
        public static function where($field, $value): \SignFlowLockFixture\Query
        {
            return (new \SignFlowLockFixture\Query())->where($field, $value);
        }
    }
}

namespace think\facade {
    final class Db
    {
        public static function startTrans(): void
        {
            \SignFlowLockFixture\Store::$snapshots[] = [\SignFlowLockFixture\Store::$orders, \SignFlowLockFixture\Store::$devices];
            \SignFlowLockFixture\Store::$trace[] = ['begin', ++\SignFlowLockFixture\Store::$depth];
        }
        public static function commit(): void
        {
            array_pop(\SignFlowLockFixture\Store::$snapshots);
            \SignFlowLockFixture\Store::$trace[] = ['commit', --\SignFlowLockFixture\Store::$depth];
            \SignFlowLockFixture\Store::$locks = [];
        }
        public static function rollback(): void
        {
            [\SignFlowLockFixture\Store::$orders, \SignFlowLockFixture\Store::$devices] = array_pop(\SignFlowLockFixture\Store::$snapshots);
            \SignFlowLockFixture\Store::$trace[] = ['rollback', --\SignFlowLockFixture\Store::$depth];
            \SignFlowLockFixture\Store::$locks = [];
        }
    }
    final class Log { public static function __callStatic($method, $args): void {} }
}

namespace addon\hsx_recycle\app\service\core\recycle_order\handler {
    final class SignHandler { public function handle($order, $data, $context): array { return \SignFlowLockFixture\Store::handler('sign', $order, $data, $context); } }
    final class PaymentHandler { public function handle($order, $data, $context): array { return \SignFlowLockFixture\Store::handler('payment', $order, $data, $context); } }
    final class CancelHandler { public function handle($order, $data, $context): array { return \SignFlowLockFixture\Store::handler('cancel', $order, $data, $context); } }
}

namespace addon\hsx_recycle\app\service\core\recycle_order {
    final class CoreRecycleOrderStatusService
    {
        public function transition(int $id, int $status, array $data): void
        {
            \SignFlowLockFixture\Store::$trace[] = ['transition', $id, $status, \SignFlowLockFixture\Store::$depth];
            \SignFlowLockFixture\Store::$orders[$id]['status'] = $status;
            if ($status === 2) \SignFlowLockFixture\Store::$orders[$id]['sign_at'] = 1790000000;
        }
    }
    final class CoreRecycleOrderEventService {}
    final class CoreRecycleOrderCancelReturnService { public function sync($id, $data, $context): void {} }
}

namespace addon\hsx_recycle\app\service\core\express {
    final class RecyclePickupService { public function record($siteId, $orderId) { return null; } }
    final class ExpressOperationLock
    {
        public static function run($siteId, $key, $callback)
        {
            \SignFlowLockFixture\Store::$trace[] = ['cancel-lock', $siteId, $key];
            return $callback();
        }
    }
    final class RecyclePickupCancellationService
    {
        public function complete($siteId, $orderId): array
        {
            if (\SignFlowLockFixture\Store::$depth !== 0) throw new \RuntimeException('cancel-complete-before-commit');
            return ['state' => 'cancelled'];
        }
    }
}

namespace {
    use SignFlowLockFixture\Store;
    use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService;
    use core\exception\CommonException;

    require_once __DIR__ . '/../app/dict/order/RecycleOrderDict.php';
    require_once __DIR__ . '/../app/dict/order/RecycleOrderAdminFlowDict.php';
    require_once __DIR__ . '/../app/service/core/recycle_order/CoreRecycleOrderFlowService.php';

    function expect(bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); }
    function sign(CoreRecycleOrderFlowService $flow, int $id = 10, array $context = ['site_id' => 100005]): array
    {
        return $flow->execute($id, 'sign', ['devices' => [['imei' => '123456789012345']]], 'admin', $context);
    }
    function reject(callable $callback, string $part): void
    {
        try { $callback(); throw new \RuntimeException('expected-rejection'); }
        catch (CommonException $e) { expect(strpos($e->getMessage(), $part) !== false, '错误提示不符：' . $e->getMessage()); }
        expect(Store::$devices === [], '拒绝后仍新增设备');
        expect(Store::$depth === 0 && end(Store::$trace)[0] === 'rollback', '拒绝未回滚');
    }
    $passed = 0;
    function check(string $name, callable $test): void
    {
        global $passed; Store::reset(); $test(); ++$passed; echo '[通过] ' . $name . PHP_EOL;
    }

    check('签收按事务→本站行锁→当前读→校验后处理→提交执行', function (): void {
        $result = sign(new CoreRecycleOrderFlowService());
        expect(array_column(Store::$trace, 0) === ['begin', 'lock', 'read', 'handler', 'transition', 'commit'], '流程执行顺序不符');
        expect(Store::$trace[1][1] === ['id' => 10, 'site_id' => 100005, 'delete_at' => 0], '签收锁未限定本站有效订单');
        expect($result['success'] && count(Store::$devices) === 1 && Store::$orders[10]['status'] === 2, '签收未成功');
    });
    check('同一单签收后重试拒绝，不重复新增设备', function (): void {
        $flow = new CoreRecycleOrderFlowService(); sign($flow);
        $devices = Store::$devices;
        try { sign($flow); throw new \RuntimeException('duplicate-sign-accepted'); }
        catch (CommonException $e) { expect(strpos($e->getMessage(), '不允许执行操作[sign]') !== false, '未拒绝已签收'); }
        expect(Store::$devices === $devices && count($devices) === 1, '重试重复新增设备');
    });
    check('锁等待结束重读首请求已提交状态，不使用签收前旧快照', function (): void {
        Store::$afterLock = function (): void { Store::$orders[10]['status'] = 2; Store::$orders[10]['sign_at'] = 1790000000; };
        reject(function (): void { sign(new CoreRecycleOrderFlowService()); }, '不允许执行操作[sign]');
        expect(!in_array('handler', array_column(Store::$trace, 0), true), '等待后仍调用处理器');
    });
    check('已签收订单在加锁读后被状态校验拒绝', function (): void {
        reject(function (): void { sign(new CoreRecycleOrderFlowService(), 30); }, '不允许执行操作[sign]');
    });
    check('跨站订单拒绝，优先使用 context.site_id 而非请求站点', function (): void {
        $flow = new CoreRecycleOrderFlowService(); $flow->request->siteId = 100006;
        reject(function () use ($flow): void { sign($flow, 20); }, '订单不存在');
    });
    check('已删除订单拒绝', function (): void {
        reject(function (): void { sign(new CoreRecycleOrderFlowService(), 40); }, '订单不存在');
    });
    check('缺少签收设备先校验失败，不执行处理器', function (): void {
        reject(function (): void { (new CoreRecycleOrderFlowService())->execute(10, 'sign', [], 'admin', ['site_id' => 100005]); }, '缺少必需参数：devices');
        expect(array_column(Store::$trace, 0) === ['begin', 'lock', 'read', 'rollback'], '未先锁定再验证必填数据');
    });
    check('上下文未带站点时沿用当前请求站点并传入处理器', function (): void {
        sign(new CoreRecycleOrderFlowService(), 10, []);
        expect(count(Store::$devices) === 1, '请求站点兜底失败');
    });
    check('无有效站点时在读取前拒绝并回滚', function (): void {
        $flow = new CoreRecycleOrderFlowService(); $flow->request->siteId = 0;
        reject(function () use ($flow): void { sign($flow, 10, []); }, '无法确认签收订单所属站点');
        expect(array_column(Store::$trace, 0) === ['begin', 'rollback'], '无效站点仍读取订单');
    });
    check('处理器失败回滚设备及状态', function (): void {
        Store::$failHandler = true;
        reject(function (): void { sign(new CoreRecycleOrderFlowService()); }, 'fixture-handler-failure');
        expect(Store::$orders[10]['status'] === 1, '处理失败改变状态');
    });
    check('原 payment 分支仍锁定本站订单并完成', function (): void {
        (new CoreRecycleOrderFlowService())->execute(50, 'payment', ['payment_info' => ['amount' => 1]], 'admin', ['site_id' => 100005]);
        expect(Store::$trace[1][0] === 'lock' && Store::$orders[50]['status'] === 7, '付款路径被改变');
    });
    check('原 cancel 预约锁与提交后取消完成路径保持不变', function (): void {
        $result = (new CoreRecycleOrderFlowService())->execute(10, 'cancel', ['reason' => 'fixture'], 'admin', ['site_id' => 100005]);
        expect(Store::$trace[0][0] === 'cancel-lock' && !in_array('lock', array_column(Store::$trace, 0), true), '取消锁路径被改变');
        expect(Store::$orders[10]['status'] === 9 && $result['data']['data']['pickup_cancellation']['state'] === 'cancelled', '取消路径未完成');
    });
    echo '[结果] ' . $passed . ' 项真实流程引擎隔离回归通过；未连接数据库、未打印或叫件。' . PHP_EOL;
}
