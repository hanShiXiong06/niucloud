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


use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use core\base\BaseCoreService;
use think\facade\Db;
use think\Model;

/**
 * 订单统计
 * Class CoreCardOrderCreateService
 */
class  CoreOrderService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }


    /**
     * 批量获取统计数据（支持按门店/师傅分组，修复引号拼接问题）
     * @param int $site_id 必填：站点ID
     * @param string $groupBy 分组类型：'store_id'或'technician_id'
     * @param array $ids 分组ID数组（门店ID或师傅ID）
     * @param array $filterIds 筛选ID数组（可选，师傅ID或门店ID）
     * @param int $start_time 开始时间（0表示不限制）
     * @param int $end_time 结束时间（0表示不限制）
     * @param bool $withMoney 是否统计金额（默认false）
     * @return array 格式：[ID => 统计数据]
     */
    public function batchGetStats(
        int $site_id,
        string $groupBy,
        array $ids,
        array $filterIds = [],
        int $start_time = 0,
        int $end_time = 0,
        bool $withMoney = false
    )
    {
        // 基础校验：避免无效查询
        if (empty($site_id) || empty($ids) || !in_array($groupBy, ['store_id', 'technician_id'])) {
            return [];
        }
        // 确定筛选字段（与分组维度相反，如按门店分组则筛选师傅）
        $filterField = $groupBy === 'store_id' ? 'technician_id' : 'store_id';
        // 基础查询条件：站点、已支付、目标分组ID
        $where = [
            ['site_id', '=', $site_id],
            ['pay_time', '>', 0],
            [$groupBy, 'in', $ids]
        ];
        // 追加筛选条件（如按门店分组时，筛选特定师傅）
        if (!empty($filterIds)) {
            $where[] = [$filterField, 'in', $filterIds];
        }
        // 关键修复：正确拼接订单状态（每个状态用双引号包裹，无外层多余引号）
        $validStatusList = [
            OrderDict::WAIT_SERVICE,
            OrderDict::IN_SERVICE,
            OrderDict::WAIT_CHECK,
            OrderDict::FINISH
        ];
        $validStatus = implode(',', array_map(function ($status) {
            return "\"{$status}\""; // 每个状态单独用双引号包裹
        }, $validStatusList));
        // 统计字段：订单数（基础必选）
        $fields = [
            $groupBy,
            "SUM(CASE WHEN order_status IN ({$validStatus}) AND refund_status = '' THEN 1 ELSE 0 END) as service_process_total_count"
        ];

        // 按需添加金额统计（与原始逻辑一致）
        if ($withMoney) {
            $fields[] = "SUM(CASE WHEN order_status IN ({$validStatus}) AND refund_status = '' THEN order_money ELSE 0 END) as total_order_money";
        }
        // 初始化查询：基础条件 + 分组
        $query = $this->model->where($where)->field($fields)->group($groupBy);
        // 时间条件：与原始方法逻辑一致（必须同时传开始/结束时间才生效）
        if ($start_time !== 0 && $end_time !== 0) {
            $query->whereBetween('create_time', [$start_time, $end_time]);
        }
        // 执行查询并格式化结果（ID映射，方便调用）
        $stats = $query->select()->toArray();
        $result = [];
        foreach ($stats as $item) {
            $result[$item[$groupBy]] = $item;
        }

        return $result;
    }


    /**
     * 批量获取无时间范围的师傅状态统计（待服务/待验收）
     * @param int $site_id 必填：站点ID
     * @param array $technician_ids 必填：师傅ID数组
     * @param int $store_id 可选：门店ID（默认0不筛选）
     * @return array 格式：[师傅ID => 统计数据]
     */
    public function batchGetTechnicianStatusStats(
        int $site_id,
        array $technician_ids,
        int $store_id = 0
    )
    {
        if (empty($site_id) || empty($technician_ids)) {
            return [];
        }
        $where = [
            ['site_id', '=', $site_id],
            ['pay_time', '>', 0],
            ['technician_id', 'in', $technician_ids]
        ];
        if ($store_id > 0) {
            $where[] = ['store_id', '=', $store_id];
        }
        $fields = [
            'technician_id',
            "SUM(CASE WHEN order_status = '" . OrderDict::WAIT_SERVICE . "' AND refund_status = '' THEN 1 ELSE 0 END) as wait_service_count",
            "SUM(CASE WHEN order_status = '" . OrderDict::WAIT_CHECK . "' AND refund_status = '' THEN 1 ELSE 0 END) as wait_check_count"
        ];
        $stats = $this->model->where($where)->field($fields)->group('technician_id')->select()->toArray();
        $result = [];
        foreach ($stats as $item) {
            $result[$item['technician_id']] = $item;
        }
        return $result;
    }










    //
    //
    //    /**
    //     * 批量获取带时间范围的统计（修复参数顺序：必填$technician_ids在前）
    //     * @param int $site_id （必填）站点ID
    //     * @param array $technician_ids （必填）师傅ID数组
    //     * @param int $store_id （可选）门店ID，默认0
    //     * @param int $start_time （必填）开始时间
    //     * @param int $end_time （必填）结束时间
    //     * @return array
    //     */
    //    public function batchGetTimeRangeStats(
    //        int $site_id,
    //        array $technician_ids, // 必填参数移到前面
    //        int $store_id = 0,     // 可选参数移到后面
    //        int $start_time = 0,
    //        int $end_time = 0
    //    )
    //    {
    //        $baseWhere = [
    //            ['site_id', '=', $site_id],
    //            ['pay_time', '>', 0],
    //            ['technician_id', 'in', $technician_ids] // 批量匹配师傅ID
    //        ];
    //        // 追加门店条件（可选）
    //        if ($store_id > 0) {
    //            $baseWhere[] = ['store_id', '=', $store_id];
    //        }
    //        // 统计字段（原逻辑不变）
    //        $fields = [
    //            'technician_id', // 按师傅分组的依据
    //            'SUM(CASE WHEN order_status IN ("' . OrderDict::WAIT_SERVICE . '", "' . OrderDict::IN_SERVICE . '", "' . OrderDict::WAIT_CHECK . '", "' . OrderDict::FINISH . '")
    //             AND refund_status = ""
    //             THEN 1 ELSE 0 END) as service_process_total_count'
    //        ];
    //        // 执行查询（原逻辑不变）
    //        $stats = $this->model->where($baseWhere)
    //            ->whereBetween('create_time', [$start_time, $end_time])
    //            ->field($fields)
    //            ->group('technician_id') // 按师傅分组，获取每个师傅的统计
    //            ->select()
    //            ->toArray();
    //        // 转换为「师傅ID => 统计数据」的映射（方便后续赋值）
    //        $result = [];
    //        foreach ($stats as $item) {
    //            $result[$item['technician_id']] = $item;
    //        }
    //        return $result;
    //    }
    //
    //
    //    /**
    //     * 批量获取无时间范围的统计（同样修复参数顺序）
    //     * @param int $site_id （必填）站点ID
    //     * @param array $technician_ids （必填）师傅ID数组
    //     * @param int $store_id （可选）门店ID，默认0
    //     * @return array
    //     */
    //    public function batchGetNoTimeRangeStats(
    //        int $site_id,
    //        array $technician_ids, // 必填参数移到前面
    //        int $store_id = 0      // 可选参数移到后面
    //    )
    //    {
    //        $baseWhere = [
    //            ['site_id', '=', $site_id],
    //            ['pay_time', '>', 0],
    //            ['technician_id', 'in', $technician_ids] // 批量匹配师傅ID
    //        ];
    //        // 追加门店条件（可选）
    //        if ($store_id > 0) {
    //            $baseWhere[] = ['store_id', '=', $store_id];
    //        }
    //        // 统计字段（待服务+待验收，无时间范围）
    //        $fields = [
    //            'technician_id', // 按师傅分组的依据
    //            // 待服务数（无时间限制）
    //            'SUM(CASE WHEN order_status = "' . OrderDict::WAIT_SERVICE . '"
    //                 AND refund_status = ""
    //                 THEN 1 ELSE 0 END) as wait_service_count',
    //            // 待验收数（无时间限制）
    //            'SUM(CASE WHEN order_status = "' . OrderDict::WAIT_CHECK . '"
    //                 AND refund_status = ""
    //                 THEN 1 ELSE 0 END) as wait_check_count'
    //        ];
    //        // 执行查询（无时间条件，按师傅分组）
    //        $stats = $this->model->where($baseWhere)
    //            ->field($fields)
    //            ->group('technician_id')
    //            ->select()
    //            ->toArray();
    //        // 转换为「师傅ID => 统计数据」的映射
    //        $result = [];
    //        foreach ($stats as $item) {
    //            $result[$item['technician_id']] = $item;
    //        }
    //        return $result;
    //    }


}
