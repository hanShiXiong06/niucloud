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

namespace addon\home_service\app\api\controller\card;

use addon\home_service\app\service\api\card\CardOrderService;
use addon\home_service\app\service\api\card\CardService;
use core\base\BaseApiController;


/**
 * 次卡套餐订单控制器
 * Class CardOrder
 * @package addon\home_service\app\api\controller\card
 */
class CardOrder extends BaseApiController
{
    /**
     * 订单状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', (new CardOrderService())->getStatus());
    }

    /**
     * 获取订单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_status', ''],
        ]);
        return success('SUCCESS', (new CardOrderService())->getPage($data));
    }

    /**
     * 获取订单详情
     * @param $order_id
     * @return \think\Response
     */
    public function detail($order_id)
    {
        return success('SUCCESS', (new CardOrderService())->getDetail($order_id));
    }


    /**
     * 取消订单
     * @param int $order_id
     * @return \think\Response
     */
    public function cancel(int $order_id)
    {
        return success('SUCCESS', (new CardOrderService())->cancel($order_id));
    }

    /**
     * 删除订单
     * @param int $order_id
     * @return \think\Response
     */
    public function delete(int $order_id)
    {
        return success('DELETE_SUCCESS', (new CardOrderService())->delete($order_id));
    }
}
