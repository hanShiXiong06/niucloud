<?php
declare(strict_types=1);

/**
 * 挂账完成闸门隔离回归：执行真实插件服务，依赖替换为内存 ORM / ERP 只读事件契约。
 * 不加载框架、.env、业务数据库，不调用真实收款、物流、微信或队列。
 * ERP 金额聚合另见 tests/phone_shop_erp_credit_feedback_test.php；本脚本验证消费方不绕过契约。
 */
namespace CreditCompletionTest {
    final class Store {
        public static array $tables = [];
        public static array $responses = [];
        public static array $responseQueue = [];
        public static array $requests = [];
        public static array $events = [];
        public static array $locks = [];
        public static array $deliveries = [];
        public static int $transactionDepth = 0;
        public static bool $providerThrows = false;
        public static bool $finishEventThrows = false;
    }
    final class Row implements \ArrayAccess {
        private string $table;
        private array $data;
        public function __construct(string $table, array $data = []) { $this->table = $table; $this->data = $data; }
        public function toArray(): array { return $this->data; }
        public function isEmpty(): bool { return $this->data === []; }
        public function save(array $data): bool {
            $key = $this->table === 'lines' ? 'order_goods_id' : ($this->table === 'users' ? 'uid' : 'order_id');
            (new Query($this->table))->where($key, $this->data[$key])->where('site_id', $this->data['site_id'] ?? 100005)->update($data);
            $this->data = array_replace($this->data, $data);
            return true;
        }
        public function offsetExists($key): bool { return array_key_exists($key, $this->data); }
        public function offsetGet($key) { return $this->data[$key] ?? null; }
        public function offsetSet($key, $value): void { $this->data[$key] = $value; }
        public function offsetUnset($key): void { unset($this->data[$key]); }
    }
    final class Collection implements \IteratorAggregate, \Countable {
        private string $table;
        private array $rows;
        public function __construct(string $table, array $rows) { $this->table = $table; $this->rows = $rows; }
        public function toArray(): array { return $this->rows; }
        public function count(): int { return count($this->rows); }
        public function isEmpty(): bool { return $this->rows === []; }
        public function getIterator(): \Traversable { foreach ($this->rows as $row) yield new Row($this->table, $row); }
    }
    final class Query {
        private string $table;
        private array $conditions = [];
        public function __construct(string $table) { $this->table = $table; }
        public function where($key, $operator = null, $value = null): self {
            if (is_array($key)) {
                foreach ($key as $clause) $this->where(...$clause);
            } else {
                if (func_num_args() === 2) { $value = $operator; $operator = '='; }
                $this->conditions[] = [$key, strtolower((string)$operator), $value];
            }
            return $this;
        }
        public function whereIn(string $key, array $values): self { return $this->where($key, 'in', $values); }
        public function field($fields): self { return $this; }
        public function lock(bool $lock): self {
            if ($lock) Store::$locks[] = [$this->table, Store::$transactionDepth];
            return $this;
        }
        private function matches(array $row): bool {
            foreach ($this->conditions as [$key, $operator, $value]) {
                $actual = $row[$key] ?? null;
                if ($operator === 'in') { if (!in_array((string)$actual, array_map('strval', (array)$value), true)) return false; }
                elseif ($operator === '<>') { if ((string)$actual === (string)$value) return false; }
                elseif ($operator === '=') { if ((string)$actual !== (string)$value) return false; }
                else throw new \RuntimeException('测试 ORM 不支持条件：' . $operator);
            }
            return true;
        }
        public function select(): Collection {
            return new Collection($this->table, array_values(array_filter(Store::$tables[$this->table] ?? [], fn($row) => $this->matches($row))));
        }
        public function findOrEmpty(): Row { return new Row($this->table, $this->select()->toArray()[0] ?? []); }
        public function find(): ?array { return $this->select()->toArray()[0] ?? null; }
        public function count(): int { return $this->select()->count(); }
        public function update(array $data): int {
            $count = 0;
            foreach (Store::$tables[$this->table] as &$row) {
                if ($this->matches($row)) { $row = array_replace($row, $data); ++$count; }
            }
            return $count;
        }
    }
    class Model {
        protected static string $table = '';
        public static function where(...$args): Query { return (new Query(static::$table))->where(...$args); }
    }
}
namespace core\base {
    class BaseCoreService { protected $model; public function __construct() {} }
    class BaseAdminService {
        protected $model;
        public int $site_id = 100005;
        public int $uid = 7;
        public string $username = '测试业务员';
        public function __construct() {}
    }
}
namespace core\exception {
    class CommonException extends \RuntimeException {}
    class AdminException extends \RuntimeException {}
}
namespace think\facade {
    final class Db {
        public static function transaction(callable $callback) {
            $before = \CreditCompletionTest\Store::$tables;
            ++\CreditCompletionTest\Store::$transactionDepth;
            try { return $callback(); }
            catch (\Throwable $e) { \CreditCompletionTest\Store::$tables = $before; throw $e; }
            finally { --\CreditCompletionTest\Store::$transactionDepth; }
        }
    }
    final class Log { public static function write($message): void {} public static function error($message): void {} }
}
namespace app\dict\pay {
    class PayDict { const WECHATPAY = 'wechatpay'; const ALIPAY = 'alipay'; const OFFLINEPAY = 'offlinepay'; }
}
namespace app\model\sys {
    class SysUser extends \CreditCompletionTest\Model { protected static string $table = 'users'; }
}
namespace app\model\pay {
    class Pay extends \CreditCompletionTest\Model { protected static string $table = 'pays'; }
}
namespace addon\phone_shop\app\model\order {
    class Order extends \CreditCompletionTest\Model { protected static string $table = 'orders'; }
    class OrderGoods extends \CreditCompletionTest\Model { protected static string $table = 'lines'; }
    class OrderOfflineRecord extends \CreditCompletionTest\Model { protected static string $table = 'offline_records'; }
}
namespace addon\phone_shop\app\service\core\order {
    class CoreOrderEventService {
        public static function orderFinish(array $data): void {
            if (\CreditCompletionTest\Store::$finishEventThrows) throw new \RuntimeException('测试完成事件失败');
            \CreditCompletionTest\Store::$events[] = ['finish', $data];
        }
        public static function orderFinishAfter(array $data): void { \CreditCompletionTest\Store::$events[] = ['finish_after', $data]; }
    }
}
namespace addon\phone_shop\app\service\admin\order {
    // 外部交付依赖替身：只模拟已经核实的真实交付结果，不测试物流 API / 核销 / 微信。
    class OrderDeliveryService extends \core\base\BaseAdminService {
        public function delivery(array $data): bool {
            \CreditCompletionTest\Store::$deliveries[] = $data;
            (new \CreditCompletionTest\Query('orders'))->where('site_id', $this->site_id)->where('order_id', $data['order_id'])
                ->update(['status' => \addon\phone_shop\app\dict\order\OrderDict::WAIT_TAKE, 'delivery_time' => time()]);
            (new \CreditCompletionTest\Query('lines'))->where('site_id', $this->site_id)->whereIn('order_goods_id', $data['order_goods_ids'])
                ->update(['delivery_status' => \addon\phone_shop\app\dict\order\OrderDeliveryDict::DELIVERY_FINISH]);
            return true;
        }
    }
}
namespace {
    use CreditCompletionTest\Store;
    use addon\phone_shop\app\dict\order\OrderDict;
    use addon\phone_shop\app\dict\order\OrderGoodsDict;
    use addon\phone_shop\app\dict\order\OrderDeliveryDict;
    use addon\phone_shop\app\service\core\order\CoreOrderFinishService;
    use addon\phone_shop\app\service\core\order\CoreOfflineSaleService;
    use addon\phone_shop\app\service\admin\order\OfflineOrderService;

