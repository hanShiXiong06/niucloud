<?php

namespace addon\home_service\app\listener\notice_template;

use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\model\technician\Technician;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 *  订单退款通知
 */
class HomeServiceRefund extends BaseNoticeTemplate
{

    private $key = 'home_service_refund';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order_refund = (new OrderRefund())->where([['refund_no', '=', $params['data']['refund_no']]])->findOrEmpty();

            if (!$order_refund->isEmpty() || !$order_refund->orderMain->isEmpty()) {
                return $this->toReturn(
                    [
                        '__wechat_page' => get_wap_domain($order_refund['site_id']) . '/addon/home_service/user/pages/order/refund/detail?refund_no=' . $order_refund['refund_id'],//模板消息链接
                        '__weapp_page' => 'addon/home_service/user/pages/order/refund/detail?refund_no=' . $order_refund['refund_id'],//小程序链接
                        'order_no' => $order_refund->orderMain->order_no,
                        'goods_name' => $order_refund->orderMain->order_name,
                        'money' => $order_refund->money,
                    ],
                    [
                        'member_id' => $order_refund->orderMain->member_id,
                    ]
                );
            }
        }
    }
}
