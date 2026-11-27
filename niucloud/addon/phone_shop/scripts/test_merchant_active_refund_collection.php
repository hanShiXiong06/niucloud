<?php
declare(strict_types=1);

// Standalone CLI regression for the merchant refund action and ORM Collection/Model;
// database, transactions and refund events are replaced by in-memory doubles.
namespace {
    if (PHP_SAPI !== 'cli') { exit(1); }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    class RefundGoodsFixture extends \think\Model
    {
        public static array $saves = [];

        public function save(array|object $data = [], ?string $sequence = null): bool
        {
            self::$saves[] = $this['order_goods_id'];
            foreach ($data as $key => $value) { $this[$key] = $value; }
            return true;
        }
    }

    function checkRefund(bool $valid, string $message): void
    {
        if (!$valid) { throw new \RuntimeException($message); }
    }
}

namespace core\base {
    class BaseCoreService { protected $model; public function __construct() {} }
}
namespace core\exception {
    class CommonException extends \RuntimeException {}
}
namespace think\facade {
    class Db
    {
        public static array $calls = [];
        public static function startTrans() { self::$calls[] = 'begin'; }
        public static function commit() { self::$calls[] = 'commit'; }
        public static function rollback() { self::$calls[] = 'rollback'; }
    }
}
namespace addon\phone_shop\app\model\order {
    class Order
    {
        public static array $erpIds = [], $checkedIds = [];
        public function whereIn($field, $ids) { \checkRefund($field === 'order_id', 'wrong parent lookup'); self::$checkedIds = $ids; return $this; }
        public function where(...$args) { return $this; }
        public function count() { return count(array_intersect(self::$checkedIds, self::$erpIds)); }
    }
    class OrderRefund
    {
        public static array $created = [];
        public function create(array $values) { self::$created[] = $values; return new \RefundGoodsFixture($values); }
    }
}
namespace addon\phone_shop\app\service\core\refund {
    class CoreRefundService
    {
        public static \think\model\Collection $goods;
        public static array $events = [];
        public function refundCheck($ids, $amount)
        {
            return ['refund_money' => 7.10, 'order_goods_list' => self::$goods];
        }
    }
    function create_no() { static $no = 0; return 'FAKE-' . ++$no; }
    function get_lang($key) { return $key; }
    function event($name, $data) { CoreRefundService::$events[] = [$name, $data]; }
}
namespace {
    use addon\phone_shop\app\model\order\{Order, OrderRefund};
    use addon\phone_shop\app\service\core\refund\{CoreRefundActionService, CoreRefundService};
    use think\facade\Db;
    use think\model\Collection;

    function exerciseRefund(array $parents, array $erpIds, string $amount, ?string $error): void
    {
        Db::$calls = OrderRefund::$created = RefundGoodsFixture::$saves = CoreRefundService::$events = Order::$checkedIds = [];
        Order::$erpIds = $erpIds;
        $ids = [101, 102];
        $rows = [];
        foreach ($parents as $i => $orderId) {
            $rows[] = new RefundGoodsFixture(['order_id' => $orderId, 'order_goods_id' => $ids[$i],
                'item_refund_money' => ['4.77', '2.33'][$i], 'is_refund_delivery' => 0, 'member_id' => 12]);
        }
        CoreRefundService::$goods = new Collection($rows);
        try {
            $ok = (new CoreRefundActionService())->shopActiveRefund(['site_id' => 100003, 'order_goods_ids' => $ids,
                'shop_active_refund_money' => $amount, 'shop_active_refund_remark' => 'test']);
        } catch (\core\exception\CommonException $e) {
            checkRefund($error !== null && str_contains($e->getMessage(), $error), 'unexpected rejection: ' . $e->getMessage());
            checkRefund(!Db::$calls && !OrderRefund::$created && !RefundGoodsFixture::$saves && !CoreRefundService::$events, 'rejection had side effects');
            return;
        }
        checkRefund($error === null && $ok === true, 'expected a rejection or true result');
        checkRefund(Order::$checkedIds === array_values(array_unique($parents)), 'parent IDs were not deduplicated');
        checkRefund(RefundGoodsFixture::$saves === $ids && array_column(OrderRefund::$created, 'order_goods_id') === $ids, 'items not each saved once');
        checkRefund(array_column(OrderRefund::$created, 'money') === ['4.77', '2.33'], 'incorrect item refund amounts');
        checkRefund(array_column(CoreRefundService::$events, 0) === array_fill(0, 2, 'AfterPhoneShopOrderRefundActiveCreate'), 'incorrect refund events');
        checkRefund(Db::$calls === ['begin', 'commit', 'begin', 'commit'], 'incorrect transaction sequence');
    }

    exerciseRefund([10, 10], [], '7.10', null);
    exerciseRefund([10, 20], [20], '7.10', 'ERP');
    exerciseRefund([10, 10], [], '7.09', '金额');
    echo "OK: multiple goods on same order, mixed ERP batch blocked, stale amount blocked (no live refunds).\n";
}
