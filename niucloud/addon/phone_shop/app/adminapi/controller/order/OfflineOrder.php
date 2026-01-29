<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址:https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\adminapi\controller\order;

use addon\phone_shop\app\service\admin\order\OfflineOrderService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 线下订单控制器
 */
class OfflineOrder extends BaseAdminController
{
    /**
     * 创建线下订单
     * @return Response
     */
    public function create()
    {
        $data = $this->request->params([
            ['member_id', 0],
            ['goods_list', []],
            ['pay_status', 'paid'],
            ['offline_pay_account', ''],
            ['remark', ''],
            ['delivery_type', 'store'],
            ['sku_name',''],
            ['taker_name', ''],
            ['taker_mobile', ''],
        ]);

        $result = (new OfflineOrderService())->create($data);
        return success('创建成功', $result);
    }

    /**
     * 获取线下订单列表
     * @return Response
     */
    public function lists()
    {
        $where = $this->request->params([
            ['order_no', ''],
            ['member_id', ''],
            ['pay_status', ''],
            ['create_time', []],
        ]);

        return success((new OfflineOrderService())->getPage($where));
    }
}
