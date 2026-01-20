<?php

namespace addon\tk_jhkd\app\listener\pay;

use addon\tk_jhkd\app\model\order\OrderAdd;
use app\dict\pay\PayDict;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\dict\order\JhkdOrderAddDict;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 支付单据创建事件
 */
class PayCreateListener
{
    public function handle(array $params)
    {
        $trade_type = $params['trade_type'] ?? '';
        if ($trade_type == JhkdOrderDict::getOrderType()['type']) {
            $trade_id = $params['trade_id'];
            $site_id = $params['site_id'];
            $order_info = (new Tkjhkdorder())->where(['site_id'=>$site_id,'id'=> $trade_id])->findOrEmpty();
            return [
                'site_id' => $order_info['site_id'],
                'main_type' => PayDict::MEMBER,
                'main_id' => $order_info['member_id'],//买家id
                'money' => $order_info['pay_money'],//订单金额
                'trade_type' =>  $trade_type,//业务类型
                'trade_id' => $trade_id,
                'body' => '快递下单付款'
            ];
        }
        if ($trade_type == JhkdOrderAddDict::getOrderType()['type']) {
            $trade_id = $params['trade_id'];
            $site_id = $params['site_id'];
            $order_info = (new OrderAdd())->where(['site_id'=>$site_id,'id'=> $trade_id])->findOrEmpty();
            return [
                'site_id' => $order_info['site_id'],
                'main_type' => PayDict::MEMBER,
                'main_id' => $order_info['member_id'],//买家id
                'money' => $order_info['order_money'],//订单金额
                'trade_type' =>  $trade_type,//业务类型
                'trade_id' => $trade_id,
                'body' => '快递下单补差价付款'
            ];
        }
    }
}