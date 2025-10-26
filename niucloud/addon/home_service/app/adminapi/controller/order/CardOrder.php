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

use addon\home_service\app\service\admin\order\CardOrderService;
use app\dict\pay\PayDict;
use core\base\BaseAdminController;
use think\Response;

/**
 * 订单
 * Class CardOrder
 * @description 次卡订单
 * @package addon\home_service\app\adminapi\controller\card_order
 */
class CardOrder extends BaseAdminController
{

    /**
     * 订单来源
     * @description 订单列表
     * @ret urn void
     */
    public function getOrderFrom()
    {
        return success((new CardOrderService())->getOrderFrom());
    }


    /**
     * 订单状态
     * @description 获取订单状态
     * @return void
     */
    public function status()
    {
        return success((new CardOrderService())->getStatus());
    }


    /**
     * 订单状态 带数字
     * @description 获取订单状态
     * @return void
     */
    public function taskStatus()
    {
        return success((new CardOrderService())->getTaskStatus());
    }


    /**
     * 订单列表
     * @description 订单列表
     * @ret urn void
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['member_search', ''],
            ['create_time', []],
            ['order_from', ''],
            ['pay_time', []],
            ['order_status', ''],
            ['order_name', ''],
        ]);
        return success((new CardOrderService())->getPage($data));
    }

    /**
     * 订单详情
     * @description 订单详情
     * @param int $card_order_id
     * @return void
     */
    public function detail(int $card_order_id)
    {
        return success((new CardOrderService())->getDetail($card_order_id));
    }

    /**
     * 订单关闭
     * @description 订单详情
     * @param int $card_order_id
     * @return void
     */
    public function orderClose(int $card_order_id)
    {
        (new CardOrderService())->orderClose($card_order_id);
        return success();
    }


    /**
     * @description 删除订单
     * @return Response
     */
    public function delete()
    {
        $params = $this->request->params([
            ['order_ids', []]
        ]);
        $order_ids = $params['order_ids'];
        $res = (new CardOrderService)->delete($order_ids);
        return success("DELETE_SUCCESS");

    }


}
