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

namespace addon\phone_shop\app\api\controller\delivery;

use addon\phone_shop\app\service\api\delivery\OrderService;
use addon\phone_shop\app\service\core\local_delivery\CoreLocalDeliveryOrderNotifyService;
use core\base\BaseApiController;
use think\Response;

class Order extends BaseApiController
{
    /**
     * 订单状态回调
     * @param $site_id
     * @param $type
     * @param $order_no
     * @param $action
     * @return Response
     */
    public function notify($site_id, $type, $order_no, $action)
    {
        $params = $this->request->all();
        $params['site_id'] = $site_id;
        $params['type'] = $type;
        $params['order_no'] = $order_no;
        return (new CoreLocalDeliveryOrderNotifyService())->notify($site_id, $type, $order_no, $action, $params);
    }

    /**
     * 获取配送轨迹
     * @return Response
     */
    public function getTrackOfLocalDeliveryOrder()
    {
        $data = $this->request->params([
            [ 'trade_no', '' ],
            [ 'member_id', 0 ],
        ]);
        return success(( new OrderService() )->getTrackOfLocalDeliveryOrder($data));
    }
}
