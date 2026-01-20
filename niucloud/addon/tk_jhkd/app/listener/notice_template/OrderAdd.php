<?php
declare (strict_types=1);

namespace addon\tk_jhkd\app\listener\notice_template;

use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use addon\tk_jhkd\app\model\order\OrderAdd as Add;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单补差价
 */
class OrderAdd extends BaseNoticeTemplate
{
    private $key = 'tk_jhkd_order_add';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new Tkjhkdorder())->where(['order_id' => $params['data']['order_id']])->findOrEmpty();
           $orderAddModel = new Add();
           $orderAddInfo=$orderAddModel->where(['order_id'=>$params['data']['order_id']])->findOrEmpty();
           $OrderDeliveryInfo=(new OrderDelivery())->where(['order_id'=>$params['data']['order_id']])->findOrEmpty();
           if(!$orderAddInfo->isEmpty()&&!$order->isEmpty()){
              $wap_domain = get_wap_domain($order['site_id']);
              return $this->toReturn(
                  [
                      '__wechat_page' => $wap_domain . '/addon/tk_jhkd/pages/orderaddlist',//模板消息链接
                      '__weapp_page' => 'addon/tk_jhkd/pages/orderaddlist',//小程序链接
                      'remark' => '您的订单存在补差价，请及时处理',
                      'order_no' => $order['order_id'],
                      'amount' => $orderAddInfo['order_money'],
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