    $plugin = dirname(__DIR__);
    foreach (['OrderDict', 'OrderGoodsDict', 'OrderDeliveryDict', 'OrderLogDict'] as $dict) require $plugin . '/app/dict/order/' . $dict . '.php';
    foreach (['CoreOrderCreditStateService', 'ErpDeviceSnapshot', 'CoreOrderFinishService', 'CoreOrderEditPriceService', 'CoreOfflineSaleService'] as $service) require $plugin . '/app/service/core/order/' . $service . '.php';
    require $plugin . '/app/service/admin/order/OrderFinishService.php';
    require $plugin . '/app/service/admin/order/OfflineOrderService.php';
    require $plugin . '/app/listener/verify/VerifyListener.php';

    function event(string $name, array $data): array {
        if ($name !== 'PhoneShopOrderCreditStateRequested') throw new \RuntimeException('不允许未声明的外部事件：' . $name);
        Store::$requests[] = [$name, $data, Store::$transactionDepth];
        if (Store::$providerThrows) throw new \RuntimeException('测试 ERP 服务不可用');
        if (Store::$responseQueue) return array_shift(Store::$responseQueue);
        return Store::$responses;
    }
    set_error_handler(static function ($level, $message, $file, $line): void { throw new \ErrorException($message, 0, $level, $file, $line); });
    $checks = 0;
    function check(bool $ok, string $message): void { global $checks; ++$checks; if (!$ok) throw new \RuntimeException($message); }
    function fixture(array $order = []): array {
        Store::$requests = Store::$events = Store::$locks = Store::$deliveries = [];
        Store::$responseQueue = [];
        Store::$providerThrows = Store::$finishEventThrows = false;
        Store::$transactionDepth = 0;
        $order = array_replace([
            'site_id' => 100005, 'order_id' => 77, 'order_no' => '20261028752442001006592',
            'member_id' => 50, 'payment_mode' => 'offline_credit', 'is_credit' => 1,
            'order_money' => '1000.00', 'pay_money' => '1000.00', 'credit_status' => 'settled', 'settle_status' => 1,
            'status' => OrderDict::WAIT_TAKE, 'delivery_type' => OrderDeliveryDict::STORE,
            'timeout' => 1790000000, 'finish_time' => 0, 'relate_source' => '', 'is_enable_refund' => 0,
            'out_trade_no' => 'original-pay-no',
        ], $order);
        Store::$tables = [
            'orders' => [$order],
            'lines' => [[
                'site_id' => 100005, 'order_id' => 77, 'order_goods_id' => 31, 'is_gift' => 0,
                'status' => OrderGoodsDict::NORMAL, 'delivery_status' => OrderDeliveryDict::DELIVERY_FINISH, 'extend' => '{}',
                'verify_count' => 0, 'verify_expire_time' => '', 'num' => 1,
            ]],
            'offline_records' => [['site_id' => 100005, 'order_id' => 77, 'status' => 'credit']],
            'users' => [['site_id' => 100005, 'uid' => 7, 'username' => '测试业务员', 'mobile' => '13000000000']],
            'pays' => [],
        ];
        providerState(['can_complete' => false, 'status' => 'pending', 'settled_amount' => '0.00', 'remaining_amount' => '1000.00']);
        return $order;
    }
    function providerState(array $overrides = []): void {
        $order = Store::$tables['orders'][0];
        $state = array_replace([
            'provider' => 'hsx_erp', 'available' => true, 'matched' => true, 'can_complete' => true,
            'site_id' => $order['site_id'], 'order_id' => $order['order_id'], 'order_no' => $order['order_no'],
            'status' => 'settled', 'amount' => '1000.00', 'settled_amount' => '1000.00', 'remaining_amount' => '0.00',
            'cash_received_amount' => $overrides['settled_amount'] ?? '1000.00', 'offset_amount' => '0.00',
            'original_amount' => '1000.00', 'expected_amount' => '1000.00', 'returned_unreceived_amount' => '0.00',
            'message' => '测试 ERP 已核实结清',
        ], $overrides);
        Store::$responses = [['provider' => 'hsx_erp', 'site_id' => $order['site_id'], 'orders' => [$order['order_id'] => $state]]];
    }
    function finish(array $extra = []): bool {
        return (new CoreOrderFinishService())->finish(array_replace(['order_id' => 77, 'site_id' => 100005], $extra));
    }
    function rejected(callable $action, string $label, string $expected = ''): void {
        $before = Store::$tables;
        try { $action(); throw new \LogicException($label . ' 未被拒绝'); }
        catch (\core\exception\CommonException | \core\exception\AdminException $e) {
            check($expected === '' || strpos($e->getMessage(), $expected) !== false, $label . ' 错误提示不准确：' . $e->getMessage());
        }
        check(Store::$tables === $before, $label . ' 不应修改订单/设备');
        check(Store::$events === [], $label . ' 不应发出完成事件');
    }
    function deliver(): array {
        $method = new \ReflectionMethod(OfflineOrderService::class, 'confirmDelivery');
        $method->setAccessible(true);
        return $method->invoke(new OfflineOrderService(), 77, '真实交付已核对');
    }

