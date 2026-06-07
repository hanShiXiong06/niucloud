<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\dashboard;

use addon\hsx_recycle\app\dict\dashboard\RecycleDashboardFilterDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\RecycleDateRangeService;
use core\base\BaseAdminService;

/**
 * 回收看板统一过滤服务
 *
 * 看板统计和订单列表下钻必须共用这个服务，避免出现看板数字和列表数量不一致。
 */
class RecycleDashboardFilterService extends BaseAdminService
{
    public const DEFAULT_CHECK_TIMEOUT_HOURS = 24;
    public const DEFAULT_QUOTE_TIMEOUT_HOURS = 2;
    public const DEFAULT_PAY_TIMEOUT_HOURS = 2;
    public const DEFAULT_HIGH_COST_AMOUNT = 3000;

    public function getFilters(): array
    {
        return RecycleDashboardFilterDict::getList();
    }

    public function getFilterMeta(string $filterKey): array
    {
        return RecycleDashboardFilterDict::get($filterKey);
    }

    public function normalizeParams(array $params = []): array
    {
        $today = date('Y-m-d');
        $range = RecycleDateRangeService::normalizeRange($params['start_time'] ?? $today, $params['end_time'] ?? $today);

        return [
            'start_time' => $range['start_time'],
            'end_time' => $range['end_time'],
            'start_at' => $range['start_at'],
            'end_at' => $range['end_at'],
            'check_timeout_hours' => max(1, (int)($params['check_timeout_hours'] ?? self::DEFAULT_CHECK_TIMEOUT_HOURS)),
            'quote_timeout_hours' => max(1, (int)($params['quote_timeout_hours'] ?? self::DEFAULT_QUOTE_TIMEOUT_HOURS)),
            'pay_timeout_hours' => max(1, (int)($params['pay_timeout_hours'] ?? self::DEFAULT_PAY_TIMEOUT_HOURS)),
            'high_cost_amount' => max(0, (float)($params['high_cost_amount'] ?? self::DEFAULT_HIGH_COST_AMOUNT)),
        ];
    }

    public function newOrderQuery(array $params = [])
    {
        return (new RecycleOrder())
            ->where([
                ['site_id', '=', $this->site_id],
                ['delete_at', '=', 0],
            ]);
    }

    public function newDeviceQuery(array $params = [])
    {
        return (new RecycleDevice())->where([['site_id', '=', $this->site_id]]);
    }

    public function applyOrderFilter($query, string $filterKey, array $params = [])
    {
        if ($filterKey === '') {
            return $query;
        }

        $params = $this->normalizeParams($params);

        switch ($filterKey) {
            case RecycleDashboardFilterDict::TODAY_CREATED_ORDERS:
            case RecycleDashboardFilterDict::TODAY_CREATED_DEVICES:
                return $query->where('create_at', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::SIGNED_TODAY:
                return $query
                    ->where('sign_at', '>', 0)
                    ->where('sign_at', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::DEVICE_PENDING_CHECK:
                return $this->whereOrderHasDeviceStatus($query, RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK);

            case RecycleDashboardFilterDict::DEVICE_CHECKING:
                return $this->whereOrderHasDeviceStatus($query, RecycleOrderDict::DEVICE_STATUS_CHECKING);

            case RecycleDashboardFilterDict::PENDING_SIGN:
                return $query->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN);

            case RecycleDashboardFilterDict::PENDING_CHECK:
                return $query->where('status', 'in', [
                    RecycleOrderDict::ORDER_STATUS_SIGNED,
                    RecycleOrderDict::ORDER_STATUS_CHECKING,
                ]);

            case RecycleDashboardFilterDict::CHECK_TIMEOUT:
                return $query
                    ->where('status', 'in', [
                        RecycleOrderDict::ORDER_STATUS_SIGNED,
                        RecycleOrderDict::ORDER_STATUS_CHECKING,
                    ])
                    ->where('sign_at', '>', 0)
                    ->where('sign_at', '<=', time() - $params['check_timeout_hours'] * 3600);

            case RecycleDashboardFilterDict::PENDING_QUOTE:
                return $query->where('status', '=', RecycleOrderDict::ORDER_STATUS_CHECKED);

            case RecycleDashboardFilterDict::QUOTE_TIMEOUT:
                return $query
                    ->where('status', '=', RecycleOrderDict::ORDER_STATUS_CHECKED)
                    ->where('update_at', '<=', time() - $params['quote_timeout_hours'] * 3600);

            case RecycleDashboardFilterDict::PENDING_CONFIRM:
                return $query->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM);

            case RecycleDashboardFilterDict::PENDING_PAY:
                return $query
                    ->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT)
                    ->where('pay_time', '=', 0);

            case RecycleDashboardFilterDict::PAY_TIMEOUT:
                return $query
                    ->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT)
                    ->where('pay_time', '=', 0)
                    ->where('confirm_time', '>', 0)
                    ->where('confirm_time', '<=', time() - $params['pay_timeout_hours'] * 3600);

