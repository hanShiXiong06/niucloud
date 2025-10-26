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

use app\dict\pay\PayDict;

/**
 * 门店订单相关字典类
 * Class HotelOrderDict
 * @package app\dict\order
 */
class StoreOrderDict
{
    //待派单
    const WAIT_DISPATCH = 'wait_dispatch';
    //待服务
    const WAIT_SERVICE = 'wait_service';
    //服务中
    const IN_SERVICE = 'in_service';
    // 异常订单
    const IN_PROGRESS = 'in_progress';

    // 进行中
    const ABNORMAL_ORDER = 'abnormal_order';


    //待验收
    const WAIT_CHECK = 'wait_check';

    //已完成
    const FINISH = 'finish';
    //已关闭
    const CLOSE = 'close';


    const ORDER_TYPE_ORDER = 'home_service';


    public static function getStatus($status = '', $data = [])
    {
        $data = [
            self::WAIT_DISPATCH => [
                'name' => get_lang('dict_home_service_order_status.wait_dispatch'),
                'status' => self::WAIT_DISPATCH,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_dispatch'),
                        'key' => 'action_dispatch'
                    ],
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
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.action_reminder'),
                        'key' => 'action_reminder'
                    ],
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
                    [
                        'name' => get_lang('dict_home_service_order_action.again_check'),
                        'key' => 'again_check'
                    ],
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
                'action' => [],
            ]
        ];

        if ($status == '') {
            return $data;
        }
        return $data[$status] ?? '';
    }


    public static function getTaskStatus($status = '', $sub_status = '')
    {
        $data = [
            self::WAIT_DISPATCH => [
                'name' => get_lang('dict_home_service_order_status.wait_dispatch'),
                'status' => self::WAIT_DISPATCH,
            ],
            self::IN_PROGRESS => [
                'name' => get_lang('dict_home_service_order_status.in_progress'),
                'status' => self::IN_PROGRESS,
            ],
            OrderDict::ABNORMAL_ORDER => [
                'name' => get_lang('dict_home_service_order_status.abnormal_order'),
                'status' => OrderDict::ABNORMAL_ORDER,
            ],
        ];
        if ($status == '') {
            return $data;
        }
        $return = $data[$status] ?? '';
        if (!empty($sub_status)) {
            $targetKey = 'action_' . $sub_status; // depart → action_depart
            // 遍历找到匹配的操作（只返回第一个匹配项）
            foreach ($return['action'] as $action) {
                if ($action['key'] === $targetKey) {
                    $return['action'] = [$action]; // 只保留匹配的操作
                    break;
                }
            }
        }
        return $return;
    }

    public static function getSearchStatus($memberStatus)
    {
        $map = [
            OrderDict::WAIT_DISPATCH => [OrderDict::WAIT_DISPATCH],
            self::WAIT_SERVICE => [OrderDict::WAIT_SERVICE],
            self::IN_SERVICE => [OrderDict::IN_SERVICE],
            self::WAIT_CHECK => [OrderDict::WAIT_CHECK],
            self::ABNORMAL_ORDER => [OrderDict::ABNORMAL_ORDER],
            self::FINISH => [OrderDict::FINISH],
            self::CLOSE => [OrderDict::CLOSE],
            self::IN_PROGRESS => [OrderDict::WAIT_SERVICE, OrderDict::IN_SERVICE, OrderDict::WAIT_CHECK],
        ];
        return $map[$memberStatus] ?? [$memberStatus];
    }


}
