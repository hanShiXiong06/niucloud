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


use addon\home_service\app\model\order\Evaluate;
use core\base\BaseCoreService;
use think\facade\Db;
use think\Model;

/**
 * 评论统计
 * Class CoreCardOrderCreateService
 */
class  CoreEvaluateService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }


    /**
     * 批量获取带时间范围的统计（支持按师傅或门店分组）
     * @param int $site_id （必填）站点ID
     * @param string $groupBy 分组类型：'store_id'或'technician_id'
     * @param array $ids （必填）对应分组的ID数组：师傅ID数组或门店ID数组
     * @param int $store_id （可选）门店筛选条件（仅当$groupBy为'technician'时有效），默认0
     * @param int $start_time （可选）开始时间戳，默认0（不限制）
     * @param int $end_time （可选）结束时间戳，默认0（不限制）
     * @return array 键为ID（师傅ID或门店ID），值为统计数据
     */
    public function batchGetTimeRangeStats(
        int $site_id,
        string $groupBy,
        array $ids,
        int $store_id = 0,
        int $start_time = 0,
        int $end_time = 0
    )
    {
        // 校验分组类型合法性
        if (!in_array($groupBy, ['store_id', 'technician_id'])) {
            throw new \InvalidArgumentException("分组类型仅支持 'technician_id' 或 'store_id'");
        }
        // 基础查询条件
        $baseWhere = [
            ['site_id', '=', $site_id],
            ['is_audit', 'in', [0,2]],
        ];
        // 动态添加分组ID的IN条件（师傅ID或门店ID）
        $baseWhere[] = [$groupBy, 'in', $ids];
        // 仅当按师傅分组时，支持额外的门店筛选
        //        if ($groupBy === 'technician' && $store_id > 0) {
        //            $baseWhere[] = ['store_id', '=', $store_id];
        //        }
        // 统计字段（固定，与原逻辑一致）
        $fields = [
            $groupBy, // 分组依据字段（动态取technician_id或store_id）
            'COUNT(*) as total_evaluate_count', // 总评价数
            'SUM(scores) as total_scores',
            'SUM(CASE WHEN scores > 3 THEN 1 ELSE 0 END) as score_gt3_count' // 评分>3的数量
        ];
        // 构建查询
        $query = $this->model->where($baseWhere);

        // 时间范围条件（仅当有值时添加）
        if ($start_time > 0 || $end_time > 0) {
            $query->whereBetween('create_time', [$start_time, $end_time]);
        }
        // 执行查询（按动态字段分组）
        $stats = $query->field($fields)
            ->group($groupBy)
            ->select()
            ->toArray();

        // 转换为「ID => 统计数据」的映射，确保默认值为0
        $result = [];
        foreach ($stats as $item) {
            $key = $item[$groupBy];
            $result[$key] = [
                'total_evaluate_count' => $item['total_evaluate_count'] ?? 0,
                'total_scores' => $item['total_scores'] ?? 0,
                'score_gt3_count' => $item['score_gt3_count'] ?? 0
            ];
        }
        // 补充未查询到的ID（确保所有传入的ID都有返回，默认0）
        foreach ($ids as $id) {
            if (!isset($result[$id])) {
                $result[$id] = [
                    'total_evaluate_count' => 0,
                    'total_scores' => 0,
                    'score_gt3_count' => 0
                ];
            }
        }
        return $result;
    }


}
