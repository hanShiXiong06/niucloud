<?php

namespace addon\ai_image\app\listener\pay;
use addon\ai_image\app\model\aiimageorder\AiimageOrder as Order;
use app\dict\pay\PayDict;
use addon\ai_image\app\dict\order\OrderStatusDict;
/**
 * 支付单据创建事件
 */
class PayCreateListener
{
    public function handle(array $params)
    {
        $trade_type = $params['trade_type'] ?? '';
        if ($trade_type == OrderStatusDict::getOrderType()['type']) {
            $trade_id = $params['trade_id'];
            $site_id = $params['site_id'];
            $order_info = (new Order())->where(['site_id'=>$site_id,'id'=> $trade_id])->findOrEmpty();
            return [
                'site_id' => $order_info['site_id'],
                'main_type' => PayDict::MEMBER,
                'main_id' => $order_info['member_id'],//买家id
                'money' => $order_info['order_money'],//订单金额
                'trade_type' =>  $trade_type,//业务类型
                'trade_id' => $trade_id,
                'body' => $order_info['name']??'Sora视频套餐'
            ];
        }
    }
}