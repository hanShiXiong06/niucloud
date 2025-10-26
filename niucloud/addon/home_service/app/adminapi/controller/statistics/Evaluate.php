<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\adminapi\controller\statistics;


use addon\home_service\app\service\admin\statistics\EvaluateService;
use core\base\BaseAdminController;


/**
 * 评价统计控制器
 * Class Evaluate
 * @package addon\home_service\app\adminapi\controller\order
 */
class Evaluate extends BaseAdminController
{


    /**
     * 师傅评价
     * Class Evaluate
     * @package addon\home_service\app\adminapi\controller\order
     */
    public function getTechEvalStats()
    {
        $data = $this->request->params([
            ["technician_id", ""],
        ]);
        return success((new EvaluateService())->getTechEvalStats($data));
    }


    /**
     * 门店评价
     * Class Evaluate
     * @package addon\home_service\app\adminapi\controller\order
     */
    public function getStoreEvalStats()
    {
        $data = $this->request->params([
            ["store_id", ""],
        ]);
        return success((new EvaluateService())->getStoreEvalStats($data));
    }


}
