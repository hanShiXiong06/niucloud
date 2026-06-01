<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\dashboard;

use addon\hsx_recycle\app\dict\dashboard\RecycleDashboardFilterDict;
use addon\hsx_recycle\app\dict\dashboard\RecycleDashboardMetricDict;
use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\order\RecycleReturnOrderDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleReturnOrder;
use app\model\sys\SysUser;
use app\service\admin\user\UserRoleService;
use core\base\BaseAdminService;
use think\facade\Db;

/**
 * 回收经营看板指标服务
 *
 * 指标结果包含口径和下钻参数，让后台页面能把每个数字解释清楚。
 */
class RecycleDashboardMetricService extends BaseAdminService
{
    protected RecycleDashboardFilterService $filterService;

    public function __construct()
    {
        parent::__construct();
        $this->filterService = new RecycleDashboardFilterService();
    }

    public function getOverview(array $params = []): array
    {
        $params = $this->filterService->normalizeParams($params);
        $cards = [
            $this->todayOrderCount($params),
            $this->todayDeviceCount($params),
            $this->todayPaidAmount($params),
            $this->pendingCheck($params),
            $this->checkTimeout($params),
            $this->pendingPay($params),
            $this->pendingPayAmount($params),
            $this->inventoryRecoveryCost($params),
            $this->quoteConfirmRate($params),
            $this->returnRate($params),
        ];

        return [
            'date_range' => [
                'start_time' => $params['start_time'],
                'end_time' => $params['end_time'],
            ],
            'thresholds' => [
                'check_timeout_hours' => $params['check_timeout_hours'],
                'quote_timeout_hours' => $params['quote_timeout_hours'],
                'pay_timeout_hours' => $params['pay_timeout_hours'],
                'high_cost_amount' => $this->money($params['high_cost_amount']),
            ],
            'cards' => $cards,
            'todo' => $this->buildTodo($cards),
            'today_business' => $this->buildTodayBusiness($params),
            'responsibility' => $this->buildResponsibility($params),
            'explain' => '本看板只统计回收链路真实数据。销售出库未接入前，库存回收成本不等同于利润。',
        ];
    }

    public function getMetrics(): array
    {
        return RecycleDashboardMetricDict::getList();
    }

    public function getTrend(array $params = []): array
    {
        $params = $this->filterService->normalizeParams($params);
        $days = $this->buildDateRange($params['start_time'], $params['end_time']);

        $series = [
            'order_count' => [
                'name' => '新增订单',
                'unit' => '单',
                'type' => 'bar',
                'data' => [],
            ],
            'device_count' => [
                'name' => '新增设备',
                'unit' => '台',
                'type' => 'bar',
                'data' => [],
            ],
            'paid_amount' => [
                'name' => '打款金额',
                'unit' => '元',
                'type' => 'line',
                'data' => [],
            ],
            'quote_confirm_rate' => [
                'name' => '报价确认率',
                'unit' => '%',
                'type' => 'line',
                'data' => [],
            ],
            'return_rate' => [
                'name' => '退货率',
                'unit' => '%',
                'type' => 'line',
                'data' => [],
            ],
        ];

        foreach ($days as $day) {
            $dayParams = array_merge($params, [
                'start_time' => $day,
                'end_time' => $day,
            ]);
            $dayParams = $this->filterService->normalizeParams($dayParams);

            $series['order_count']['data'][] = $this->filterService->countOrders(RecycleDashboardFilterDict::TODAY_CREATED_ORDERS, $dayParams);
            $series['device_count']['data'][] = $this->filterService->countDevices(RecycleDashboardFilterDict::TODAY_CREATED_DEVICES, $dayParams);
            $series['paid_amount']['data'][] = (float)$this->filterService->sumDeviceFinalPrice(RecycleDashboardFilterDict::PAID_TODAY, $dayParams);
            $series['quote_confirm_rate']['data'][] = (float)$this->quoteConfirmRate($dayParams)['value'];
            $series['return_rate']['data'][] = (float)$this->returnRate($dayParams)['value'];
        }

        return [
            'date_range' => [
                'start_time' => $params['start_time'],
                'end_time' => $params['end_time'],
            ],
            'x_axis' => $days,
            'series' => array_values($series),
            'explain' => '趋势图按所选时间逐日统计，最多展示 31 天；当前库存成本属于快照指标，不做历史回放。',
        ];
    }

