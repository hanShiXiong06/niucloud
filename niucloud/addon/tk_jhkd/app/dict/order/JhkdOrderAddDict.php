<?php
namespace addon\tk_jhkd\app\dict\order;

use app\dict\pay\PayDict;

/**
 *订单相关枚举类
 */
class JhkdOrderAddDict
{
    //订单状态
    //待支付
    const WAIT_PAY = 0;
    //已支付
    const FINISH_PAY = 1;
    //已完成
    const FINISH = 10;
    //已关闭
    const CLOSE = -1;
    const REFUND_OVER=11;

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
            'type' => 'jhkdOrderAddPay',
            'name' => '聚合快递补差价付款'
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
                        'name' => '立即支付',
                        'class' => 'gopay',
                        'params' => ''
                    ],
                ],
            ],
            self::FINISH_PAY=>[
                'name' => '已支付',
                'status' => self::FINISH_PAY,
                'is_refund' => 1,
                'action' => [],
                'member_action' => [
                    [
                        'name' => '删除订单',
                        'class' => 'del',
                        'params' => ''
                    ],
                ],
            ],

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