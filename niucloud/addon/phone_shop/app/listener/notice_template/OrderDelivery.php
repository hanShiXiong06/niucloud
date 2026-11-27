<?php
declare (strict_types = 1);

namespace addon\phone_shop\app\listener\notice_template;

use addon\phone_shop\app\service\core\order\CoreOrderService;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 订单发货通知
 */
class OrderDelivery extends BaseNoticeTemplate
{
    private $key = 'phone_shop_order_delivery';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new CoreOrderService())->getInfo($params['data']['order_id']);
            if (!empty($order)) {
                $order_data = $params['data'];
                $wap_domain = get_wap_domain($order['site_id']);
                return $this->toReturn(
                    [
                        '__wechat_page' => $wap_domain . '/addon/phone_shop/pages/order/detail?order_id=' . $order_data['order_id'],//模板消息链接
                        '__weapp_page' => 'addon/phone_shop/pages/order/detail?order_id=' . $order_data['order_id'],//小程序链接
                        'body' => str_sub($order_data['body']),
                        'order_no' => $order_data['order_no'],
                        'delivery_time' => $order_data['delivery_time'],
                        'order_money' => $order_data['order_money'],
                        'url' => $wap_domain . '/addon/phone_shop/pages/order/detail?order_id=' . $order_data['order_id']
                    ],
                    [
                        'member_id' => $order_data['member_id']
                    ]
                );
            }
        }
    }
}
