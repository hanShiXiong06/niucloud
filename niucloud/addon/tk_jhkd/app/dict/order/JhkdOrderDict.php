<?php

namespace addon\tk_jhkd\app\dict\order;

use app\dict\pay\PayDict;

/**
 *订单相关枚举类
 */
class JhkdOrderDict
{
    //订单状态
    //待支付
    const WAIT_PAY = 0;
    //已支付
    const FINISH_PAY = 1;
    const FINISH_PICK = 2;
    //已完成
    const FINISH = 10;
    //已关闭
    const CLOSE = -1;
    const REFUND_OVER = 11;

    // 退款相关状态
    // 未申请
    const NOT_APPLAY = 0;
    // 退款中
    const REFUNDING = 1;
    // 退款完成
    const REFUND_COMPLETED = 2;
    // 退款失败
    const REFUND_FAIL = -1;

    /**
     * 当前订单支持的支付方式
     */
    const ALLOW_PAY = [
        PayDict::WECHATPAY,
        PayDict::ALIPAY,
        PayDict::OFFLINEPAY,
    ];

    /**
     * 订单类型以及名称
     * @return array
     */
    public static function getOrderType()
    {
        return [
            'type' => 'jhkdOrderPay',
            'name' => '聚合快递付款'
        ];
    }

    public static function getStatus($status = '')
    {
        $data = [
            self::WAIT_PAY => [
                'name' => '待支付',
                'status' => self::WAIT_PAY,
                'is_refund' => 0,
                'action' => [],
                'member_action' => [
                    [
                        'name' => '关闭订单',
                        'class' => 'close',
                        'params' => ''
                    ],
                    [
                        'name' => '立即支付',
                        'class' => 'gopay',
                        'params' => ''
                    ],
                ],
            ],
            self::FINISH_PAY => [
                'name' => '已支付',
                'status' => self::FINISH_PAY,
                'is_refund' => 1,
                'action' => [],
                'member_action' => [
                    [
                        'name' => '申请退款',
                        'class' => 'refund',
                        'params' => ''
                    ],
                ],
            ],
            self::FINISH => [
                'name' => '已完成',
                'status' => self::FINISH,
                'is_refund' => 0,
                'action' => [],
                'member_action' => [
//                    [
//                        'name' => '立即评价',
//                        'class' => 'evaluate',
//                        'params' => ''
//                    ],
//                    [
//                        'name' => '再来一单',
//                        'class' => 'again',
//                        'params' => ''
//                    ],
                    [
                        'name' => '删除订单',
                        'class' => 'del',
                        'params' => ''
                    ],
                ],
            ],
            self::CLOSE => [
                'name' => '已关闭',
                'status' => self::CLOSE,
                'is_refund' => 0,
                'action' => [],
                'member_action' => [
                    [
                        'name' => '删除订单',
                        'class' => 'del',
                        'params' => ''
                    ],
                ],
            ],
            self::FINISH_PICK => [
                'name' => '在途中',
                'status' =>  self::FINISH_PICK,
                'is_refund' => 0,
                'action' => [],
                'member_action' => [

                ],
            ]


        ];
        if ($status == '') {
            return $data;
        }
        return $data[$status] ?? '';
    }

    /**
     * 获取退款状态
     * @param string $status
     * @return array|array[]|string
     */
    public static function getRefundStatus(string $status = '')
    {
        $data = [
            self::REFUNDING => [
                'name' => '正在退款',
                'status' => self::REFUNDING
            ],
            self::REFUND_COMPLETED => [
                'name' => '退款完成',
                'status' => self::REFUND_COMPLETED
            ],
            self::REFUND_FAIL => [
                'name' => '退款失败',
                'status' => self::REFUND_FAIL
            ]
        ];

        if ($status == '') {
            return $data;
        }
        return $data[$status] ?? '';
    }

}