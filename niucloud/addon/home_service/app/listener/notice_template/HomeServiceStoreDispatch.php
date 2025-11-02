<?php

namespace addon\home_service\app\listener\notice_template;

use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\model\technician\Technician;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 *  订单退款通知
 */
class HomeServiceStoreDispatch extends BaseNoticeTemplate
{

    private $key = 'home_service_store_dispatch';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new Order())->where([['order_id', '=', $params['data']['order_id']]])->with([
                'store' => function($query){
                    $query->field('store_id,store_name');
                }
                ,'technician' => function($query){
                    $query->field('id,member_id');
                }])->findOrEmpty()->toArray();
            if (!empty($order)) {
                return $this->toReturn(
                    [
                        '__wechat_page' => get_wap_domain($order['site_id']) . '/addon/home_service/technician/pages/order/detail?order_id=' . $order['order_id'],//模板消息链接
                        '__weapp_page' => 'addon/home_service/technician/pages/order/detail?order_id=' . $order['order_id'],//小程序链接
                        'store_name' => $order['store']['store_name'] ?? '',
                        'goods_name' => $order['order_name'],
                        'taker_name' => $order['taker_name'],
                        'taker_mobile' => $order['taker_mobile'],
                        'taker_full_address' => $order['taker_address'],
                        'reserve_service_time' => $order['reserve_service_time'],
                    ],
                    [
                        'member_id' => $order['technician']['member_id'],
                    ]
                );
            }
        }
    }
}
