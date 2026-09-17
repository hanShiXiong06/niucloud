<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\dashboard;

use addon\hsx_recycle\app\dict\dashboard\RecycleDashboardFilterDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\order\RecycleReturnOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\RecycleDateRangeService;
use addon\hsx_recycle\app\service\core\stat\CoreRecycleWorkloadService;
use core\base\BaseAdminService;
use think\facade\Db;

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
        // 历史业务量保留取消订单的真实记录；当前待办另由 workload 排除已终结订单。
        return (new RecycleDevice())->where('site_id', '=', $this->site_id)
            ->whereIn('order_id', function ($sub) {
                $sub->name('recycle_order')->where('site_id', '=', $this->site_id)
                    ->where('delete_at', '=', 0)->field('id');
            });
    }

    public function applyOrderFilter($query, string $filterKey, array $params = [])
    {
        if ($filterKey === '') {
            return $query;
        }

        $params = $this->normalizeParams($params);

        if ($this->currentStage($filterKey) !== '') {
            return (new CoreRecycleWorkloadService())->applyValidOrderFilter($query, $this->site_id)
                ->whereIn('id', function ($sub) use ($filterKey, $params) {
                    $this->applyCurrentDeviceFilter($sub->name('recycle_device'), $filterKey, $params)->field('order_id');
                });
        }

        switch ($filterKey) {
            case RecycleDashboardFilterDict::TODAY_CREATED_ORDERS:
            case RecycleDashboardFilterDict::TODAY_CREATED_DEVICES:
                return $query->where('create_at', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::SIGNED_TODAY:
                return $query
                    ->where('sign_at', '>', 0)
                    ->where('sign_at', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::PENDING_SIGN:
                return $query->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN)
                    ->where('delivery_type', '<>', RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE);

            case RecycleDashboardFilterDict::PAID_TODAY:
                return $query->whereIn('id', function ($sub) use ($params) {
                    $sub->name('recycle_device_payment')->where('site_id', '=', $this->site_id)
                        ->where('pay_time', '>', 0)->where('amount', '>', 0)
                        ->where('pay_time', 'between', [$params['start_at'], $params['end_at']])->field('order_id');
                });

            case RecycleDashboardFilterDict::COMPLETED_TODAY:
                return $query
                    ->where('status', '=', RecycleOrderDict::ORDER_STATUS_COMPLETED)
                    ->where('complete_at', 'between', [$params['start_at'], $params['end_at']]);

            case RecycleDashboardFilterDict::RETURNED_ORDERS:
            case RecycleDashboardFilterDict::RETURNED_DEVICES:
                return $query->whereIn('id', function ($sub) use ($params) {
                    $this->completedReturnDeviceQuery($params, $sub)->field('ro.order_id');
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

        if ($this->currentStage($filterKey) !== '') {
            return $this->applyCurrentDeviceFilter($query, $filterKey, $params);
        }

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

            case RecycleDashboardFilterDict::PAID_TODAY:
                return $query->whereIn('id', function ($sub) use ($params) {
                    $sub->name('recycle_device_payment')->where('site_id', '=', $this->site_id)
                        ->where('pay_time', '>', 0)->where('amount', '>', 0)
                        ->where('pay_time', 'between', [$params['start_at'], $params['end_at']])->field('device_id');
                });

            case RecycleDashboardFilterDict::INVENTORY_DEVICES:
                return $query->where('status', '=', RecycleOrderDict::DEVICE_STATUS_RECYCLED);

            case RecycleDashboardFilterDict::HIGH_COST_DEVICES:
                return $query
                    ->where('final_price', '>=', $params['high_cost_amount'])
                    ->where('final_price', '>', 0);

            case RecycleDashboardFilterDict::RETURNED_DEVICES:
            case RecycleDashboardFilterDict::RETURNED_ORDERS:
                return $query->whereIn('id', function ($sub) use ($params) {
                    $this->completedReturnDeviceQuery($params, $sub)->field('rd.device_id');
                });

            default:
                return $query;
        }
    }

    public function countOrders(string $filterKey, array $params = []): int
    {
        $query = $this->applyOrderFilter($this->newOrderQuery($params), $filterKey, $params);
        return (int)$query->count();
    }

    /**
     * 已退回客户的共同口径，供数量、责任分布和下钻明细复用。
     * 设备 status=6 在申请退回时已写入，不能当作完成凭据；以当前有效退回单及明细完成为准。
     */
    public function completedReturnDeviceQuery(array $params = [], $query = null)
    {
        $params = $this->normalizeParams($params);
        $query = $query ?? Db::name('recycle_return_device');

        return $query->name('recycle_return_device')->alias('rd')
            ->join([Db::name('recycle_return_order')->getTable() => 'ro'], 'rd.return_order_id = ro.id')
            ->join([Db::name('recycle_device')->getTable() => 'd'], 'd.id = rd.device_id AND d.return_order_id = ro.id AND d.order_id = ro.order_id')
            ->join([Db::name('recycle_order')->getTable() => 'o'], 'o.id = ro.order_id')
            ->where('ro.site_id', '=', $this->site_id)
            ->where('d.site_id', '=', $this->site_id)
            ->where('o.site_id', '=', $this->site_id)
            ->where('ro.delete_at', '=', 0)
            ->where('o.delete_at', '=', 0)
            ->where('ro.status', '=', RecycleReturnOrderDict::ORDER_STATUS_COMPLETED)
            // recycle_return_device 的状态独立于设备状态：0 待退货、1 退货中、2 已退货。
            ->where('rd.status', '=', 2)
            // 退回完成时间是 DATETIME（不同于设备 update_at 的 Unix 秒数），NULL 自然排除。
            ->where('ro.over_at', 'between', [date('Y-m-d H:i:s', $params['start_at']), date('Y-m-d H:i:s', $params['end_at'])]);
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
        if ($filterKey === RecycleDashboardFilterDict::PENDING_PAY) {
            return (new CoreRecycleWorkloadService())->pendingPayAmount($this->site_id);
        }
        $query = $this->applyDeviceFilter($this->newDeviceQuery($params), $filterKey, $params);
        return round((float)$query->where('final_price', '>', 0)->sum('final_price'), 2);
    }

    private function currentStage(string $filterKey): string
    {
        return [
            RecycleDashboardFilterDict::DEVICE_PENDING_CHECK => RecycleStageDict::STAGE_CHECK,
            RecycleDashboardFilterDict::DEVICE_CHECKING => RecycleStageDict::STAGE_CHECK,
            RecycleDashboardFilterDict::PENDING_CHECK => RecycleStageDict::STAGE_CHECK,
            RecycleDashboardFilterDict::CHECK_TIMEOUT => RecycleStageDict::STAGE_CHECK,
            RecycleDashboardFilterDict::PENDING_QUOTE => RecycleStageDict::STAGE_PRICE,
            RecycleDashboardFilterDict::QUOTE_TIMEOUT => RecycleStageDict::STAGE_PRICE,
            RecycleDashboardFilterDict::PENDING_CONFIRM => RecycleStageDict::STAGE_CONFIRM,
            RecycleDashboardFilterDict::PENDING_PAY => RecycleStageDict::STAGE_PAY,
            RecycleDashboardFilterDict::PAY_TIMEOUT => RecycleStageDict::STAGE_PAY,
            RecycleDashboardFilterDict::PENDING_RETURN => RecycleStageDict::STAGE_ABNORMAL,
        ][$filterKey] ?? '';
    }

    private function applyCurrentDeviceFilter($query, string $filterKey, array $params)
    {
        $query = (new CoreRecycleWorkloadService())->applyDeviceStages($query, $this->site_id, [$this->currentStage($filterKey)]);
        if ($filterKey === RecycleDashboardFilterDict::DEVICE_PENDING_CHECK) {
            $query->where('status', '=', RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK);
        } elseif ($filterKey === RecycleDashboardFilterDict::DEVICE_CHECKING) {
            $query->where('status', '=', RecycleOrderDict::DEVICE_STATUS_CHECKING);
        } elseif ($filterKey === RecycleDashboardFilterDict::CHECK_TIMEOUT) {
            $query->whereIn('order_id', function ($sub) use ($params) {
                $sub->name('recycle_order')->where('site_id', '=', $this->site_id)
                    ->where('sign_at', '>', 0)->where('sign_at', '<=', time() - $params['check_timeout_hours'] * 3600)->field('id');
            });
        } elseif ($filterKey === RecycleDashboardFilterDict::QUOTE_TIMEOUT) {
            $query->where('update_at', '<=', time() - $params['quote_timeout_hours'] * 3600);
        } elseif ($filterKey === RecycleDashboardFilterDict::PAY_TIMEOUT) {
            $query->where('confirm_time', '>', 0)->where('confirm_time', '<=', time() - $params['pay_timeout_hours'] * 3600);
        }
        return $query;
    }

    private function whereOrderHasDeviceStatus($query, int $status)
    {
        return $query->where('id', 'in', function ($subQuery) use ($status) {
            $subQuery->name('recycle_device')
                ->field('order_id')
                ->where('site_id', '=', $this->site_id)
                ->where('status', '=', $status);

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