            case RecycleDashboardFilterDict::PAID_TODAY:
                return $query
                    ->where('pay_time', '>', 0)
                    ->where('pay_time', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::COMPLETED_TODAY:
                return $query
                    ->where('status', '=', RecycleOrderDict::ORDER_STATUS_COMPLETED)
                    ->where('complete_at', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::RETURNED_ORDERS:
            case RecycleDashboardFilterDict::RETURNED_DEVICES:
                return $this->whereOrderHasDeviceStatus($query, RecycleOrderDict::DEVICE_STATUS_RETURNED, $params);

            case RecycleDashboardFilterDict::PENDING_RETURN:
                return $query->where('id', 'in', function ($subQuery) {
                    $subQuery->name('recycle_device')
                        ->field('order_id')
                        ->where('site_id', '=', $this->site_id)
                        ->where('dispose_type', '=', RecycleOrderDict::DISPOSE_TYPE_RETURN)
                        ->where('status', '<>', RecycleOrderDict::DEVICE_STATUS_RETURNED);
                });

            case RecycleDashboardFilterDict::INVENTORY_DEVICES:
                return $this->whereOrderHasDeviceStatus($query, RecycleOrderDict::DEVICE_STATUS_RECYCLED);

            case RecycleDashboardFilterDict::HIGH_COST_DEVICES:
                return $this->whereOrderHasHighCostDevice($query, $params['high_cost_amount']);

            case RecycleDashboardFilterDict::QUOTE_DECIDED:
                return $this->whereOrderHasQuotedDevice($query, $params);

            case RecycleDashboardFilterDict::QUOTE_CONFIRMED:
                return $this->whereOrderHasQuotedDevice($query, $params)
                    ->where('status', 'in', [
                        RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
                        RecycleOrderDict::ORDER_STATUS_COMPLETED,
                    ]);

            default:
                return $query;
        }
    }

