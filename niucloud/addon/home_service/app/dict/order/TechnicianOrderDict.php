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
class TechnicianOrderDict
{

    //待服务
    const WAIT_SERVICE = 'wait_service';
    //服务中
    const IN_SERVICE = 'in_service';

    //待验收
    const WAIT_CHECK = 'wait_check';
    //异常订单
    const ABNORMAL_ORDER = 'abnormal_order';

    //已完成
    const FINISH = 'finish';
    //已关闭
    const CLOSE = 'close';


    const SUB_STATUS_DEPART = 'depart';         // 出发
    const SUB_STATUS_PHOTO_TAKEN = 'photo_taken'; // 拍照
    const SUB_STATUS_ACTION_START = 'action_start'; // 开始服务


    /**
     * $status  大订单状态
     * $sub_status  子订单状态 特殊业务
     */
    public static function getStatus($status = '', $order_data = [])
    {
        $sub_status = $order_data['sub_status'] ?? "";
        $data = [
            self::WAIT_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.wait_service'),
                'status' => self::WAIT_SERVICE,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_sub_depart'),
                        'key' => 'action_depart'
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.action_sub_photo_taken'),
                        'key' => 'action_photo_taken'
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.action_start'),
                        'key' => 'action_action_start'
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.edit_reserve_service_time'),
                        'key' => 'edit_reserve_service_time'
                    ],
                ],
            ],
            self::IN_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.in_service'),
                'status' => self::IN_SERVICE,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_order_item'),
                        'key' => 'action_order_item'
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.action_save_check'),
                        'key' => 'action_save_check'
                    ],
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
                'action' => [],
            ]
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
        // 为每个状态添加动态操作
        $return = self::addDynamicActions($return, $status, $order_data);

        return $return;
    }

    /**
     * 为状态添加动态操作按钮
     * 所有动态逻辑集中在这里维护
     */
    private static function addDynamicActions($data, $currentStatus, $order_data)
    {

        // 根据不同状态添加动态操作
        switch ($currentStatus) {
            case self::WAIT_SERVICE:
                // 修改预约时间
                    $data['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.edit_reserve_service_time'),
                        'key' => 'edit_reserve_service_time'
                    ];
                break;
        }
        return $data;
    }


    public static function getTaskStatus($status = '', $sub_status = '')
    {
        $data = [
            self::WAIT_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.wait_service'),
                'status' => self::WAIT_SERVICE,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_sub_depart'),
                        'key' => 'action_depart'
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.action_sub_photo_taken'),
                        'key' => 'action_photo_taken'
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.action_start'),
                        'key' => 'action_action_start'
                    ],
                ],
            ],
            self::IN_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.in_service'),
                'status' => self::IN_SERVICE,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_save_check'),
                        'key' => 'action_save_check'
                    ],
                ]
            ],
            self::WAIT_CHECK => [
                'name' => get_lang('dict_home_service_order_status.wait_check'),
                'status' => self::WAIT_CHECK,
                'is_refund' => 1,
                'action' => [
                ],
            ],
            OrderDict::ABNORMAL_ORDER => [
                'name' => get_lang('dict_home_service_order_status.abnormal_order'),
                'status' => OrderDict::ABNORMAL_ORDER,
                'is_refund' => 1,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_sub_depart'),
                        'key' => 'action_depart'
                    ],
                ],
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
        ];
        return $map[$memberStatus] ?? [$memberStatus];
    }


}
