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

namespace addon\home_service\app\adminapi\controller\order;

use addon\home_service\app\service\admin\order\TechnicianOrderService;
use core\base\BaseAdminController;

/**
 * 师傅订单
 * Class Order
 * @description 订单
 * @package addon\home_service\app\adminapi\controller\order
 */
class TechnicianOrder extends BaseAdminController
{
    /**
     * 订单列表
     * @description 订单列表
     * @return void
     */
    public function lists()
    {
        $data = $this->request->params([
            ["technician_id", ""],
            ["order_no", ""],
            ["order_name", ""],
            ["member_search", ""],
        ]);
        return success((new TechnicianOrderService())->getPage($data));
    }


}