    public function applyDeviceFilter($query, string $filterKey, array $params = [])
    {
        if ($filterKey === '') {
            return $query;
        }

        $params = $this->normalizeParams($params);

        switch ($filterKey) {
            case RecycleDashboardFilterDict::TODAY_CREATED_DEVICES:
                return $query->where('order_id', 'in', function ($subQuery) use ($params) {
                    $subQuery->name('recycle_order')
                        ->field('id')
                        ->where([
                            ['site_id', '=', $this->site_id],
                            ['delete_at', '=', 0],
                            ['create_at', 'between', [$params['start_at'], $params['end_at']]],
                        ]);
                });

            case RecycleDashboardFilterDict::SIGNED_TODAY:
                return $query
                    ->where('order_id', 'in', function ($subQuery) use ($params) {
                        $subQuery->name('recycle_order')
                            ->field('id')
                            ->where([
                                ['site_id', '=', $this->site_id],
                                ['delete_at', '=', 0],
                                ['sign_at', '>', 0],
                                ['sign_at', 'between', [$params['start_at'], $params['end_at']]],
                            ]);
                    });

            case RecycleDashboardFilterDict::DEVICE_PENDING_CHECK:
                return $query->where('status', '=', RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK);

            case RecycleDashboardFilterDict::DEVICE_CHECKING:
                return $query->where('status', '=', RecycleOrderDict::DEVICE_STATUS_CHECKING);

            case RecycleDashboardFilterDict::PENDING_CHECK:
            case RecycleDashboardFilterDict::CHECK_TIMEOUT:
                return $query->where('status', 'in', [
                    RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                    RecycleOrderDict::DEVICE_STATUS_CHECKING,
                ]);

            case RecycleDashboardFilterDict::PENDING_QUOTE:
                return $query->where('status', 'in', [
                    RecycleOrderDict::DEVICE_STATUS_CHECKED,
                    RecycleOrderDict::DEVICE_STATUS_PRICED,
                    RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE,
                ]);

            case RecycleDashboardFilterDict::PENDING_CONFIRM:
                return $query
                    ->where('status', '=', RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM)
                    ->where('confirm_status', '=', RecycleOrderDict::CONFIRM_STATUS_PENDING);

            case RecycleDashboardFilterDict::PENDING_PAY:
                return $query
                    ->where('order_id', 'in', function ($subQuery) {
                        $subQuery->name('recycle_order')
                            ->field('id')
                            ->where([
                                ['site_id', '=', $this->site_id],
                                ['delete_at', '=', 0],
                                ['status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT],
                                ['pay_time', '=', 0],
                            ]);
                    });

            case RecycleDashboardFilterDict::PAID_TODAY:
                return $query
                    ->where('order_id', 'in', function ($subQuery) use ($params) {
                        $subQuery->name('recycle_order')
                            ->field('id')
                            ->where([
                                ['site_id', '=', $this->site_id],
                                ['delete_at', '=', 0],
                                ['pay_time', '>', 0],
                                ['pay_time', 'between', [$params['start_at'], $params['end_at']]],
                            ]);
                    });

            case RecycleDashboardFilterDict::INVENTORY_DEVICES:
                return $query->where('status', '=', RecycleOrderDict::DEVICE_STATUS_RECYCLED);

            case RecycleDashboardFilterDict::HIGH_COST_DEVICES:
                return $query
                    ->where('final_price', '>=', $params['high_cost_amount'])
                    ->where('final_price', '>', 0);

            case RecycleDashboardFilterDict::RETURNED_DEVICES:
            case RecycleDashboardFilterDict::RETURNED_ORDERS:
                return $query
                    ->where('status', '=', RecycleOrderDict::DEVICE_STATUS_RETURNED)
                    ->where('update_at', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::PENDING_RETURN:
                return $query
                    ->where('dispose_type', '=', RecycleOrderDict::DISPOSE_TYPE_RETURN)
                    ->where('status', '<>', RecycleOrderDict::DEVICE_STATUS_RETURNED);

            default:
                return $query;
        }
    }

    public function countOrders(string $filterKey, array $params = []): int
    {
        $query = $this->applyOrderFilter($this->newOrderQuery($params), $filterKey, $params);
        return (int)$query->count();
    }

    public function getOrderIds(string $filterKey, array $params = []): array
    {
        $query = $this->applyOrderFilter($this->newOrderQuery($params), $filterKey, $params);
        return array_values(array_unique(array_map('intval', $query->column('id'))));
    }

    public function countDevices(string $filterKey, array $params = []): int
    {
        $query = $this->applyDeviceFilter($this->newDeviceQuery($params), $filterKey, $params);
        return (int)$query->count();
    }

    public function sumDeviceFinalPrice(string $filterKey, array $params = []): float
    {
        $query = $this->applyDeviceFilter($this->newDeviceQuery($params), $filterKey, $params);
        return round((float)$query->where('final_price', '>', 0)->sum('final_price'), 2);
    }

    private function whereOrderHasDeviceStatus($query, int $status, ?array $params = null)
    {
        return $query->where('id', 'in', function ($subQuery) use ($status, $params) {
            $subQuery->name('recycle_device')
                ->field('order_id')
                ->where('site_id', '=', $this->site_id)
                ->where('status', '=', $status);

            if ($params !== null && $status === RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                $subQuery->where('update_at', 'between', [$params['start_at'], $params['end_at']]);
            }
        });
    }

    private function whereOrderHasQuotedDevice($query, array $params)
    {
        return $query->where('id', 'in', function ($subQuery) use ($params) {
            $subQuery->name('recycle_device')
                ->field('order_id')
                ->where('site_id', '=', $this->site_id)
                ->where('final_price', '>', 0)
                ->where(function ($timeQuery) use ($params) {
                    $timeQuery
                        ->where('final_price_at', 'between', [$params['start_at'], $params['end_at']])
                        ->whereOr('price_at', 'between', [$params['start_at'], $params['end_at']])
                        ->whereOr('update_at', 'between', [$params['start_at'], $params['end_at']]);
                });
        });
    }

    private function whereOrderHasHighCostDevice($query, float $amount)
    {
        return $query->where('id', 'in', function ($subQuery) use ($amount) {
            $subQuery->name('recycle_device')
                ->field('order_id')
                ->where('site_id', '=', $this->site_id)
                ->where('final_price', '>=', $amount)
                ->where('final_price', '>', 0);
        });
    }
}
