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

namespace addon\phone_shop\app\listener\verify;

use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use core\exception\CommonException;

/**
 * 核销类型注册
 */
class VerifyCreateListener
{
    /**
     * @param array $params
     * @return array|void
     */
    public function handle($params = [])
    {
        //虚拟商品
        if($params['type'] == 'shopVirtualGoods'){
            $data = $params['data'] ?? [];
            $member_id = $params['member_id'];
            $order_goods_id = $data['order_goods_id'] ?? 0;
            $order_goods_info = (new OrderGoods())->where([['order_goods_id', '=', $order_goods_id], ['member_id', '=', $member_id]])->findOrEmpty();
            if($order_goods_info->isEmpty()) throw new CommonException('SHOP_ORDER_NOT_FOUND');
           // if(in_array($order_goods_info['delivery_status'],[OrderDeliveryDict::TAKED, OrderDeliveryDict::EXPIRE])) throw new CommonException('SHOP_ORDER_ITEM_HAS_BEEN_WRITTEN_OFF_OR_EXPIRED');
            //todo  判断订单项状态(已收货 已核销  核销已过期)
            $order_info = (new Order())->where([['order_id', '=', $order_goods_info['order_id']], ['member_id', '=', $member_id]])->findOrEmpty();
           // if($order_info->isEmpty()) throw new CommonException('SHOP_ORDER_NOT_FOUND');
           // if(in_array($order_info['status'], [OrderDict::FINISH, OrderDict::CLOSE])) throw new CommonException('SHOP_ORDER_HAS_BEEN_CLOSED_OR_COMPLETED');
            $goods_id = $order_goods_info['goods_id'];
            $goods_info = (new Goods())->findOrEmpty($goods_id);
            if($goods_info->isEmpty()) throw new CommonException('SHOP_GOODS_NOT_EXIST');
            $virtual_receive_type = $goods_info['virtual_receive_type'];
           // if($virtual_receive_type != 'verify') throw new CommonException('SHOP_GOODS_CURRENT_PRODUCT_DOES_NOT_SUPPORT_WRITE_OFF');
            $count = $order_goods_info['num'] - $order_goods_info['verify_count'];

            return [
                'count' => $count,
                'relate_tag' => $order_goods_id,
                'body' => $order_goods_info['goods_name'].$order_goods_info['sku_name'],
                'expire_time' => empty($order_goods_info['verify_expire_time']) ? 0 : strtotime($order_goods_info['verify_expire_time']),
                'data' => [
                    'list' => [
                        [
                            'name' => $order_goods_info['goods_name'].$order_goods_info['sku_name'],
                            'cover' => $order_goods_info['goods_image'],
                            'num' => $order_goods_info['num'],
                        ]
                    ],
                    'content' => [
                        'fixed' => [

                        ],
                        'diy' => [
                            [
                                'title' => '订单信息',
                                'list' => [
                                    [
                                        'title' => '订单编号',
                                        'value' => $order_info['order_no'],
                                    ],
                                    [
                                        'title' => '支付时间',
                                        'value' => $order_info['pay_time'],
                                    ],
                                ]
                            ],
                        ]
                    ]
                ]
            ];

        }

    }
}