    private function todayOrderCount(array $params): array
    {
        return $this->metric(
            RecycleDashboardMetricDict::TODAY_ORDER_COUNT,
            $this->filterService->countOrders(RecycleDashboardFilterDict::TODAY_CREATED_ORDERS, $params)
        );
    }

    private function todayDeviceCount(array $params): array
    {
        return $this->metric(
            RecycleDashboardMetricDict::TODAY_DEVICE_COUNT,
            $this->filterService->countDevices(RecycleDashboardFilterDict::TODAY_CREATED_DEVICES, $params)
        );
    }

    private function todayPaidAmount(array $params): array
    {
        return $this->metric(
            RecycleDashboardMetricDict::TODAY_PAID_AMOUNT,
            $this->filterService->sumDeviceFinalPrice(RecycleDashboardFilterDict::PAID_TODAY, $params)
        );
    }

    private function pendingCheck(array $params): array
    {
        $orders = $this->filterService->countOrders(RecycleDashboardFilterDict::PENDING_CHECK, $params);
        $devices = $this->countDevicesByOrderFilter(RecycleDashboardFilterDict::PENDING_CHECK, $params, [
            RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            RecycleOrderDict::DEVICE_STATUS_CHECKING,
        ]);

        return $this->metric(RecycleDashboardMetricDict::PENDING_CHECK, $orders, [
            'secondary' => $this->secondaryDeviceValue($devices, RecycleDashboardFilterDict::PENDING_CHECK),
        ]);
    }

    private function checkTimeout(array $params): array
    {
        $orders = $this->filterService->countOrders(RecycleDashboardFilterDict::CHECK_TIMEOUT, $params);
        $devices = $this->countDevicesByOrderFilter(RecycleDashboardFilterDict::CHECK_TIMEOUT, $params, [
            RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            RecycleOrderDict::DEVICE_STATUS_CHECKING,
        ]);

        return $this->metric(RecycleDashboardMetricDict::CHECK_TIMEOUT, $orders, [
            'secondary' => $this->secondaryDeviceValue($devices, RecycleDashboardFilterDict::CHECK_TIMEOUT),
        ]);
    }

    private function pendingPay(array $params): array
    {
        return $this->metric(
            RecycleDashboardMetricDict::PENDING_PAY,
            $this->filterService->countOrders(RecycleDashboardFilterDict::PENDING_PAY, $params)
        );
    }

    private function pendingPayAmount(array $params): array
    {
        return $this->metric(
            RecycleDashboardMetricDict::PENDING_PAY_AMOUNT,
            $this->filterService->sumDeviceFinalPrice(RecycleDashboardFilterDict::PENDING_PAY, $params)
        );
    }

    private function inventoryRecoveryCost(array $params): array
    {
        $devices = $this->filterService->countDevices(RecycleDashboardFilterDict::INVENTORY_DEVICES, $params);

        return $this->metric(
            RecycleDashboardMetricDict::INVENTORY_RECOVERY_COST,
            $this->filterService->sumDeviceFinalPrice(RecycleDashboardFilterDict::INVENTORY_DEVICES, $params),
            [
                'secondary' => $this->secondaryDeviceValue($devices, RecycleDashboardFilterDict::INVENTORY_DEVICES),
            ]
        );
    }

