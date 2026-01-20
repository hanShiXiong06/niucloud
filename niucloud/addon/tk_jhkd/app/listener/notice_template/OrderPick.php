<?php
declare (strict_types=1);

namespace addon\tk_jhkd\app\listener\notice_template;

use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单揽收
 */
class OrderPick extends BaseNoticeTemplate
{
    private $key = 'tk_jhkd_order_pick';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new Tkjhkdorder())->where(['order_id' => $params['data']['order_id']])->findOrEmpty();
            $OrderDeliveryInfo=(new OrderDelivery())->where(['order_id'=>$params['data']['order_id']])->findOrEmpty();

            if (!$order->isEmpty()) {
                $wap_domain = get_wap_domain($order['site_id']);
                return $this->toReturn(
                    [
                        '__wechat_page' => $wap_domain . '/addon/tk_jhkd/pages/orderdetail?id=' . $order['id'],//模板消息链接
                        '__weapp_page' => 'addon/tk_jhkd/pages/orderdetail?id=' . $order['id'],//小程序链接
                        'remark' => '您的运单揽收，请及时关注揽收计费信息',
                        'order_no' => $order['order_id'],
                        'status_name' => '在途中',
                        'url'=>$wap_domain . '/addon/tk_jhkd/pages/orderaddlist',
                        'delivery_id'=>$OrderDeliveryInfo['delivery_id'],
                        'delivery_type'=>$OrderDeliveryInfo['delivery_type']
                    ],
                    [
                        'member_id' => $order['member_id']
                    ]
                );
            }
        }
    }
}