    foreach ([
        '尚未建应收' => ['matched' => false, 'can_complete' => false, 'status' => 'not_linked'],
        '应收已建但未收款' => ['can_complete' => false, 'status' => 'pending', 'settled_amount' => '0.00', 'remaining_amount' => '1000.00'],
        '部分收款' => ['can_complete' => false, 'status' => 'partial', 'settled_amount' => '300.00', 'remaining_amount' => '700.00'],
        '差一分钱' => ['can_complete' => false, 'status' => 'partial', 'settled_amount' => '999.99', 'remaining_amount' => '0.01'],
        'ERP账务冲突' => ['available' => false, 'can_complete' => false, 'status' => 'conflict'],
    ] as $label => $state) {
        fixture(); providerState(array_replace($state, ['message' => $label]));
        rejected(fn() => finish(), $label, $label);
        check(Store::$requests[0][1]['lock'] === true && Store::$requests[0][2] > 0, $label . ' 应在事务内持锁请求 ERP 事实');
    }
    fixture(); Store::$responses = [];
    rejected(fn() => finish(), 'ERP无响应且本地伪settled不可完成', 'ERP');
    fixture(); Store::$providerThrows = true;
    rejected(fn() => finish(), 'ERP异常不可完成', 'ERP');
    fixture(); providerState(); Store::$responses[] = Store::$responses[0];
    rejected(fn() => finish(), '多个 ERP 响应不可猜选', 'ERP');
    foreach (['site_id' => 100006, 'order_id' => 78, 'order_no' => 'OTHER'] as $key => $value) {
        fixture(); providerState([$key => $value]); rejected(fn() => finish(), '错配契约：' . $key, 'ERP');
    }
    fixture(); providerState(); Store::$responses[0]['site_id'] = 100006;
    rejected(fn() => finish(), '跨站 ERP provider', 'ERP');
    fixture(); providerState(); rejected(fn() => finish(['site_id' => 100006]), '跨站完成请求', 'NOT_FOUND');
    foreach ([
        '布尔可完成但仍欠一分' => ['can_complete' => true, 'settled_amount' => '999.99', 'remaining_amount' => '0.01', 'cash_received_amount' => '999.99'],
        'ERP原额不是原商城单金额' => ['original_amount' => '2000.00'],
        '收款来源金额不一致' => ['cash_received_amount' => '999.99'],
        '超出分精度的金额' => ['offset_amount' => '0.001'],
        '布尔可完成但状态是部分收款' => ['can_complete' => true, 'status' => 'partial'],
        '可用状态不是真布尔值' => ['available' => 'true'],
    ] as $label => $state) {
        fixture(); providerState($state); rejected(fn() => finish(), $label, '不一致');
    }
    fixture(); Store::$responses = [];
    rejected(fn() => finish(['finance_snapshot' => ['available' => true, 'matched' => true, 'can_complete' => true]]), '调用者伪造财务快照不可绕过', 'ERP');
    fixture();
    rejected(fn() => (new \addon\phone_shop\app\service\core\order\CoreOrderEditPriceService())->finish(['site_id' => 100005, 'order_id' => 77]), '旧复制完成入口也走统一闸门');

