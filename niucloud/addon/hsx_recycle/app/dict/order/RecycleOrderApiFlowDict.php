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
 * 回收订单流程配置字典 - API端（用户端）
 *
 * 定义用户在不同订单状态下可以执行的操作及状态转换规则
 *
 * 配置说明：
 * - actions: 当前状态下用户可执行的操作列表
 * - transitions: 操作对应的状态转换配置
 *   - to_status: 目标状态
 *   - handler: 处理器类名（位于 service/core/recycle_order/handler/）
 *   - validate: 验证方法列表（可选）
 *   - event_after: 执行后触发的事件方法（可选）
 *
 * @package addon\hsx_recycle\app\dict\order
 */
class RecycleOrderApiFlowDict
{
    /**
     * API端订单流程配置
     *
     * 订单状态说明：
     * 1-待签收, 2-已签收, 3-质检中, 4-已质检, 5-待确认, 6-待打款, 7-已完成, 8-已关闭, 9-已取消
     */
    public const FLOW_CONFIG = [
        // 状态1：待签收
        RecycleOrderDict::ORDER_STATUS_PENDING_SIGN => [
            'status_name' => '待签收',
            'actions' => ['cancel'], // 用户可以取消订单
            'transitions' => [
                'cancel' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
                    'handler' => 'CancelHandler',
                    'validate' => [], // 可添加验证规则，如 ['checkCancelTime']
                    'event_after' => 'orderCancelAfter',
                    'description' => '用户取消订单'
                ]
            ]
        ],

        // 状态2：已签收
        RecycleOrderDict::ORDER_STATUS_SIGNED => [
            'status_name' => '已签收',
            'actions' => ['confirm_receipt'], // 用户确认收货
            'transitions' => [
                'confirm_receipt' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_CHECKING,
                    'handler' => 'ConfirmReceiptHandler',
                    'validate' => [],
                    'event_after' => 'orderConfirmReceiptAfter',
                    'description' => '用户确认收货，进入质检流程'
                ]
            ]
        ],

        // 状态3：质检中 - 用户无操作权限
        RecycleOrderDict::ORDER_STATUS_CHECKING => [
            'status_name' => '质检中',
            'actions' => [], // 质检中用户无法操作
            'transitions' => []
        ],

        // 状态4：已质检 - 用户无操作权限
        RecycleOrderDict::ORDER_STATUS_CHECKED => [
            'status_name' => '已质检',
            'actions' => [], // 等待管理员定价
            'transitions' => []
        ],

        // 状态5：待确认
        RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM => [
            'status_name' => '待确认',
            'actions' => ['confirm_price', 'negotiate'], // 用户可以确认价格或议价
            'transitions' => [
                'confirm_price' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
                    'handler' => 'ConfirmPriceHandler',
                    'validate' => [],
                    'event_after' => 'orderConfirmPriceAfter',
                    'description' => '用户确认价格，等待打款'
                ],
                'negotiate' => [
                    'to_status' => RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM, // 保持当前状态
                    'handler' => 'NegotiateHandler',
                    'validate' => [],
                    'event_after' => 'orderNegotiateAfter',
                    'description' => '用户发起议价'
                ]
            ]
        ],

        // 状态6：待打款 - 用户无操作权限
        RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT => [
            'status_name' => '待打款',
            'actions' => [], // 等待管理员打款
            'transitions' => []
        ],

        // 状态7：已完成 - 订单完成，无操作
        RecycleOrderDict::ORDER_STATUS_COMPLETED => [
            'status_name' => '已完成',
            'actions' => [],
            'transitions' => []
        ],

        // 状态8：已关闭 - 订单关闭，无操作
        RecycleOrderDict::ORDER_STATUS_CLOSED => [
            'status_name' => '已关闭',
            'actions' => [],
            'transitions' => []
        ],

        // 状态9：已取消 - 订单取消，无操作
        RecycleOrderDict::ORDER_STATUS_CANCELLED => [
            'status_name' => '已取消',
            'actions' => [],
            'transitions' => [
                // 'delete' => [
                //     'to_status' => RecycleOrderDict::ORDER_STATUS_DELETE,
                //     'handler' => 'DeleteHandler',
                //     'validate' => [],
                //     'event_after' => 'orderDeleteAfter',
                //     'description' => '用户删除订单'
                // ]
            ]
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
}