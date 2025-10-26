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


use core\base\BaseAdminService;
use addon\home_service\app\model\account\StoreAccount;
use addon\home_service\app\dict\account\AccountDict;


/**
 * 门店账单统计统计
 * Class CoreCardOrderCreateService
 */
class StoreAccountService extends BaseAdminService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreAccount();
    }


    public function batchGetStats(
        int $site_id,
        array $ids,
        int $start_time = 0,
        int $end_time = 0  // 移除末尾多余的逗号
    )
    {
        $groupBy = 'store_id';
        // 基础查询条件：站点、目标分组ID
        $where = [
            ['site_id', '=', $site_id],
            [$groupBy, 'in', $ids]
        ];
        // 修复字段定义语法错误，正确拼接常量
        $fields = [
            $groupBy,
            // 常量需要用引号包裹并正确拼接，避免SQL语法错误
            "SUM(CASE WHEN status = '" . AccountDict::PENDING_SETTLEMENT . "' THEN account_data ELSE 0 END) as total_pending_settlement_money"
        ];
        // 初始化查询：基础条件 + 分组
        $query = $this->model->where($where)->field($fields)->group($groupBy);
        // 时间条件：同时传开始/结束时间才生效
        if ($start_time !== 0 && $end_time !== 0) {
            $query->whereBetween('create_time', [$start_time, $end_time]);
        }
        // 执行查询并格式化结果
        $stats = $query->select()->toArray();
        $result = [];
        foreach ($stats as $item) {
            $result[$item[$groupBy]] = $item;
        }
        return $result;
    }
}