    fixture(['status' => OrderDict::WAIT_DELIVERY]); providerState();
    rejected(fn() => finish(), '收齐但未交付不可完成', '交付');
    fixture(['status' => OrderDict::WAIT_DELIVERY]);
    rejected(fn() => finish(['pickup_verified' => true]), '可信核销标记不绕过 ERP 未收款');
    fixture(['status' => OrderDict::WAIT_DELIVERY, 'delivery_type' => OrderDeliveryDict::EXPRESS]); providerState();
    rejected(fn() => finish(['pickup_verified' => true]), '自提核销标记不允许物流未交付订单完成', '交付');
    fixture(['status' => OrderDict::WAIT_DELIVERY]); providerState();
    check(finish(['pickup_verified' => true]) && Store::$tables['orders'][0]['status'] === OrderDict::FINISH, '可信门店核销且 ERP 结清可完成');
    foreach (['shopVirtualGoods', 'shopPickUpOrder'] as $type) {
        fixture(['status' => $type === 'shopVirtualGoods' ? OrderDict::WAIT_TAKE : OrderDict::WAIT_DELIVERY]);
        $params = ['site_id' => 100005, 'type' => $type, 'data' => ['order_id' => 77, 'order_goods_id' => 31]];
        rejected(fn() => (new \addon\phone_shop\app\listener\verify\VerifyListener())->handle($params), $type . ' 未收款拒绝且不提前累计核销');
        check(Store::$tables['lines'][0]['verify_count'] === 0 && Store::$requests[0][2] > 0, $type . ' 核销前置校验在事务内且不吃次数');
        providerState();
        (new \addon\phone_shop\app\listener\verify\VerifyListener())->handle($params);
        check(Store::$tables['orders'][0]['status'] === OrderDict::FINISH, $type . ' 真实核销与全额结清后完成');
        check(count(Store::$events) === 2, $type . ' 成功仅发一次完成事件');
    }
    fixture();
    $pending = Store::$responses;
    providerState();
    Store::$responseQueue = [Store::$responses, $pending];
    rejected(fn() => (new \addon\phone_shop\app\listener\verify\VerifyListener())->handle([
        'site_id' => 100005, 'type' => 'shopVirtualGoods', 'data' => ['order_goods_id' => 31],
    ]), '核销前后ERP事实发生变化时回滚次数');
    check(Store::$tables['lines'][0]['verify_count'] === 0, '完成闸门二次拒绝后核销次数恢复');
    fixture(['status' => OrderDict::WAIT_PAY, 'relate_source' => 'SO123']); providerState();
    rejected(fn() => finish(), 'ERP镜像WAIT_PAY不跳过履约', '交付');
    fixture(['status' => OrderDict::CLOSE]); providerState();
    rejected(fn() => finish(), '退回关闭订单不可复活', '关闭');
    fixture(); providerState(); Store::$tables['lines'][0]['status'] = OrderGoodsDict::REFUNDING;
    rejected(fn() => finish(), '退款中不可完成', 'REFUNDING');

