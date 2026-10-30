<?php
declare(strict_types=1);

// 隔离回归：执行真实代下单 create，外围服务和会员查询使用内存替身。
// 不加载框架或 .env，不连接数据库、不调用打印、不产生实际订单。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
}

namespace AgentOrderMemberFixture {
    final class Store
    {
        public static array $members = [], $queries = [], $created = [], $assigned = [];

        public static function reset(): void
        {
            self::$members = [
                ['member_id' => 11, 'site_id' => 100005, 'is_del' => 0, 'delete_time' => 0, 'status' => 1],
                ['member_id' => 12, 'site_id' => 100006, 'is_del' => 0, 'delete_time' => 0, 'status' => 1],
                ['member_id' => 13, 'site_id' => 100005, 'is_del' => 1, 'delete_time' => 0, 'status' => 1],
                ['member_id' => 14, 'site_id' => 100005, 'is_del' => 0, 'delete_time' => 1700000000, 'status' => 1],
                ['member_id' => 15, 'site_id' => 100005, 'is_del' => 0, 'delete_time' => 0, 'status' => 0],
            ];
            self::$queries = self::$created = self::$assigned = [];
        }
    }

    final class MemberQuery
    {
        private array $conditions;

        public function __construct(array $conditions)
        {
            // 与真实 Member 模型的 SoftDelete/defaultSoftDelete=0 查询作用域一致。
            $this->conditions = array_merge([['delete_time', '=', 0]], $conditions);
            Store::$queries[] = $this->conditions;
        }

        public function field(string $unused): self { return $this; }

        public function findOrEmpty(): MemberRow
        {
            foreach (Store::$members as $row) {
                foreach ($this->conditions as [$field, $operator, $value]) {
                    if ($operator !== '=' || ($row[$field] ?? null) != $value) continue 2;
                }
                return new MemberRow($row);
            }
            return new MemberRow([]);
        }
    }

    final class MemberRow
    {
        private array $row;
        public function __construct(array $row) { $this->row = $row; }
        public function isEmpty(): bool { return $this->row === []; }
    }
}

namespace app\model\member {
    final class Member
    {
        public static function where(array $conditions): \AgentOrderMemberFixture\MemberQuery
        {
            return new \AgentOrderMemberFixture\MemberQuery($conditions);
        }
    }
}

namespace core\base {
    class BaseAdminService
    {
        public int $site_id = 100005;
        public int $uid = 7;
    }
}

namespace core\exception {
    class CommonException extends \RuntimeException {}
}

namespace addon\hsx_recycle\app\dict\order {
    final class RecycleOrderDict { public const DELIVERY_TYPE_LOGISTICS_VEHICLE = 3; }
}

namespace addon\hsx_recycle\app\dict\stat {
    final class RecycleStageDict { public const STAGE_PICKUP = 1, STAGE_SIGN = 2; }
}

namespace addon\hsx_recycle\app\service\core\recycle_order {
    final class CoreRecycleOrderService
    {
        public function create(array $data): object
        {
            \AgentOrderMemberFixture\Store::$created[] = $data;
            return (object)['id' => 501, 'order_no' => 'FIXTURE-501', 'delivery_type' => 2];
        }
    }
}

namespace addon\hsx_recycle\app\service\admin\stat {
    final class TaskService
    {
        public static function forSite(int $siteId, int $uid, string $name): self { return new self(); }
        public function assignPreferredOrDefault(int $orderId, int $stage, int $assignee): void
        {
            \AgentOrderMemberFixture\Store::$assigned[] = [$orderId, $stage, $assignee];
        }
    }
}

namespace think\facade {
    final class Db
    {
        public static function name(string $unused): self { return new self(); }
        public function where($field, $value): self { return $this; }
        public function find(): array { return ['real_name' => '测试员工']; }
    }
    final class Log { public static function warning($message, array $context): void {} }
}

namespace {
    use AgentOrderMemberFixture\Store;
    use addon\hsx_recycle\app\service\admin\order\RecycleOrderService;
    use core\exception\CommonException;

    require_once __DIR__ . '/../app/service/core/recycle_order/DeviceEntryImei.php';
    require_once __DIR__ . '/../app/service/admin/order/RecycleOrderService.php';

    function expect(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }

    function service(): RecycleOrderService
    {
        return (new \ReflectionClass(RecycleOrderService::class))->newInstanceWithoutConstructor();
    }

    $passed = 0;
    function check(string $name, callable $test): void
    {
        global $passed;
        Store::reset();
        $test();
        ++$passed;
        echo '[通过] ' . $name . PHP_EOL;
    }

    check('本站未删除客户可下单，站点与代下单来源不能伪造', function (): void {
        $result = service()->create([
            'member_id' => 11, 'site_id' => 100006, 'order_source' => 'customer', 'agent_uid' => 999,
            'devices' => [], 'sign_after_create' => false,
        ]);
        expect($result === ['id' => 501, 'order_no' => 'FIXTURE-501', 'signed' => false], '返回结果不一致');
        expect(count(Store::$created) === 1 && count(Store::$assigned) === 1, '未正常创建/分配');
        expect(Store::$created[0]['site_id'] === 100005, '未固定当前站点');
        expect(Store::$created[0]['order_source'] === 'agent' && Store::$created[0]['agent_uid'] === 7, '未固定代下单来源');
        expect(in_array(['site_id', '=', 100005], Store::$queries[0], true), '查询未限定本站');
    });

    foreach ([12 => '其他站点客户', 13 => '注销标记客户', 14 => '软删除客户', 999 => '不存在的客户'] as $memberId => $label) {
        check($label . '被拒绝且不创建订单/分配任务', function () use ($memberId): void {
            try {
                service()->create(['member_id' => $memberId, 'site_id' => 100006, 'devices' => []]);
                throw new \RuntimeException('错误接受了无效客户');
            } catch (CommonException $e) {
                expect($e->getMessage() === '所选客户不存在或已删除，请重新选择本站客户', '提示不明确');
            }
            expect(Store::$created === [] && Store::$assigned === [], '拒绝后仍产生副作用');
        });
    }

    foreach (['member_id=0' => ['member_id' => 0], '未传 member_id' => []] as $label => $input) {
        check($label . '保留原有创建能力且不查询客户', function () use ($input): void {
            service()->create($input + ['devices' => []]);
            expect(count(Store::$created) === 1 && Store::$queries === [], '破坏了零会员兼容路径');
        });
    }

    check('未删除但停用的客户不被本次新增规则误拦截', function (): void {
        service()->create(['member_id' => '15', 'devices' => []]);
        expect(count(Store::$created) === 1, '超出本次未删除校验范围');
    });

    echo '[结果] ' . $passed . ' 项隔离回归全部通过；未连接数据库、未调用打印。' . PHP_EOL;
}
