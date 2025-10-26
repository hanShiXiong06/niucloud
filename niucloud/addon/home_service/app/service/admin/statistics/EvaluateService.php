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

namespace addon\home_service\app\service\admin\statistics;


use addon\home_service\app\model\order\Evaluate;
use core\base\BaseAdminService;
use addon\home_service\app\service\core\statistics\CoreEvaluateService;


/**
 * 评论统计
 * Class CoreCardOrderCreateService
 */
class  EvaluateService extends BaseAdminService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }


    /**
     * 通用评价统计（支持师傅/门店）
     * @param string $type 统计类型：'technician'（师傅）、'store'（门店）
     * @param array $where 筛选条件，需包含对应ID（technician_id或store_id）
     * @return array 包含总评价数、好评数、好评率的统计结果
     */
    private function getEvalStats(string $type, array $where): array
    {
        // 校验类型合法性
        if (!in_array($type, ['technician', 'store'])) {
            throw new \InvalidArgumentException("统计类型仅支持 'technician' 或 'store'");
        }
        // 确定ID字段（师傅ID或门店ID）
        $idField = "{$type}_id";
        $targetId = $where[$idField] ?? 0;
        // 调用通用统计服务（复用批量统计方法，传入单个ID的数组）
        $evaluateStatsMap = (new CoreEvaluateService)->batchGetTimeRangeStats(
            $this->site_id,
            $idField, // 分组字段（与ID字段一致）
            [$targetId] // 单个ID的数组（适配批量统计方法）
        );
        // 提取统计数据（默认值为0，避免空值）
        $stats = $evaluateStatsMap[$targetId] ?? [
                'total_evaluate_count' => 0,
                'score_gt3_count' => 0,
                'total_scores' => 0 // 总评分（所有星号总和）
            ];
        // 计算好评率（保留1位小数，加百分号）
        $total = $stats['total_evaluate_count'];
        $positiveRating = $total > 0
            ? round(($stats['score_gt3_count'] / $total) * 100, 1) . '%'
            : '0%';
        // 计算平均分（保留1位小数，总评价数为0时返回0）
        //        $avgScore = $total > 0
        //            ? round($stats['total_scores'] / $total, 1) // 总评分÷总评价数
        //            : 0;
        // 组装结果（新增avg_score字段）
        return [
            'total_evaluate_count' => $total,
            'score_gt3_count' => $stats['score_gt3_count'],
            'positive_rating' => $positiveRating,
            // 'avg_score' => $avgScore // 平均分
        ];
    }

    /**
     * 统计师傅评价字段（调用通用方法）
     */
    public function getTechEvalStats(array $where = []): array
    {
        return $this->getEvalStats('technician', $where);
    }


    /**
     * 统计门店评价字段（调用通用方法）
     */
    public function getStoreEvalStats(array $where = []): array
    {
        return $this->getEvalStats('store', $where);
    }


}
