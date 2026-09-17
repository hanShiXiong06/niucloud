<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\stat;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\order\RecycleReturnOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use think\facade\Db;

/**
 * 当前在途的唯一查询口径。看板、待办和任务列表共用，不以历史埋点累计数代替业务事实。
 * 只读现有表，不修复、删除或回填历史记录；未分配负责人也属于全站在途。
 */
class CoreRecycleWorkloadService
{
    public function applyValidOrderFilter($query, int $siteId)
    {
        return $query->where('site_id', '=', $siteId)
            ->where('delete_at', '=', 0)
            ->whereNotIn('status', [RecycleOrderDict::ORDER_STATUS_CANCELLED, RecycleOrderDict::ORDER_STATUS_DELETE]);
    }

    public function applyValidDeviceFilter($query, int $siteId)
    {
        return $query->where('site_id', '=', $siteId)
            ->whereIn('order_id', function ($sub) use ($siteId) {
                $this->applyValidOrderFilter($sub->name('recycle_order'), $siteId)->field('id');
            });
    }

    public function orderQuery(int $siteId, string $stage)
    {
        $query = $this->applyValidOrderFilter(Db::name('recycle_order'), $siteId);
        if (!RecycleStageDict::isOrderStage($stage)) {
            return $query->where('id', '=', 0);
        }
        $query->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN);
        return $query->where('delivery_type', $stage === RecycleStageDict::STAGE_PICKUP ? '=' : '<>',
            RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE);
    }

    public function deviceQuery(int $siteId, array $stages)
    {
        return $this->applyDeviceStages(Db::name('recycle_device'), $siteId, $stages);
    }

    /** $query 为未使用别名的设备查询，可继续叠加关键字、责任人或分页条件。 */
    public function applyDeviceStages($query, int $siteId, array $stages)
    {
        $stages = array_values(array_intersect(array_keys(RecycleStageDict::getStageStatuses()), $stages));
        $query = $this->applyValidDeviceFilter($query, $siteId);
        if ($stages === []) return $query->where('id', '=', 0);

        return $query->where(function ($union) use ($siteId, $stages) {
            foreach ($stages as $stage) {
                $union->whereOr(function ($part) use ($siteId, $stage) {
                    $this->applySingleDeviceStage($part, $siteId, $stage);
                });
            }
        });
    }

    private function applySingleDeviceStage($query, int $siteId, string $stage): void
    {
        $query->whereIn('status', RecycleStageDict::getStageStatuses()[$stage]);
        if ($stage === RecycleStageDict::STAGE_ABNORMAL) {
            // 回收设备 status=6 在“创建退回单”时就会写入，并不意味着仍待处理。
            // 只认当前关联且未完成/未取消/未删除的退回单，IN 避免重复关联行放大台数。
            $query->whereIn('id', function ($sub) use ($siteId) {
                $returnTable = Db::name('recycle_return_order')->getTable();
                $deviceTable = Db::name('recycle_device')->getTable();
                $sub->name('recycle_return_device')->alias('rd')
                    ->join([$returnTable => 'ro'], 'rd.return_order_id = ro.id')
                    ->join([$deviceTable => 'd'], 'd.id = rd.device_id AND d.return_order_id = ro.id AND d.order_id = ro.order_id')
                    ->where('ro.site_id', '=', $siteId)->where('d.site_id', '=', $siteId)
                    ->where('ro.delete_at', '=', 0)
                    ->whereIn('ro.status', [RecycleReturnOrderDict::ORDER_STATUS_PENDING, RecycleReturnOrderDict::ORDER_STATUS_RETURNING])
                    ->whereIn('rd.status', [0, 1, RecycleReturnOrderDict::DEVICE_STATUS_RETURNING])
                    ->field('rd.device_id');
            });
            return;
        }

        $query->whereNotIn('dispose_type', [RecycleOrderDict::DISPOSE_TYPE_RETURN, RecycleOrderDict::DISPOSE_TYPE_CONSIGN]);
        // 未签收、已终结订单的残留设备不再进入质检/定价/确认队列。
        // 已完成订单存在真实采购补差时，部分打款设备仍需继续结清。
        $query->where(function ($active) use ($siteId, $stage) {
            $active->whereIn('order_id', function ($sub) use ($siteId) {
                $sub->name('recycle_order')->where('site_id', '=', $siteId)
                    ->whereIn('status', [RecycleOrderDict::ORDER_STATUS_SIGNED, RecycleOrderDict::ORDER_STATUS_CHECKING,
                        RecycleOrderDict::ORDER_STATUS_CHECKED, RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,
                        RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT])->field('id');
            });
            if ($stage === RecycleStageDict::STAGE_PAY) {
                $active->whereOr(function ($partial) use ($siteId) {
                    $partial->where('pay_status', '=', RecycleOrderDict::PAY_STATUS_PARTIAL)
                        ->whereIn('order_id', function ($sub) use ($siteId) {
                            $sub->name('recycle_order')->where('site_id', '=', $siteId)
                                ->where('status', '=', RecycleOrderDict::ORDER_STATUS_COMPLETED)->field('id');
                        });
                });
            }
        });

        if ($stage === RecycleStageDict::STAGE_PAY) {
            $query->whereIn('pay_status', [RecycleOrderDict::PAY_STATUS_UNPAID, RecycleOrderDict::PAY_STATUS_PARTIAL])
                ->where('pay_amount', '>=', 0)->whereColumn('final_price', '>', 'pay_amount')
                ->where(function ($payment) {
                    // 有付款时间却没有金额的矛盾记录，不作为可直接处理的未付款。
                    $payment->where('pay_time', '=', 0)->whereOr('pay_amount', '>', 0);
                });
        } else {
            $query->where('pay_status', '=', RecycleOrderDict::PAY_STATUS_UNPAID)
                ->where('pay_amount', '=', 0)->where('pay_time', '=', 0);
            if ($stage === RecycleStageDict::STAGE_CONFIRM) {
                $query->where('confirm_status', '=', RecycleOrderDict::CONFIRM_STATUS_PENDING);
            }
        }
    }

    public function getCurrentCounts(int $siteId): array
    {
        $counts = [];
        foreach (RecycleStageDict::getStages() as $stage) {
            $key = $stage['stage_key'];
            $query = RecycleStageDict::isOrderStage($key)
                ? $this->orderQuery($siteId, $key) : $this->deviceQuery($siteId, [$key]);
            $counts[$key] = (int)$query->count();
        }
        return $counts;
    }

    public function pendingPayAmount(int $siteId): float
    {
        // 同一设备补价后只统计未结清差额，不重复累计已付部分。
        $row = $this->deviceQuery($siteId, [RecycleStageDict::STAGE_PAY])
            ->fieldRaw('SUM(final_price - pay_amount) AS pending_amount')->find();
        return round((float)($row['pending_amount'] ?? 0), 2);
    }
}
