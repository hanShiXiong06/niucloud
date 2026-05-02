<?php
declare(strict_types=1);

namespace addon\recycle\app\model\express;

use core\base\BaseModel;

/**
 * 快递订单记录模型
 * Class ExpressOrderRecord
 * @package addon\recycle\app\model\express
 */
class ExpressOrderRecord extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'express_order_record';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    // JSON 字段
    protected $json = ['status_history', 'api_response'];
    protected $jsonAssoc = true;

    /**
     * 搜索器：站点ID
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('site_id', '=', $value);
        }
    }

    /**
     * 搜索器：订单号
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderNoAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('order_no', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：运单号
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchDeliveryIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('delivery_id', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：订单状态
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderStatusAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('order_status', '=', $value);
        }
    }

    public function searchProductCodeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('product_code', '=', $value);
        }
    }

    public function searchSenderMobileAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('sender_mobile', 'like', '%' . $value . '%');
        }
    }

    public function searchReceiverMobileAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('receiver_mobile', 'like', '%' . $value . '%');
        }
    }

    public function searchKeywordAttr($query, $value, $data)
    {
        if ($value) {
            $query->where(function ($query) use ($value) {
                $query->where('order_no', 'like', '%' . $value . '%')
                    ->whereOr('delivery_id', 'like', '%' . $value . '%')
                    ->whereOr('sender_name', 'like', '%' . $value . '%')
                    ->whereOr('sender_mobile', 'like', '%' . $value . '%')
                    ->whereOr('receiver_name', 'like', '%' . $value . '%')
                    ->whereOr('receiver_mobile', 'like', '%' . $value . '%');
            });
        }
    }

    /**
     * 搜索器：回收订单ID
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchRecycleOrderIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('recycle_order_id', '=', $value);
        }
    }

    /**
     * 搜索器：回收设备ID
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchRecycleDeviceIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('recycle_device_id', '=', $value);
        }
    }

    /**
     * 搜索器：服务商名称
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchProviderNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('provider_name', '=', $value);
        }
    }

    /**
     * 搜索器：创建时间范围
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        if ($value && is_array($value) && count($value) == 2) {
            $query->whereBetweenTime('create_at', $value[0], $value[1]);
        }
    }

    /**
     * 创建快递订单记录
     * @param array $data
     * @return ExpressOrderRecord|false
     */
    public static function createRecord(array $data)
    {
        return self::create($data);
    }

    /**
     * 更新实际重量和费用
     * @param string $orderNo
     * @param float $actualWeight
     * @param float $actualCost
     * @return bool
     */
    public static function updateActualInfo(string $orderNo, float $actualWeight, float $actualCost): bool
    {
        $record = self::where('order_no', $orderNo)->find();
        if (!$record) {
            return false;
        }

        $weightDiff = $actualWeight - $record->estimated_weight;
        $costDiff = $actualCost - $record->estimated_cost;

        return $record->save([
            'actual_weight' => $actualWeight,
            'actual_cost' => $actualCost,
            'weight_diff' => $weightDiff,
            'cost_diff' => $costDiff,
        ]);
    }

    /**
     * 更新订单状态
     * @param string $orderNo
     * @param string $status
     * @param string $remark
     * @return bool
     */
    public static function updateStatus(string $orderNo, string $status, string $remark = ''): bool
    {
        $record = self::where('order_no', $orderNo)->find();
        if (!$record) {
            return false;
        }

        // 添加状态历史
        $statusHistory = $record->status_history ?? [];
        $statusHistory[] = [
            'status' => $status,
            'remark' => $remark,
            'time' => time(),
        ];

        $updateData = [
            'order_status' => $status,
            'status_history' => $statusHistory,
        ];

        // 根据状态更新相应的时间字段
        if ($status === 'picked') {
            $updateData['pickup_time'] = time();
        } elseif ($status === 'delivered') {
            $updateData['delivery_time'] = time();
        } elseif ($status === 'cancelled') {
            $updateData['cancel_time'] = time();
            if ($remark) {
                $updateData['cancel_reason'] = $remark;
            }
        }

        return $record->save($updateData);
    }

    /**
     * 根据订单号获取记录
     * @param string $orderNo
     * @return ExpressOrderRecord|null
     */
    public static function getByOrderNo(string $orderNo): ?ExpressOrderRecord
    {
        return self::where('order_no', $orderNo)->find();
    }

    /**
     * 根据运单号获取记录
     * @param string $deliveryId
     * @return ExpressOrderRecord|null
     */
    public static function getByDeliveryId(string $deliveryId): ?ExpressOrderRecord
    {
        return self::where('delivery_id', $deliveryId)->find();
    }

    /**
     * 获取回收订单的快递记录列表
     * @param int $recycleOrderId
     * @return array
     */
    public static function getByRecycleOrderId(int $recycleOrderId): array
    {
        return self::where('recycle_order_id', $recycleOrderId)
            ->order('create_at', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取有重量差异的订单列表
     * @param int $siteId
     * @param float $threshold 差异阈值（kg）
     * @return array
     */
    public static function getWeightDiffOrders(int $siteId, float $threshold = 0.5): array
    {
        return self::where([
            ['site_id', '=', $siteId],
            ['weight_diff', '>', $threshold]
        ])
            ->order('weight_diff', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取有费用差异的订单列表
     * @param int $siteId
     * @param float $threshold 差异阈值（元）
     * @return array
     */
    public static function getCostDiffOrders(int $siteId, float $threshold = 5.0): array
    {
        return self::where([
            ['site_id', '=', $siteId],
            ['cost_diff', '>', $threshold]
        ])
            ->order('cost_diff', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 统计站点快递费用
     * @param int $siteId
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    public static function getStatistics(int $siteId, int $startTime, int $endTime): array
    {
        $query = self::where('site_id', $siteId);

        if ($startTime) {
            $query->where('create_at', '>=', $startTime);
        }
        if ($endTime) {
            $query->where('create_at', '<=', $endTime);
        }

        // 使用数据库聚合查询
        $totalCount = (clone $query)->count();
        $totalEstimatedCost = (clone $query)->sum('estimated_cost');
        $totalActualCost = (clone $query)->sum('actual_cost');
        $totalCostDiff = (clone $query)->sum('cost_diff');

        return [
            'total_count' => $totalCount,
            'total_estimated_cost' => round($totalEstimatedCost, 2),
            'total_actual_cost' => round($totalActualCost, 2),
            'total_cost_diff' => round($totalCostDiff, 2),
            'avg_cost' => $totalCount > 0 ? round($totalActualCost / $totalCount, 2) : 0,
        ];
    }
}

