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

namespace addon\hsx_recycle\app\dict\order;

/**
 * 回收订单流程配置字典 - Admin端（管理端）
 *
 * 定义管理员在不同订单状态下可以执行的操作及状态转换规则
 *
 * 配置说明：
 * - actions: 当前状态下管理员可执行的操作列表
 * - transitions: 操作对应的状态转换配置
 *   - to_status: 目标状态
 *   - handler: 处理器类名（位于 service/core/recycle_order/handler/）
 *   - validate: 验证方法列表（可选）
 *   - event_after: 执行后触发的事件方法（可选）
 *   - require_data: 必需的数据字段（可选）
 *
 * @package addon\hsx_recycle\app\dict\order
 */
class RecycleOrderAdminFlowDict
{
    /**
     * Admin端订单流程配置
     *
     * 订单状态说明：
     * 1-待签收, 2-已签收, 3-质检中, 4-已质检, 5-待确认, 6-待打款, 7-已完成, 8-已关闭, 9-已取消
     */
    public const FLOW_CONFIG = [
        // 状态1：待签收
        RecycleOrderDict::ORDER_STATUS_PENDING_SIGN => [
            'status_name' => '待签收',
            'actions' => ['sign', 'cancel', 'close'],
            'transitions' => [
                'sign' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_SIGNED,
                    'handler' => 'SignHandler',
                    'validate' => [],
                    'event_after' => 'orderSignAfter',
                    'require_data' => ['devices'],
                    'description' => '管理员签收订单，录入设备信息'
                ],
                'cancel' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
                    'handler' => 'CancelHandler',
                    'validate' => [],
                    'event_after' => 'orderCancelAfter',
                    'require_data' => ['reason'],
                    'description' => '管理员取消订单'
                ],
                'close' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CLOSED,
                    'handler' => 'CloseHandler',
                    'validate' => [],
                    'event_after' => 'orderCloseAfter',
                    'require_data' => ['reason'],
                    'description' => '管理员关闭订单'
                ]
            ]
        ],

        // 状态2：已签收
        RecycleOrderDict::ORDER_STATUS_SIGNED => [
            'status_name' => '已签收',
            'actions' => ['start_check', 'add_device', 'cancel'],
            'transitions' => [
                'start_check' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CHECKING,
                    'handler' => 'StartCheckHandler',
                    'validate' => ['checkDeviceExists'],
                    'event_after' => 'orderStartCheckAfter',
                    'description' => '开始质检流程'
                ],
                'add_device' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_SIGNED,
                    'handler' => 'AddDeviceHandler',
                    'validate' => [],
                    'require_data' => ['devices'],
                    'description' => '添加设备信息'
                ],
                'cancel' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
                    'handler' => 'CancelHandler',
                    'validate' => [],
                    'event_after' => 'orderCancelAfter',
                    'require_data' => ['reason'],
                    'description' => '取消订单'
                ]
            ]
        ],

        // 状态3：质检中
        RecycleOrderDict::ORDER_STATUS_CHECKING => [
            'status_name' => '质检中',
            'actions' => ['complete_check', 'cancel'],
            'transitions' => [
                'complete_check' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CHECKED,
                    'handler' => 'CompleteCheckHandler',
                    'validate' => ['checkAllDevicesChecked'],
                    'event_after' => 'orderCompleteCheckAfter',
                    'description' => '完成质检'
                ],
                'cancel' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
                    'handler' => 'CancelHandler',
                    'validate' => [],
                    'event_after' => 'orderCancelAfter',
                    'require_data' => ['reason'],
                    'description' => '取消订单'
                ]
            ]
        ],

        // 状态4：已质检
        RecycleOrderDict::ORDER_STATUS_CHECKED => [
            'status_name' => '已质检',
            'actions' => ['set_price', 'cancel'],
            'transitions' => [
                'set_price' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,
                    'handler' => 'SetPriceHandler',
                    'validate' => [],
                    'event_after' => 'orderSetPriceAfter',
                    'require_data' => ['devices'],
                    'description' => '设置设备价格，等待用户确认'
                ],
                'cancel' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
                    'handler' => 'CancelHandler',
                    'validate' => [],
                    'event_after' => 'orderCancelAfter',
                    'require_data' => ['reason'],
                    'description' => '取消订单'
                ]
            ]
        ],

        // 状态5：待确认
        RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM => [
            'status_name' => '待确认',
            'actions' => ['adjust_price', 'force_confirm', 'cancel'],
            'transitions' => [
                'adjust_price' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,
                    'handler' => 'AdjustPriceHandler',
                    'validate' => [],
                    'event_after' => 'orderAdjustPriceAfter',
                    'require_data' => ['devices'],
                    'description' => '调整设备价格（议价后）'
                ],
                'force_confirm' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
                    'handler' => 'ForceConfirmHandler',
                    'validate' => [],
                    'event_after' => 'orderForceConfirmAfter',
                    'description' => '管理员强制确认价格'
                ],
                'cancel' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
                    'handler' => 'CancelHandler',
                    'validate' => [],
                    'event_after' => 'orderCancelAfter',
                    'require_data' => ['reason'],
                    'description' => '取消订单'
                ]
            ]
        ],

        // 状态6：待打款
        RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT => [
            'status_name' => '待打款',
            'actions' => ['payment', 'cancel'],
            'transitions' => [
                'payment' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_COMPLETED,
                    'handler' => 'PaymentHandler',
                    'validate' => [],
                    'event_after' => 'orderPaymentAfter',
                    'require_data' => ['payment_info'],
                    'description' => '完成打款，订单完成'
                ],
                'cancel' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
                    'handler' => 'CancelHandler',
                    'validate' => [],
                    'event_after' => 'orderCancelAfter',
                    'require_data' => ['reason'],
                    'description' => '取消订单'
                ]
            ]
        ],

        // 状态7：已完成
        RecycleOrderDict::ORDER_STATUS_COMPLETED => [
            'status_name' => '已完成',
            'actions' => [],
            'transitions' => []
        ],

        // 状态8：已关闭
        RecycleOrderDict::ORDER_STATUS_CLOSED => [
            'status_name' => '已关闭',
            'actions' => [],
            'transitions' => []
        ],

        // 状态9：已取消
        RecycleOrderDict::ORDER_STATUS_CANCELLED => [
            'status_name' => '已取消',
            'actions' => [],
            'transitions' => []
        ]
    ];

    /**
     * 获取指定状态的流程配置
     *
     * @param int $status 订单状态
     * @return array|null 流程配置，不存在返回null
     */
    public static function getFlowConfig(int $status): ?array
    {
        return self::FLOW_CONFIG[$status] ?? null;
    }

    /**
     * 获取指定状态下的可用操作列表
     *
     * @param int $status 订单状态
     * @return array 操作列表
     */
    public static function getAvailableActions(int $status): array
    {
        $config = self::getFlowConfig($status);
        return $config['actions'] ?? [];
    }

    /**
     * 检查指定状态下是否允许执行某个操作
     *
     * @param int $status 订单状态
     * @param string $action 操作名称
     * @return bool 是否允许
     */
    public static function isActionAllowed(int $status, string $action): bool
    {
        $actions = self::getAvailableActions($status);
        return in_array($action, $actions);
    }

    /**
     * 获取指定操作的转换配置
     *
     * @param int $status 当前状态
     * @param string $action 操作名称
     * @return array|null 转换配置，不存在返回null
     */
    public static function getTransitionConfig(int $status, string $action): ?array
    {
        $config = self::getFlowConfig($status);
        return $config['transitions'][$action] ?? null;
    }

    /**
     * 获取所有状态的流程配置（用于管理后台展示）
     *
     * @return array 完整的流程配置
     */
    public static function getAllFlowConfig(): array
    {
        return self::FLOW_CONFIG;
    }
}
