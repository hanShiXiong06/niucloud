<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\dict\card;

use app\dict\pay\PayDict;

/**
 * 订单相关字典类
 * Class CardOrderDict
 * @package app\dict\card
 */
class CardOrderDict
{
    //订单状态
    //待支付
    const WAIT_PAY = 'wait_pay';
    //已完成
    const FINISH = 'finish';
    //已关闭
    const CLOSE = 'close';

    const TYPE = 'home_service_card';

    /**
     * 当前订单支持的支付方式
     */
    const ALLOW_PAY = [
        PayDict::WECHATPAY,
        PayDict::ALIPAY,
        PayDict::BALANCEPAY
    ];

    public static function getStatus($status = '')
    {
        $data = [
            self::WAIT_PAY => [
                'name' => get_lang('dict_home_service_card_order_status.wait_pay'),
                'status' => self::WAIT_PAY,
                'is_refund' => 0,
                'action' => [],
                'member_action' => [
                    [
                        'name' => get_lang('dict_home_service_card_order_action.action_pay'),
                        'key' => 'pay'
                    ],
                    [
                        'name' => get_lang('dict_home_service_card_order_action.action_cancel'),
                        'key' => 'cancel'
                    ]
                ],
            ],
            self::FINISH => [
                'name' =>  get_lang('dict_home_service_card_order_status.order_finish'),
                'status' => self::FINISH,
                'is_refund' => 0,
                'action' => [],
                'member_action' => [
                ],
            ],
            self::CLOSE => [
                'name' => get_lang('dict_home_service_card_order_status.order_close'),
                'status' => self::CLOSE,
                'is_refund' => 0,
                'action' => [],
                'member_action' => [
                    [
                        'name' => get_lang('dict_home_service_card_order_action.action_delete'),
                        'key' => 'delete'
                    ]
                ]
            ]
        ];

        if ($status == '') {
            return $data;
        }
        return $data[ $status ] ?? '';
    }
}
