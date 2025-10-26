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

namespace addon\home_service\app\dict\order;


/**
 * 订单相关字典类
 * Class HotelOrderDict
 * @package app\dict\order
 */
class OrderDict
{
    // 订单创建
    const ORDER_CREATION = 'order_creation';

    //订单状态
    //待支付
    const WAIT_PAY = 'wait_pay';

    //待派单
    const DISPATCH = 'wait_dispatch';

    //待派单
    const WAIT_DISPATCH = 'wait_dispatch';
    //待服务
    const WAIT_SERVICE = 'wait_service';
    //服务中
    const IN_SERVICE = 'in_service';
    //异常订单
    const ABNORMAL_ORDER = 'abnormal_order';

    //待验收
    const WAIT_CHECK = 'wait_check';

    //已完成
    const FINISH = 'finish';
    //已关闭
    const CLOSE = 'close';


    const SUB_STATUS_DEPART = 'depart';         // 出发
    const SUB_STATUS_PHOTO_TAKEN = 'photo_taken'; // 拍照
    const SUB_STATUS_ACTION_START = 'action_start'; // 开始服务


    const SERVICE_FINISH = 'service_finish';


    const ORDER_CHECK = 'order_check';


    const ORDER_TYPE_ORDER = 'home_service';
    const ORDER_TYPE_ITEM = 'home_service_item';

    const REPLACE_BUY = 'replace_buy';


    public static function getStatus($status = '', $order_data = [])
    {
        $data = [
            self::WAIT_PAY => [
                'name' => get_lang('dict_home_service_order_status.wait_pay'),
                'status' => self::WAIT_PAY,
                'is_refund' => 0,
                'action' => [
                    //                    [
                    //                        'name' => get_lang('dict_home_service_order_action.action_offline_payment'),
                    //                        'key' => 'action_offline_payment'
                    //                    ]
                ],
            ],
            self::WAIT_DISPATCH => [
                'name' => get_lang('dict_home_service_order_status.wait_dispatch'),
                'status' => self::WAIT_DISPATCH,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_dispatch'),
                        'key' => 'action_dispatch'
                    ]
                ],
            ],
            self::WAIT_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.wait_service'),
                'status' => self::WAIT_SERVICE,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_transfer'),
                        'key' => 'action_transfer'
                    ]
                ],
            ],
            self::IN_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.in_service'),
                'status' => self::IN_SERVICE,
                'is_refund' => 1,
                'action' => [
                ]
            ],
            self::WAIT_CHECK => [
                'name' => get_lang('dict_home_service_order_status.wait_check'),
                'status' => self::WAIT_CHECK,
                'is_refund' => 1,
                'action' => [
                ],
            ],
            self::FINISH => [
                'name' => get_lang('dict_home_service_order_status.order_finish'),
                'status' => self::FINISH,
                'is_refund' => 0,
                'action' => [],
            ],
            self::CLOSE => [
                'name' => get_lang('dict_home_service_order_status.order_close'),
                'status' => self::CLOSE,
                'is_refund' => 0,
                'action' => [
                ],
            ]
        ];
        // 为每个状态添加动态操作
        $data = self::addDynamicActions($data, $status, $order_data);
        if ($status == '') {
            return $data;
        }
        return $data[$status] ?? '';
    }

    /**
     * 为状态添加动态操作按钮
     * 所有动态逻辑集中在这里维护
     */
    private static function addDynamicActions($data, $currentStatus, $order_data)
    {
        // 根据不同状态添加动态操作
        switch ($currentStatus) {
            case self::CLOSE:
                // 删除
                if (isset($order_data['refund_status']) && $order_data['refund_status'] != 'refund_completed') {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_delete'),
                        'key' => 'action_delete'
                    ];
                }
                break;
        }

        return $data;
    }


    public static function getSearchStatus($memberStatus)
    {
        $map = [
            self::WAIT_DISPATCH => [self::WAIT_DISPATCH],
            self::WAIT_SERVICE => [self::WAIT_SERVICE],
            self::IN_SERVICE => [self::IN_SERVICE],
            self::WAIT_CHECK => [self::WAIT_CHECK],
            self::FINISH => [self::FINISH],
            self::CLOSE => [self::CLOSE],
        ];
        return $map[$memberStatus] ?? [$memberStatus];
    }

    public static function getStatisticsStatus($memberStatus)
    {
        $map = [
            self::WAIT_DISPATCH => [self::WAIT_DISPATCH],
            self::WAIT_SERVICE => [self::WAIT_SERVICE],
            self::IN_SERVICE => [self::IN_SERVICE],
            self::WAIT_CHECK => [self::WAIT_CHECK],
            self::FINISH => [self::FINISH],
            self::CLOSE => [self::CLOSE],
        ];
        return $map[$memberStatus] ?? [$memberStatus];
    }



}