    private function quoteConfirmRate(array $params): array
    {
        $decided = $this->filterService->countOrders(RecycleDashboardFilterDict::QUOTE_DECIDED, $params);
        $confirmed = $this->filterService->countOrders(RecycleDashboardFilterDict::QUOTE_CONFIRMED, $params);
        $rate = $decided > 0 ? round($confirmed / $decided * 100, 2) : 0.00;

        return $this->metric(RecycleDashboardMetricDict::QUOTE_CONFIRM_RATE, $rate, [
            'numerator' => $confirmed,
            'denominator' => $decided,
        ]);
    }

    private function returnRate(array $params): array
    {
        $returned = $this->filterService->countDevices(RecycleDashboardFilterDict::RETURNED_DEVICES, $params);
        $recycled = $this->filterService->countDevices(RecycleDashboardFilterDict::INVENTORY_DEVICES, $params);
        $base = $returned + $recycled;
        $rate = $base > 0 ? round($returned / $base * 100, 2) : 0.00;

        return $this->metric(RecycleDashboardMetricDict::RETURN_RATE, $rate, [
            'numerator' => $returned,
            'denominator' => $base,
        ]);
    }

    private function metric(string $key, $value, array $extra = []): array
    {
        $definition = RecycleDashboardMetricDict::get($key);
        $filterKey = (string)($definition['filter_key'] ?? '');
        $viewMode = (string)($definition['view_mode'] ?? '');

        $result = [
            'key' => $key,
            'title' => $definition['title'] ?? $key,
            'category' => $definition['category'] ?? '',
            'unit' => $definition['unit'] ?? '',
            'value_type' => $definition['value_type'] ?? 'integer',
            'value' => $this->normalizeValue($value, (string)($definition['value_type'] ?? 'integer')),
            'description' => $definition['description'] ?? '',
            'caliber' => $definition['caliber'] ?? '',
            'time_scope' => $definition['time_scope'] ?? 'snapshot',
            'scope_label' => $definition['scope_label'] ?? '当前状态',
            'drilldown' => [
                'target' => 'order_list',
                'filter_key' => $filterKey,
            ],
        ];

        if ($viewMode !== '') {
            $result['drilldown']['view_mode'] = $viewMode;
        }

        return array_merge($result, $extra);
    }

    private function secondaryDeviceValue(int $value, string $filterKey): array
    {
        return [
            'label' => '涉及设备',
            'value' => $value,
            'unit' => '台',
            'drilldown' => [
                'target' => 'order_list',
                'filter_key' => $filterKey,
                'view_mode' => 'device_expand',
            ],
        ];
    }

    private function countDevicesByOrderFilter(string $filterKey, array $params, array $deviceStatuses = []): int
    {
        $orderIds = $this->filterService->getOrderIds($filterKey, $params);
        if (empty($orderIds)) {
            return 0;
        }

        $query = (new RecycleDevice())->where([
            ['site_id', '=', $this->site_id],
            ['order_id', 'in', $orderIds],
        ]);

        if (!empty($deviceStatuses)) {
            $query->where('status', 'in', $deviceStatuses);
        }

        return (int)$query->count();
    }

    private function buildTodo(array $cards): array
    {
        $todoKeys = [
            RecycleDashboardMetricDict::CHECK_TIMEOUT,
            RecycleDashboardMetricDict::PENDING_CHECK,
            RecycleDashboardMetricDict::PENDING_PAY,
        ];

        $todo = [];
        foreach ($cards as $card) {
            if (in_array($card['key'], $todoKeys, true) && (float)$card['value'] > 0) {
                $todo[] = $card;
            }
        }

        return $todo;
    }

