<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\listener\pay;

use addon\home_service\app\dict\card\CardOrderDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\service\core\card\CoreCardOrderService;
use addon\home_service\app\service\core\order\CoreOrderService;
use app\dict\pay\PayDict;
use core\exception\CommonException;

/**
 * 支付创建事件
 */
class PayCreateListener
{
    public function handle(array $params)
    {
        $trade_type = $params['trade_type'] ?? '';
        if (in_array($trade_type, [OrderDict::ORDER_TYPE_ORDER])) {
            $order_info = (new CoreOrderService())->orderInfo($params['site_id'], $params['trade_id']);
            if ($order_info['order_status'] != OrderDict::WAIT_PAY) throw new CommonException('HOME_SERVICE_ONLY_WAIT_PAY_CAN_BE_PAY');
            //添加订单支付表
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $order_info['member_id'],//买家id
                'money' => $order_info['order_money'],//订单金额
                'trade_type' => $trade_type,//业务类型
                'trade_id' => $params['trade_id'],
                'body' => $order_info['order_name'],
            ];
        }
        if (in_array($trade_type, [OrderDict::ORDER_TYPE_ITEM])) {
            $item_list = (new CoreOrderService())->orderItemList($params['site_id'], $params['trade_id']);
            if (empty($item_list)) throw new CommonException('HOME_SERVICE_ORDER_ADDED_ITEM_ORDER_NOT_EXIST');

            foreach ($item_list as $value){
                if ($value['is_pay'] == 1) throw new CommonException('HOME_SERVICE_ONLY_WAIT_PAY_CAN_BE_PAY');
            }
            $money = number_format(array_sum(array_column($item_list, 'item_money')), 2, '.', '');
            //添加订单支付表
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $item_list[0]['member_id'],//买家id
                'money' => $money,//订单金额
                'trade_type' => $trade_type,//业务类型
                'trade_id' => $params['trade_id'],
                'body' => '增项服务',
            ];
        }
        if (in_array($trade_type, [CardOrderDict::TYPE])) {
            $card_order_info = (new CoreCardOrderService())->orderInfo($params['site_id'], $params['trade_id']);
            if ($card_order_info['order_status'] != CardOrderDict::WAIT_PAY) throw new CommonException('HOME_SERVICE_ONLY_WAIT_PAY_CAN_BE_PAY');
            //添加订单支付表
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $card_order_info['member_id'],//买家id
                'money' => $card_order_info['order_money'],//订单金额
                'trade_type' => $trade_type,//业务类型
                'trade_id' => $params['trade_id'],
                'body' => $card_order_info['order_name'],
            ];
        }

    }
}
