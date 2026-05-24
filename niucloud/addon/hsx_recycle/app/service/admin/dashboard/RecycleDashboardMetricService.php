<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\dashboard;

use addon\hsx_recycle\app\dict\dashboard\RecycleDashboardFilterDict;
use addon\hsx_recycle\app\dict\dashboard\RecycleDashboardMetricDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use core\base\BaseAdminService;

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