    private function buildTodayBusiness(array $params): array
    {
        $createdDeviceQuery = $this->filterService->applyDeviceFilter(
            $this->filterService->newDeviceQuery($params),
            RecycleDashboardFilterDict::TODAY_CREATED_DEVICES,
            $params
        );
        $createdDeviceIds = array_values(array_map('intval', $createdDeviceQuery->column('id')));

        $baseDeviceQuery = $this->filterService->newDeviceQuery($params)
            ->where('create_at', 'between', [$params['start_at'], $params['end_at']]);

        $categoryRows = (clone $baseDeviceQuery)
            ->field('category_id, COUNT(*) as count')
            ->group('category_id')
            ->select()
            ->toArray();

        $categoryBreakdown = [];
        foreach ($categoryRows as $row) {
            $categoryBreakdown[] = [
                'category_id' => (int)($row['category_id'] ?? 0),
                'category_name' => $this->categoryName((int)($row['category_id'] ?? 0)),
                'count' => (int)($row['count'] ?? 0),
            ];
        }

        $priceRows = [];
        if (!empty($createdDeviceIds)) {
            $priceRows = (new RecycleDevice())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['id', 'in', $createdDeviceIds],
                    ['final_price', '>', 0],
                ])
                ->field('final_price')
                ->select()
                ->toArray();
        }

        $prices = array_values(array_map(static fn($row) => (float)($row['final_price'] ?? 0), $priceRows));
        $priceCount = count($prices);
        $priceMin = $priceCount > 0 ? min($prices) : 0;
        $priceMax = $priceCount > 0 ? max($prices) : 0;
        $priceAvg = $priceCount > 0 ? array_sum($prices) / $priceCount : 0;

        return [
            'order_count' => $this->filterService->countOrders(RecycleDashboardFilterDict::TODAY_CREATED_ORDERS, $params),
            'device_count' => count($createdDeviceIds),
            'recycled_device_count' => (int)(new RecycleDevice())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['status', '=', RecycleOrderDict::DEVICE_STATUS_RECYCLED],
                    ['update_at', 'between', [$params['start_at'], $params['end_at']]],
                ])
                ->count(),
            'sold_device_count' => (int)(new RecycleConsignmentOrder())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['status', 'in', [
                        RecycleConsignmentDict::STATUS_SOLD,
                        RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
                        RecycleConsignmentDict::STATUS_SETTLED,
                    ]],
                    ['sold_time', 'between', [$params['start_at'], $params['end_at']]],
                ])
                ->count(),
            'category_breakdown' => $categoryBreakdown,
            'price_summary' => [
                'count' => $priceCount,
                'min' => $this->money($priceMin),
                'max' => $this->money($priceMax),
                'avg' => $this->money($priceAvg),
                'label' => $priceCount > 0 ? $this->money($priceMin) . ' - ' . $this->money($priceMax) : '0.00 - 0.00',
            ],
            'price_ranges' => $this->buildPriceRanges($createdDeviceIds),
            'consignment' => $this->buildConsignmentOverview($params),
        ];
    }

    private function buildResponsibility(array $params): array
    {
        $tasks = [
            $this->buildPendingSignTask($params),
            $this->buildCheckingTask($params),
            $this->buildPendingQuoteTask($params),
            $this->buildPendingConfirmTask($params),
            $this->buildPendingPayTask($params),
            $this->buildPendingReturnTask($params),
            $this->buildReturnCompletedTask($params),
            $this->buildConsignmentPendingTask(),
            $this->buildConsignmentSellingTask(),
            $this->buildConsignmentSettlementTask(),
        ];

        $totalPendingDevices = 0;
        foreach ($tasks as $task) {
            if (!empty($task['is_pending'])) {
                $totalPendingDevices += (int)($task['device_count'] ?? 0);
            }
        }

        return [
            'total_pending_devices' => $totalPendingDevices,
            'tasks' => $tasks,
            'explain' => '责任角色来自系统角色；未明确到人的任务只显示待处理数量，不虚构岗位。',
        ];
    }

    private function buildPendingSignTask(array $params): array
    {
        $orderIds = $this->filterService->getOrderIds(RecycleDashboardFilterDict::PENDING_SIGN, $params);
        $deviceCount = $this->countDevicesByOrderIds($orderIds);

        return $this->task('pending_sign', '待签收', '', count($orderIds), $deviceCount, [
            $this->owner(0, '待处理', count($orderIds), $deviceCount, 0, ''),
        ], RecycleDashboardFilterDict::PENDING_SIGN);
    }

    private function buildCheckingTask(array $params): array
    {
        $statuses = [
            RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            RecycleOrderDict::DEVICE_STATUS_CHECKING,
        ];
        $owners = $this->groupDevicesByOwner($statuses, 'check_uid', '待处理', '');
        $deviceCount = array_sum(array_column($owners, 'device_count'));

        return $this->task('checking', '待质检/质检中', '', 0, $deviceCount, $owners, RecycleDashboardFilterDict::PENDING_CHECK);
    }

    private function buildPendingQuoteTask(array $params): array
    {
        $statuses = [
            RecycleOrderDict::DEVICE_STATUS_CHECKED,
            RecycleOrderDict::DEVICE_STATUS_PRICED,
            RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE,
        ];
        $owners = $this->groupDevicesByOwner($statuses, 'price_uid', '待处理', '');
        $deviceCount = array_sum(array_column($owners, 'device_count'));

        return $this->task('pending_quote', '待定价/待报价', '', 0, $deviceCount, $owners, RecycleDashboardFilterDict::PENDING_QUOTE);
    }

    private function buildPendingConfirmTask(array $params): array
    {
        $owners = $this->groupDevicesByOwner(
            [RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM],
            'price_uid',
            '待处理',
            '',
            [['confirm_status', '=', RecycleOrderDict::CONFIRM_STATUS_PENDING]]
        );
        $deviceCount = array_sum(array_column($owners, 'device_count'));

        return $this->task('pending_confirm', '待客户确认', '', 0, $deviceCount, $owners, RecycleDashboardFilterDict::PENDING_CONFIRM);
    }

    private function buildPendingPayTask(array $params): array
    {
        $orderIds = $this->filterService->getOrderIds(RecycleDashboardFilterDict::PENDING_PAY, $params);
        $amount = $this->filterService->sumDeviceFinalPrice(RecycleDashboardFilterDict::PENDING_PAY, $params);
        $deviceCount = $this->countDevicesByOrderIds($orderIds);

        return $this->task('pending_pay', '待打款', '', count($orderIds), $deviceCount, [
            $this->owner(0, '待处理', count($orderIds), $deviceCount, $amount, ''),
        ], RecycleDashboardFilterDict::PENDING_PAY, $amount);
    }

    private function buildPendingReturnTask(array $params): array
    {
        $returnOrderTable = (new RecycleReturnOrder())->getTable();
        $rows = Db::name('recycle_return_device')
            ->alias('rd')
            ->join($returnOrderTable . ' ro', 'rd.return_order_id = ro.id')
            ->where([
                ['ro.site_id', '=', $this->site_id],
                ['rd.status', 'in', [
                    RecycleReturnOrderDict::ORDER_STATUS_PENDING,
                    RecycleReturnOrderDict::ORDER_STATUS_RETURNING,
                ]],
            ])
            ->field('COALESCE(ro.operator_uid, 0) as owner_id, COUNT(*) as device_count, COUNT(DISTINCT ro.id) as order_count')
            ->group('owner_id')
            ->select()
            ->toArray();

        $owners = $this->normalizeOwnerRows($rows, '待处理', '');
        $deviceCount = array_sum(array_column($owners, 'device_count'));
        $orderCount = array_sum(array_column($owners, 'order_count'));

        return $this->task('pending_return', '待退货/退货中', '', $orderCount, $deviceCount, $owners, RecycleDashboardFilterDict::PENDING_RETURN, 0, true, [
            'route_path' => '/site/recycle_return_order/list',
            'route_query' => [],
        ]);
    }

    private function buildReturnCompletedTask(array $params): array
    {
        $returnOrderTable = (new RecycleReturnOrder())->getTable();
        $rows = Db::name('recycle_return_device')
            ->alias('rd')
            ->join($returnOrderTable . ' ro', 'rd.return_order_id = ro.id')
            ->where([
                ['ro.site_id', '=', $this->site_id],
                ['rd.status', '=', RecycleReturnOrderDict::ORDER_STATUS_COMPLETED],
                ['ro.over_at', 'between', [$params['start_at'], $params['end_at']]],
            ])
            ->field('COALESCE(ro.operator_uid, 0) as owner_id, COUNT(*) as device_count, COUNT(DISTINCT ro.id) as order_count')
            ->group('owner_id')
            ->select()
            ->toArray();

        $owners = $this->normalizeOwnerRows($rows, '待处理', '');
        $deviceCount = array_sum(array_column($owners, 'device_count'));
        $orderCount = array_sum(array_column($owners, 'order_count'));

        return $this->task('return_completed', '退货完成', '', $orderCount, $deviceCount, $owners, RecycleDashboardFilterDict::RETURNED_DEVICES, 0, false, [
            'route_path' => '/site/recycle_return_order/list',
            'route_query' => ['status' => RecycleReturnOrderDict::ORDER_STATUS_COMPLETED],
        ]);
    }

    private function buildConsignmentPendingTask(): array
    {
        $owners = $this->groupConsignmentByOwner([RecycleConsignmentDict::STATUS_PENDING], '待处理', '');
        $deviceCount = array_sum(array_column($owners, 'device_count'));
        $amount = array_sum(array_map(static fn($owner) => (float)($owner['amount'] ?? 0), $owners));

        return $this->task('consignment_pending', '代卖待上架', '', $deviceCount, $deviceCount, $owners, '', $amount, true, [
            'route_path' => '/site/consignment_order/list',
            'route_query' => ['status' => RecycleConsignmentDict::STATUS_PENDING],
        ]);
    }

    private function buildConsignmentSellingTask(): array
    {
        $owners = $this->groupConsignmentByOwner([RecycleConsignmentDict::STATUS_SELLING], '待处理', '');
        $deviceCount = array_sum(array_column($owners, 'device_count'));
        $amount = array_sum(array_map(static fn($owner) => (float)($owner['amount'] ?? 0), $owners));

        return $this->task('consignment_selling', '代卖中', '', $deviceCount, $deviceCount, $owners, '', $amount, true, [
            'route_path' => '/site/consignment_order/list',
            'route_query' => ['status' => RecycleConsignmentDict::STATUS_SELLING],
        ]);
    }

    private function buildConsignmentSettlementTask(): array
    {
        $owners = $this->groupConsignmentByOwner([
            RecycleConsignmentDict::STATUS_SOLD,
            RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
        ], '待处理', '');
        $deviceCount = array_sum(array_column($owners, 'device_count'));
        $amount = array_sum(array_map(static fn($owner) => (float)($owner['amount'] ?? 0), $owners));

        return $this->task('consignment_pending_settlement', '代卖待结算', '', $deviceCount, $deviceCount, $owners, '', $amount, true, [
            'route_path' => '/site/consignment_order/list',
            'route_query' => ['status' => RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT],
        ]);
    }

    private function task(string $key, string $title, string $role, int $orderCount, int $deviceCount, array $owners, string $filterKey = '', float $amount = 0, bool $isPending = true, array $extra = []): array
    {
        $task = [
            'key' => $key,
            'title' => $title,
            'role' => $role,
            'order_count' => $orderCount,
            'device_count' => $deviceCount,
            'amount' => $this->money($amount),
            'owners' => $owners,
            'is_pending' => $isPending,
            'drilldown' => [
                'target' => 'order_list',
                'filter_key' => $filterKey,
                'view_mode' => 'device_expand',
            ],
        ];

        return array_merge($task, $extra);
    }

    private function owner(int $ownerId, string $ownerName, int $orderCount, int $deviceCount, float $amount, string $roleName): array
    {
        return [
            'owner_id' => $ownerId,
            'owner_name' => $ownerName,
            'role_name' => $roleName,
            'order_count' => $orderCount,
            'device_count' => $deviceCount,
            'amount' => $this->money($amount),
        ];
    }

    private function groupDevicesByOwner(array $statuses, string $ownerField, string $emptyOwnerName, string $roleName = '', array $extraWhere = []): array
    {
        $query = (new RecycleDevice())
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', 'in', $statuses],
            ]);

        foreach ($extraWhere as $where) {
            if (is_array($where) && count($where) >= 3) {
                $query->where($where[0], $where[1], $where[2]);
            }
        }

        $rows = $query
            ->field("COALESCE({$ownerField}, 0) as owner_id, COUNT(*) as device_count, COUNT(DISTINCT order_id) as order_count, SUM(final_price) as amount")
            ->group('owner_id')
            ->select()
            ->toArray();

        return $this->normalizeOwnerRows($rows, $emptyOwnerName, $roleName);
    }

    private function groupConsignmentByOwner(array $statuses, string $emptyOwnerName, string $roleName = ''): array
    {
        $rows = (new RecycleConsignmentOrder())
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', 'in', $statuses],
            ])
            ->field('COALESCE(operator_id, 0) as owner_id, COUNT(*) as device_count, COUNT(*) as order_count, SUM(listing_price) as amount')
            ->group('owner_id')
            ->select()
            ->toArray();

        return $this->normalizeOwnerRows($rows, $emptyOwnerName, $roleName);
    }

    private function normalizeOwnerRows(array $rows, string $emptyOwnerName, string $roleName): array
    {
        $owners = [];
        foreach ($rows as $row) {
            $ownerId = (int)($row['owner_id'] ?? 0);
            $owners[] = $this->owner(
                $ownerId,
                $ownerId > 0 ? $this->userName($ownerId) : $emptyOwnerName,
                (int)($row['order_count'] ?? 0),
                (int)($row['device_count'] ?? 0),
                (float)($row['amount'] ?? 0),
                $ownerId > 0 ? $this->userRoleName($ownerId) : ''
            );
        }

        usort($owners, static fn($a, $b) => (int)$b['device_count'] <=> (int)$a['device_count']);
        return $owners;
    }

    private function countDevicesByOrderIds(array $orderIds): int
    {
        if (empty($orderIds)) {
            return 0;
        }

        return (int)(new RecycleDevice())
            ->where([
                ['site_id', '=', $this->site_id],
                ['order_id', 'in', $orderIds],
            ])
            ->count();
    }

    private function buildPriceRanges(array $deviceIds): array
    {
        $ranges = [
            ['key' => '0_500', 'label' => '0-500', 'min' => 0, 'max' => 500],
            ['key' => '500_1000', 'label' => '500-1000', 'min' => 500, 'max' => 1000],
            ['key' => '1000_3000', 'label' => '1000-3000', 'min' => 1000, 'max' => 3000],
            ['key' => '3000_up', 'label' => '3000以上', 'min' => 3000, 'max' => null],
        ];

        foreach ($ranges as &$range) {
            $query = (new RecycleDevice())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['final_price', '>', 0],
                ]);

            if (!empty($deviceIds)) {
                $query->where('id', 'in', $deviceIds);
            } else {
                $query->where('id', '=', 0);
            }

            $query->where('final_price', '>=', $range['min']);
            if ($range['max'] !== null) {
                $query->where('final_price', '<', $range['max']);
            }

            $range['count'] = (int)$query->count();
            unset($range['min'], $range['max']);
        }
        unset($range);

        return $ranges;
    }

    private function buildConsignmentOverview(array $params): array
    {
        $todayQuery = (new RecycleConsignmentOrder())
            ->where([
                ['site_id', '=', $this->site_id],
                ['create_time', 'between', [$params['start_at'], $params['end_at']]],
            ]);

        return [
            'today_count' => (int)(clone $todayQuery)->count(),
            'pending_count' => $this->countConsignmentByStatus([RecycleConsignmentDict::STATUS_PENDING]),
            'selling_count' => $this->countConsignmentByStatus([RecycleConsignmentDict::STATUS_SELLING]),
            'pending_settlement_count' => $this->countConsignmentByStatus([
                RecycleConsignmentDict::STATUS_SOLD,
                RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
            ]),
            'sold_today_count' => (int)(new RecycleConsignmentOrder())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['status', 'in', [
                        RecycleConsignmentDict::STATUS_SOLD,
                        RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
                        RecycleConsignmentDict::STATUS_SETTLED,
                    ]],
                    ['sold_time', 'between', [$params['start_at'], $params['end_at']]],
                ])
                ->count(),
            'sold_today_amount' => $this->money((float)(new RecycleConsignmentOrder())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['status', 'in', [
                        RecycleConsignmentDict::STATUS_SOLD,
                        RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
                        RecycleConsignmentDict::STATUS_SETTLED,
                    ]],
                    ['sold_time', 'between', [$params['start_at'], $params['end_at']]],
                ])
                ->sum('sold_price')),
        ];
    }

    private function countConsignmentByStatus(array $statuses): int
    {
        return (int)(new RecycleConsignmentOrder())
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', 'in', $statuses],
            ])
            ->count();
    }

    private function categoryName(int $categoryId): string
    {
        if ($categoryId <= 0) {
            return '未分类';
        }

        try {
            $name = (new RecycleDevice())->getCategoryNameAttr('', ['category_id' => $categoryId]);
            return $name !== '' ? $name : '未分类';
        } catch (\Throwable $e) {
            return '未分类';
        }
    }

    private function userName(int $uid): string
    {
        $user = (new SysUser())->where('uid', '=', $uid)->field('uid,username,real_name')->findOrEmpty();
        if ($user->isEmpty()) {
            return '员工 #' . $uid;
        }

        $data = $user->toArray();
        return (string)($data['real_name'] ?: $data['username'] ?: ('员工 #' . $uid));
    }

    private function userRoleName(int $uid): string
    {
        if ($uid <= 0) {
            return '';
        }

        try {
            $userRoleService = new UserRoleService();
            $userRole = $userRoleService->getUserRole((int)$this->site_id, $uid);
            if (empty($userRole)) {
                return '';
            }

            if (!empty($userRole['is_admin'])) {
                return '站点管理员';
            }

            $roleIds = $userRole['role_ids'] ?? [];
            if (is_string($roleIds)) {
                $decoded = json_decode($roleIds, true);
                $roleIds = is_array($decoded) ? $decoded : [];
            }

            $roleIds = array_values(array_filter(array_map('intval', (array)$roleIds)));
            if (empty($roleIds)) {
                return '';
            }

            $roleNames = $userRoleService->getRoleByUserRoleIds($roleIds, (int)$this->site_id);
            return !empty($roleNames) ? implode('、', $roleNames) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function normalizeValue($value, string $type)
    {
        if ($type === 'money') {
            return $this->money($value);
        }

        if ($type === 'percent') {
            return number_format((float)$value, 2, '.', '');
        }

        return (int)$value;
    }

    private function money($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function buildDateRange(string $startTime, string $endTime): array
    {
        $start = strtotime($startTime . ' 00:00:00') ?: strtotime(date('Y-m-d') . ' 00:00:00');
        $end = strtotime($endTime . ' 00:00:00') ?: $start;
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $maxDays = 31;
        $days = [];
        for ($time = $start; $time <= $end && count($days) < $maxDays; $time += 86400) {
            $days[] = date('Y-m-d', $time);
        }

        return $days;
    }
}