    fixture(); providerState();
    Store::$tables['orders'][] = array_replace(Store::$tables['orders'][0], ['site_id' => 100006]);
    Store::$tables['lines'][] = array_replace(Store::$tables['lines'][0], ['site_id' => 100006]);
    Store::$tables['lines'][] = array_replace(Store::$tables['lines'][0], ['order_goods_id' => 32, 'extend' => '{"erp_return":{"received":true}}']);
    check(finish(), '结清且已交付允许完成');
    check(Store::$tables['orders'][0]['status'] === OrderDict::FINISH && Store::$tables['orders'][0]['timeout'] === 0, '完成清理计时器');
    check(Store::$tables['lines'][0]['delivery_status'] === OrderDeliveryDict::TAKED, '有效本机明细确认收货');
    check(Store::$tables['lines'][1]['delivery_status'] === OrderDeliveryDict::DELIVERY_FINISH, '不污染其他站同号设备');
    check(Store::$tables['lines'][2]['delivery_status'] === OrderDeliveryDict::DELIVERY_FINISH, '已退回设备不再标记客户收货');
    check(Store::$tables['orders'][1]['status'] === OrderDict::WAIT_TAKE, '不污染其他站同号订单');
    check(count(Store::$events) === 2 && count(Store::$requests) === 1, '首次完成触发一次完成及后置事件');
    check(Store::$locks[0][0] === 'orders' && Store::$locks[0][1] > 0, '订单行锁处于事务内');
    Store::$responses = [];
    check(finish() && count(Store::$events) === 2 && count(Store::$requests) === 1, '重复完成幂等，不重复查询和事件');

    fixture(); providerState(); Store::$finishEventThrows = true;
    $before = Store::$tables;
    try { finish(); throw new \LogicException('事件故障应阻止事务提交'); }
    catch (\RuntimeException $e) { check($e->getMessage() === '测试完成事件失败', '事件失败抛出原因'); }
    check(Store::$tables === $before && Store::$transactionDepth === 0, '完成事件失败回滚订单和明细');

