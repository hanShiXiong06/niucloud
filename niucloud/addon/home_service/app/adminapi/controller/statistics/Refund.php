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


use addon\home_service\app\service\admin\statistics\RefundService;
use core\base\BaseAdminController;


/**
 * 评价售后控制器
 * Class Evaluate
 * @package addon\home_service\app\adminapi\controller\order
 */
class Refund extends BaseAdminController
{

    public function getTechRefundStats()
    {
        $data = $this->request->params([
            ["technician_id", ""],
        ]);
        return success((new RefundService())->getTechRefundStats($data));
    }


    public function getStoreRefundStats()
    {
        $data = $this->request->params([
            ["store_id", ""],
        ]);
        return success((new RefundService())->getStoreRefundStats($data));

    }

}
