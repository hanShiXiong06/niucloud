<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\core\statistics;


use core\base\BaseCoreService;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\model\order\Order;
use  addon\home_service\app\dict\order\RefundDict;
use think\facade\Db;
use think\Model;

/**
 * 售后统计
 * Class CoreCardOrderCreateService
 */
class  CoreRefundService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderRefund();
    }


    /**
     * 批量获取带时间范围的退款统计（支持按门店ID或师傅ID分组）
     * @param int $site_id （必填）站点ID
     * @param string $groupBy （必填）分组字段：'store_id'（按门店）、'technician_id'（按师傅）
     * @param array $ids （必填）对应分组的ID数组：门店ID数组或师傅ID数组
     * @param int $store_id （可选）门店筛选条件（仅当$groupBy为'technician_id'时有效），默认0
     * @param int $start_time （可选）开始时间戳，默认0（不限制）
     * @param int $end_time （可选）结束时间戳，默认0（不限制）
     * @return array 键为ID（门店ID或师傅ID），值为退款统计数据
     */
    public function batchGetTimeRangeStats(
        int $site_id,
        string $groupBy,
        array $ids,
        int $store_id = 0,
        $start_time = 0,
        $end_time = 0
    )
    {
        // 1. 校验分组字段合法性
        if (!in_array($groupBy, ['store_id', 'site_id', 'technician_id'])) {
            throw new \InvalidArgumentException("分组字段仅支持 'store_id' 或 'site_id' 或 'technician_id'");
        }
        // 校验ID数组非空
        if (empty($ids)) {
            throw new \InvalidArgumentException("ID数组不能为空");
        }
        // 2. 基础查询条件
        $baseWhere = [
            ['site_id', '=', $site_id],
            [$groupBy, 'in', $ids] // 批量匹配目标ID（直接使用分组字段）
        ];
        // 3. 仅按师傅分组时支持门店筛选
        if ($groupBy === 'technician_id' && $store_id > 0) {
            $baseWhere[] = ['store_id', '=', $store_id];
        }
        // 4. 统计字段（直接使用分组字段作为分组依据）
        $fields = [
            $groupBy,
            'COUNT(*) as total_refund_count',
            "SUM(CASE WHEN status = '" . RefundDict::WAIT_REFUND . "' THEN 1 ELSE 0 END) as wait_refund_count",
            "SUM(CASE WHEN status = '" . RefundDict::REFUND_COMPLETED . "' THEN 1 ELSE 0 END) as refund_completed_count",
            "SUM(CASE WHEN status = '" . RefundDict::REFUND_REFUSE . "' THEN 1 ELSE 0 END) as refund_refuse_count",
            "SUM(CASE WHEN status = '" . RefundDict::CANCEL . "' THEN 1 ELSE 0 END) as cancel_count"
        ];
        // 5. 执行查询
        $query = $this->model->where($baseWhere);
        // 时间范围条件（有效时添加）
        if ($start_time > 0 || $end_time > 0) {
            $query->whereBetween('create_time', [$start_time, $end_time]);
        }
        $stats = $query->field($fields)
            ->group($groupBy) // 按传入的字段名分组
            ->select()
            ->toArray();
        // 6. 转换为「ID => 统计数据」的映射，补充默认值
        $result = [];
        foreach ($stats as $item) {
            $id = $item[$groupBy]; // 直接通过分组字段取ID值
            $result[$id] = [
                'total_refund_count' => $item['total_refund_count'] ?? 0,
                'wait_refund_count' => $item['wait_refund_count'] ?? 0,
                'refund_completed_count' => $item['refund_completed_count'] ?? 0,
                'refund_refuse_count' => $item['refund_refuse_count'] ?? 0,
                'cancel_count' => $item['cancel_count'] ?? 0
            ];
        }
        // 补充未查询到的ID（确保所有传入的ID都有返回）
        foreach ($ids as $id) {
            if (!isset($result[$id])) {
                $result[$id] = [
                    'total_refund_count' => 0,
                    'wait_refund_count' => 0,
                    'refund_completed_count' => 0,
                    'refund_refuse_count' => 0,
                    'cancel_count' => 0
                ];
            }
        }
        return $result;
    }


}
