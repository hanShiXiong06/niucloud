<?php
declare(strict_types=1);

// 执行真实 OrderService 分组方法；查询只用内存替身，不加载 .env、不连接数据库。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
}
namespace core\base { class BaseAdminService {} }
namespace core\exception { class AdminException extends \RuntimeException {} }
namespace PhoneShopOrderChannelFixture {
    final class Query
    {
        private array $conditions = [];
        public array $trace = [];

        public function where($field, $operator = null, $value = null): self
        {
            if (is_callable($field)) {
                $group = new self();
                $field($group);
                $this->conditions[] = ['AND', static fn(array $row): bool => $group->matches($row)];
                $this->trace[] = ['group', $group->trace];
                return $this;
            }
            if ($operator !== '=') throw new \RuntimeException('测试仅允许真实用到的等值条件');
            $this->conditions[] = ['AND', static fn(array $row): bool => ($row[$field] ?? null) === $value];
            $this->trace[] = ['where', $field, $operator, $value];
            return $this;
        }
        public function whereIn(string $field, array $values): self
        {
            $this->conditions[] = ['AND', static fn(array $row): bool => in_array($row[$field] ?? null, $values, true)];
            $this->trace[] = ['in', $field, $values];
            return $this;
        }
        public function whereNotIn(string $field, array $values): self
        {
            // SQL NULL NOT IN (...) 不为 TRUE；必须显式 OR NULL。
            $this->conditions[] = ['AND', static fn(array $row): bool => isset($row[$field]) && !in_array($row[$field], $values, true)];
            $this->trace[] = ['not_in', $field, $values];
            return $this;
        }
        public function whereNull(string $field, string $logic = 'AND'): self
        {
            $this->conditions[] = [$logic, static fn(array $row): bool => !isset($row[$field])];
            $this->trace[] = ['null', $field, $logic];
            return $this;
        }
        public function matches(array $row): bool
        {
            $result = null;
            foreach ($this->conditions as [$logic, $condition]) {
                $current = $condition($row);
                $result = $result === null ? $current : ($logic === 'OR' ? ($result || $current) : ($result && $current));
            }
            return $result ?? true;
        }
        public function ids(array $rows): array
        {
            return array_values(array_column(array_filter($rows, fn(array $row): bool => $this->matches($row)), 'id'));
        }
    }
}
namespace {
    require dirname(__DIR__) . '/app/service/admin/order/OrderService.php';
    use addon\phone_shop\app\service\admin\order\OrderService;
    use core\exception\AdminException;
    use PhoneShopOrderChannelFixture\Query;

    $reflection = new ReflectionClass(OrderService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $filter = $reflection->getMethod('applyOrderChannelFilter');
    $filter->setAccessible(true);
    $rows = [];
    foreach ([
        [1, 100005, 'online', 1], [2, 100005, '', 1], [3, 100005, null, 1],
        [4, 100005, 'offline_pending', 1], [5, 100005, 'offline_cash', 2], [6, 100005, 'offline_credit', 5],
        [7, 100005, 'historical_online', 1], [8, 100006, null, 1], [9, 100006, 'offline_cash', 2], [10, 100005, 'online', 5],
    ] as [$id, $site, $mode, $status]) {
        $rows[] = ['id' => $id, 'order.site_id' => $site, 'order.payment_mode' => $mode, 'order.status' => $status];
    }
    $query = static function (array $where, ?int $status = null, ?string $paymentMode = null) use ($filter, $service): Query {
        $q = (new Query())->where('order.site_id', '=', 100005);
        if ($status !== null) $q->where('order.status', '=', $status);
        if ($paymentMode !== null) $q->where('order.payment_mode', '=', $paymentMode);
        $filter->invoke($service, $q, $where);
        return $q;
    };
    $passed = 0;
    $check = static function ($actual, $expected, string $label) use (&$passed): void {
        if ($actual !== $expected) throw new RuntimeException($label . ': ' . json_encode($actual, JSON_UNESCAPED_UNICODE));
        $passed++;
        echo '[通过] ' . $label . PHP_EOL;
    };
    $all = [1, 2, 3, 4, 5, 6, 7, 10];
    $check($query([])->ids($rows), $all, '未传新参数默认本站全部订单');
    $check($query(['order_channel' => ''])->ids($rows), $all, '空参数默认全部');
    $check($query(['order_channel' => 'all'])->ids($rows), $all, '显式全部包含线上与线下');
    $check($query(['order_channel' => 'online'])->ids($rows), [1, 2, 3, 7, 10], '线上排除三种线下且保留NULL空值和历史模式');
    $check($query(['order_channel' => 'offline'])->ids($rows), [4, 5, 6], '线下同时包含待处理收款与挂账');
    $check($query(['offline_workflow' => 1])->ids($rows), [4, 5, 6], '旧PC线下标志继续生效');
    $check($query(['offline_workflow' => '1'])->ids($rows), [4, 5, 6], '旧标志字符串兼容');
    $check($query(['offline_workflow' => 0])->ids($rows), $all, '旧标志为零不限制');
    $check($query(['order_channel' => 'online', 'offline_workflow' => 1])->ids($rows), [1, 2, 3, 7, 10], '明确线上选择优先于旧标志');
    $check($query(['order_channel' => 'all', 'offline_workflow' => 1])->ids($rows), $all, '明确全部选择优先于旧标志');
    $check($query(['order_channel' => ' offline '])->ids($rows), [4, 5, 6], '参数外围空白正常化');
    $check($query(['order_channel' => 'online'], 1)->ids($rows), [1, 2, 3, 7], '线上NULL分支不能绕过状态过滤');
    $check($query(['order_channel' => 'online'], 5)->ids($rows), [10], '完成状态过滤不变');
    $check($query(['order_channel' => 'offline'], 2)->ids($rows), [5], '线下待交付过滤不变');
    $check($query(['order_channel' => 'offline'], null, 'offline_credit')->ids($rows), [6], '保留支付模式细分过滤');
    $check($query(['order_channel' => 'online'], null, 'offline_cash')->ids($rows), [], '不覆盖矛盾的额外过滤条件');
    $check($query(['order_channel' => 'online'], null, '')->ids($rows), [2], 'NULL分支不能绕过额外支付模式条件');
    $check(array_intersect([8, 9], $query(['order_channel' => 'online'])->ids($rows)), [], '线上OR条件组不泄漏跨站NULL订单');
    $check(array_slice($query(['order_channel' => 'online'])->ids($rows), 1, 2), [2, 3], '分组只过滤不改排序分页集合');
    try {
        $query(['order_channel' => 'other']);
        throw new RuntimeException('非法分组未拒绝');
    } catch (AdminException $e) {
        $check($e->getMessage(), '订单类型筛选不正确，请重新选择', '非法分组明确拒绝');
    }
    $controller = file_get_contents(dirname(__DIR__) . '/app/adminapi/controller/order/Order.php');
    $check(strpos($controller, "['order_channel', '']") !== false, true, '控制器声明可选参数');
    echo '[结果] ' . $passed . ' 项隔离回归通过；未连接数据库、未修改订单或支付。' . PHP_EOL;
}
