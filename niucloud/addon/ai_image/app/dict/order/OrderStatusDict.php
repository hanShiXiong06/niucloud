<?php

namespace addon\ai_image\app\dict\order;
class OrderStatusDict
{
    const WAIT_PAY = 1;
    const FINISH = 10;
    const CLOSE = -1;

    public static function getOrderType()
    {
        return [
            'type' => 'aiimagePay',
            'name' => 'AI设计套餐下单'
        ];
    }

    public static function getOrderStatus($status = '')
    {
        $data = [
            self::WAIT_PAY => [
                'name' => '待支付',
                'status' => self::WAIT_PAY,
                'member_action' => [
                    [
                        'name' => '立即支付',
                        'class' => 'gopay',
                        'params' => ''
                    ],
                    [
                        'name' => '关闭订单',
                        'class' => 'close',
                        'params' => ''
                    ],
                ],
            ],
            self::FINISH => [
                'name' => '已完成',
                'status' => self::FINISH,
            ],
            self::CLOSE => [
                'name' => '已关闭',
                'status' => self::CLOSE,
                'member_action' => [
                    [
                        'name' => '删除订单',
                        'class' => 'del',
                        'params' => ''
                    ],
                ],
            ],
        ];
        if ($status != '') {
            return $data[$status];
        } else {
            return $data;
        }
    }

    public static function getApiOrderStatus($status = '')
    {
        $data = [
            self::WAIT_PAY => [
                'name' => '待支付',
                'status' => self::WAIT_PAY,
                'member_action' => [
                    [
                        'name' => '立即支付',
                        'class' => 'gopay',
                        'params' => ''
                    ],
                    [
                        'name' => '关闭订单',
                        'class' => 'close',
                        'params' => ''
                    ],
                ],
            ],
            self::FINISH => [
                'name' => '已完成',
                'status' => self::FINISH,
                'member_action' => [

                ],
            ],



        ];
        if ($status != '') {
            return $data[$status];
        } else {
            return $data;
        }
    }
}