    fixture(['payment_mode' => 'online', 'is_credit' => 0]); Store::$providerThrows = true;
    check(finish() && Store::$requests === [], '线上已交付完成不依赖 ERP 挂账契约');
    fixture(['payment_mode' => 'offline_cash', 'is_credit' => 0]); Store::$providerThrows = true;
    check(finish() && Store::$requests === [], '现结已交付完成沿用原流程');

    fixture(['status' => OrderDict::WAIT_DELIVERY]);
    Store::$tables['lines'][0]['delivery_status'] = OrderDeliveryDict::WAIT_DELIVERY;
    $result = deliver();
    check($result['status'] === OrderDict::WAIT_TAKE && Store::$tables['orders'][0]['status'] === OrderDict::WAIT_TAKE, '挂账交付返回和实际状态均为待收货');
    check($result['next_action'] === 'collection' && Store::$events === [], '交付未冒充收款或订单完成');
    check(Store::$tables['offline_records'][0]['status'] === 'delivered' && count(Store::$deliveries) === 1, '真实交付责任记录保留');
    fixture(['status' => OrderDict::WAIT_DELIVERY]); providerState();
    Store::$tables['lines'][0]['delivery_status'] = OrderDeliveryDict::WAIT_DELIVERY;
    $result = deliver();
    check($result['status'] === OrderDict::WAIT_TAKE && $result['next_action'] === 'finish' && Store::$events === [], '先已结清的挂账交付后提示可完成而非直接完成');
    fixture(['status' => OrderDict::WAIT_DELIVERY, 'payment_mode' => 'offline_cash', 'is_credit' => 0]);
    Store::$tables['offline_records'][0]['status'] = 'paid';
    Store::$tables['lines'][0]['delivery_status'] = OrderDeliveryDict::WAIT_DELIVERY;
    $result = deliver();
    check($result['status'] === OrderDict::FINISH && Store::$tables['orders'][0]['status'] === OrderDict::FINISH, '现结自提正常完成');
    check($result['next_action'] === 'completed' && count(Store::$events) === 2, '现结完成事件维持原流程');

    foreach ([false, true] as $settled) {
        fixture(['status' => OrderDict::WAIT_PAY, 'relate_source' => 'SO123']);
        if ($settled) providerState();
        $before = Store::$tables;
        $result = (new CoreOfflineSaleService())->finalizeByOutbound(100005, 'SO123');
        check(Store::$tables === $before && Store::$events === [], 'ERP sold 不直接完成或伪造收款：' . ($settled ? '已结清' : '未结清'));
        check($result['can_complete'] === $settled, 'ERP sold 仅读真实财务契约');
    }
    fixture(['relate_source' => 'SO123']); providerState();
    $result = (new CoreOfflineSaleService())->finalizeByOutbound(100006, 'SO123');
    check(!empty($result['skipped']) && Store::$requests === [], 'ERP sold 不跨站寻找订单');
    fixture(['status' => OrderDict::CLOSE, 'relate_source' => 'SO123']); providerState();
    $result = (new CoreOfflineSaleService())->finalizeByOutbound(100005, 'SO123');
    check(!empty($result['skipped']) && Store::$tables['orders'][0]['status'] === OrderDict::CLOSE, 'ERP sold 不复活已退回订单');

    // 以下只做源契约检查；不是退款、计划任务、微信核销或真实物流的执行测试。
    $auto = file_get_contents($plugin . '/app/job/order/OrderFinish.php');
    check(strpos($auto, 'CoreOrderFinishService())->finish') !== false, '定时完成继续通过统一闸门');
    $close = file_get_contents($plugin . '/app/service/core/order/CoreOrderCloseService.php');
    check(strpos($close, 'OrderDict::AUTO_CLOSE') !== false && strpos($close, 'OrderDict::REFUND_CLOSE') !== false, '保留线上未付自动关闭与退款关闭分支');
    printf("[通过] %d 项挂账完成隔离回归；执行真实完成、核销、财务契约消费、自提交付编排与ERP sold核对服务。\n", $checks);
    echo "[边界] 内存 ORM / 交付与事件依赖替身；不验证 MySQL 并发、真实 ERP 金额聚合、物流、微信发货上传、队列或线上订单。\n";
}
