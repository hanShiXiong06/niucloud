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

use addon\home_service\app\service\admin\order\StoreOrderService;
use core\base\BaseAdminController;

/**
 * 门店订单
 * Class Order
 * @description 订单
 * @package addon\home_service\app\adminapi\controller\order
 */
class StoreOrder extends BaseAdminController
{
    /**
     * 订单列表
     * @description 订单列表
     * @return void
     */
    public function lists()
    {
        $data = $this->request->params([
            ["store_id", ""],
            ["order_no", ""],
            ["order_name", ""],
            ["technician_name", ""],
            ["member_search", ""],
        ]);
        return success((new StoreOrderService())->getPage($data));
    }


}