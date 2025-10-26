<?php

namespace addon\home_service\app\listener\notice_template;

use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\technician\Technician;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 *  订单开始服务通知
 */
class HomeServiceOrderService extends BaseNoticeTemplate
{

    private $key = 'home_service_order_service';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new Order())->where([['order_id', '=', $params['data']['order_id']]])->findOrEmpty()->toArray();
            $technician_info = (new Technician())->where([['id', '=', $order['technician_id']]])->findOrEmpty()->toArray();
            if (!empty($order)) {
                return $this->toReturn(
                    [
                        '__wechat_page' => get_wap_domain($order['site_id']) . '/addon/home_service/user/pages/order/detail?order_id=' . $order['order_id'],//模板消息链接
//                        '__weapp_page' => 'addon/home_service/user/pages/order/detail?order_id=' . $order['order_id'],//小程序链接
                        'goods_name' => $order['order_name'],
                        'order_no' => $order['order_no'],
                        'order_money' => $order['order_money'], //交易流水号
                        'create_time' => $order['create_time'],//创建时间
                        'technician' => $technician_info['real_name'],//师傅
                        'mobile' => $technician_info['mobile'],//电话
                    ],
                    [
                        'member_id' => $order['member_id']
                    ]
                );
            }
        }
    }
}
