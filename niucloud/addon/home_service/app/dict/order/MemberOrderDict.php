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

use addon\home_service\app\service\core\order\CoreOrderConfigService;
use app\dict\pay\PayDict;

/**
 * 订单相关字典类
 * Class HotelOrderDict
 * @package app\dict\order
 */
class MemberOrderDict
{
    //订单状态
    //待支付
    const WAIT_PAY = 'wait_pay';
    //待服务
    const WAIT_SERVICE = 'wait_service';
    //服务中
    const IN_SERVICE = 'in_service';
    //待验收
    const WAIT_CHECK = 'wait_check';
    //已完成
    const FINISH = 'finish';
    //已关闭
    const CLOSE = 'close';


    // 1. 定义系统状态到会员视角状态的映射（核心：wait_dispatch → WAIT_SERVICE）
    private static $statusMap = [
        'wait_dispatch' => self::WAIT_SERVICE, // 系统待派单 → 会员待服务
        'wait_pay' => self::WAIT_PAY,
        'wait_service' => self::WAIT_SERVICE,
        'in_service' => self::IN_SERVICE,
        'wait_check' => self::WAIT_CHECK,
        'finish' => self::FINISH,
        'close' => self::CLOSE,
    ];


    public static function getStatus($status = '', $order_data = [])
    {
        // 关键：将系统实际状态转换为会员视角状态
        $mappedStatus = self::$statusMap[$status] ?? $status;
        // 获取状态配置（只定义静态部分）
        $data = [
            self::WAIT_PAY => [
                'name' => get_lang('dict_home_service_order_status.wait_pay'),
                'status' => self::WAIT_PAY,
                'is_refund' => 0,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_pay'),
                        'key' => 'action_pay',
                        'color' => '#0C54F3'
                    ],
                    [
                        'name' => get_lang('dict_home_service_order_action.action_cancel'),
                        'key' => 'action_cancel'
                    ]
                ],

            ],
            self::WAIT_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.wait_service'),
                'status' => self::WAIT_SERVICE,
                'is_refund' => 1,
                'action' => [
//                    [
//                        'name' => get_lang('dict_home_service_order_action.action_refund'),
//                        'key' => 'action_refund',
//                        'color' => '#EF000C'
//                    ]
                ],
            ],
            self::IN_SERVICE => [
                'name' => get_lang('dict_home_service_order_status.in_service'),
                'status' => self::IN_SERVICE,
                'is_refund' => 1,
                'action' => [
//                    [
//                        'name' => get_lang('dict_home_service_order_action.action_refund'),
//                        'key' => 'action_refund',
//                        'color' => '#EF000C'
//                    ]
                ]
            ],
            self::WAIT_CHECK => [
                'name' => get_lang('dict_home_service_order_status.wait_check'),
                'status' => self::WAIT_CHECK,
                'is_refund' => 1,
                'action' => [
//                    [
//                        'name' => get_lang('dict_home_service_order_action.action_refund'),
//                        'key' => 'action_refund',
//                        'color' => '#EF000C'
//                    ]
                ],
            ],
            self::FINISH => [
                'name' => get_lang('dict_home_service_order_status.order_finish'),
                'status' => self::FINISH,
                'is_refund' => 0,
                'action' => [
                    [
                        'name' => get_lang('dict_home_service_order_action.action_order_again'),
                        'key' => 'action_order_again',
                        'color' => '#0C54F3'
                    ],
                ],
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
        $data = self::addDynamicActions($data, $mappedStatus, $order_data);
        if ($status !== '') {
            return $data[$mappedStatus] ?? '';
        }

        return $data;
    }

    /**
     * 为状态添加动态操作按钮
     * 所有动态逻辑集中在这里维护
     */
    private static function addDynamicActions($data, $currentStatus, $order_data)
    {
        $technicianId = isset($order_data['technician_id']) ? (int)$order_data['technician_id'] : 0;
        // 根据不同状态添加动态操作
        switch ($currentStatus) {
            case self::WAIT_SERVICE:
                if (!$order_data['is_card_order'] || $technicianId == 0) {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_refund'),
                        'key' => 'action_refund',
                        'color' => '#EF000C'
                    ];
                }
                if ($technicianId != 0) {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_contact_technician'),
                        'key' => 'action_contact_technician'
                    ];
                }
                break;
            case self::IN_SERVICE:
                // 添加联系师傅操作（条件性显示）
                if (!$order_data['is_card_order']) {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_refund'),
                        'key' => 'action_refund',
                        'color' => '#EF000C'
                    ];
                }
                if ($technicianId != 0) {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_contact_technician'),
                        'key' => 'action_contact_technician'
                    ];
                }
                if ($order_data['order_money'] > $order_data['pay_money']) {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_item_pay'),
                        'key' => 'action_item_pay',
                        'color' => '#0C54F3'
                    ];
                }
                break;
            case self::WAIT_CHECK:
                // 等待审核状态的动态操作
                if (!$order_data['is_card_order']) {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_refund'),
                        'key' => 'action_refund',
                        'color' => '#EF000C'
                    ];
                }
                if ($technicianId != 0) {
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.action_contact_technician'),
                        'key' => 'action_contact_technician'
                    ];
                }
                if (isset($order_data['refund_status']) && empty($order_data['refund_status'])){
                    $data[$currentStatus]['action'][] = [
                        'name' => get_lang('dict_home_service_order_action.again_check'),
                        'key' => 'again_check',
                        'color' => '#00B815'
                    ];
                }
                break;
            case self::FINISH:
                // 评价
               $evaluateConfig = (new CoreOrderConfigService())->getEvaluateConfig($order_data['site_id']);
                if (isset($evaluateConfig['is_evaluate']) && $evaluateConfig['is_evaluate']){
                    if (isset($order_data['is_evaluate']) && $order_data['is_evaluate']==0) {
                        $data[$currentStatus]['action'][] = [
                            'name' => get_lang('dict_home_service_order_action.action_order_review_share'),
                            'key' => 'action_order_review_share',
                            'color' => '#00B815'
                        ];
                    }
                }
                break;



        }

        return $data;
    }


    public static function getSearchStatus($memberStatus)
    {
        $map = [
            self::WAIT_SERVICE => [OrderDict::WAIT_DISPATCH, OrderDict::WAIT_SERVICE],
            self::WAIT_PAY => [OrderDict::WAIT_PAY],
            self::IN_SERVICE => [OrderDict::IN_SERVICE],
            self::WAIT_CHECK => [OrderDict::WAIT_CHECK],
            self::FINISH => [OrderDict::FINISH],
            self::CLOSE => [OrderDict::CLOSE],
        ];
        return $map[$memberStatus] ?? [$memberStatus];
    }


}
