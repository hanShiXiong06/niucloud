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
use addon\home_service\app\service\core\statistics\CoreRefundService;


/**
 * 售后统计
 * Class CoreCardOrderCreateService
 */
class  RefundService extends BaseAdminService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }


    /**
     * 统计师傅部分字段
     * Class CoreCardOrderCreateService
     */
    public function getTechRefundStats($where = [])
    {
        $technician_id = $where['technician_id'] ?? 0;
        $evaluateNoTimeStatsMap = (new  CoreRefundService)->batchGetTimeRangeStats($this->site_id, "technician_id", [$technician_id]);
        return $evaluateNoTimeStatsMap[$technician_id];
    }


    public function getStoreRefundStats($where = [])
    {
        $store_id = $where['store_id'] ?? 0;
        $evaluateNoTimeStatsMap = (new  CoreRefundService)->batchGetTimeRangeStats($this->site_id, 'store_id', [$store_id]);
        return $evaluateNoTimeStatsMap[$store_id];
    }

}
