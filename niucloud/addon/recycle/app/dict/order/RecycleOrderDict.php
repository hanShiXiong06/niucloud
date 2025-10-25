<?php
declare(strict_types=1);

namespace addon\recycle\app\dict\order;

/**
 * 回收订单相关枚举类
 */
class RecycleOrderDict
{
    // 订单状态
    const ORDER_STATUS_PENDING_SIGN = 1;        // 待签收
    const ORDER_STATUS_SIGNED = 2;              // 已签收
    const ORDER_STATUS_CHECKING = 3;            // 质检中
    const ORDER_STATUS_CHECKED = 4;             // 已质检
    const ORDER_STATUS_PENDING_CONFIRM = 5;     // 待确认（定价后等待用户确认）

    const ORDER_STATUS_PENDING_PAYMENT = 6;     // 待打款
    const ORDER_STATUS_COMPLETED = 7;           // 已完成
    const ORDER_STATUS_CLOSED = 8;              // 已关闭（全部退回）
    // 取消状态
    const ORDER_STATUS_CANCELLED = 9;           // 已取消
    const ORDER_STATUS_DELETE = 10;             // 已删除
    const ORDER_STATUS_DETAIL = 11;             // 查看详情
    
    // 设备状态
    const DEVICE_STATUS_PENDING_CHECK = 1;      // 待质检
    const DEVICE_STATUS_CHECKING = 2;           // 质检中
    const DEVICE_STATUS_CHECKED = 3;            // 已质检
    const DEVICE_STATUS_PENDING_CONFIRM = 4;    // 待确认
    const DEVICE_STATUS_RECYCLED = 5;           // 已回收
    const DEVICE_STATUS_RETURNED = 6;           // 已退回
    const DEVICE_STATUS_PRICED = 7;             // 已定价
    const DEVICE_STATUS_PRICED_REPRICE = 8;     // 已定价（重新定价）
    // 质检结果状态
    const CHECK_STATUS_PASS = 1;               // 质检通过
    const CHECK_STATUS_RETURN = 2;             // 退回

    // 设备操作类型
    const DEVICE_OP_TYPE_CHECK = 2;            // 质检
    const DEVICE_OP_TYPE_CHECK_RESULT = 3;    // 质检结果
    const DEVICE_OP_TYPE_PRICE = 4;            // 定价
    const DEVICE_OP_TYPE_RETURN = 5;           // 确认
    const DEVICE_OP_TYPE_REPRICE = 8;          // 重新定价

    // 设备状态文本映射
    const DEVICE_STATUS_TEXT = [
        self::DEVICE_STATUS_PENDING_CHECK => '待质检',
        self::DEVICE_STATUS_CHECKING => '质检中',
        self::DEVICE_STATUS_CHECKED => '已质检',
        self::DEVICE_STATUS_PENDING_CONFIRM => '待确认',
        self::DEVICE_STATUS_RECYCLED => '已回收',
        self::DEVICE_STATUS_RETURNED => '已退回',
        self::DEVICE_STATUS_PRICED => '已定价'
    ];

    // 设备操作类型文本映射
    const DEVICE_OP_TYPE_TEXT = [
        self::DEVICE_OP_TYPE_CHECK => '质检',
        self::DEVICE_OP_TYPE_CHECK_RESULT => '质检完成',
        self::DEVICE_OP_TYPE_PRICE => '定价',
        self::DEVICE_OP_TYPE_RETURN => '确认',
        self::DEVICE_OP_TYPE_REPRICE => '重新定价'
    ];

    

    // 订单状态文本映射
    const ORDER_STATUS_TEXT = [
        self::ORDER_STATUS_PENDING_SIGN => '待签收',
        self::ORDER_STATUS_SIGNED => '已签收',
        self::ORDER_STATUS_CHECKING => '质检中',
        self::ORDER_STATUS_CHECKED => '已质检',
        self::ORDER_STATUS_PENDING_CONFIRM => '待确认',
        self::ORDER_STATUS_PENDING_PAYMENT => '待打款',
        self::ORDER_STATUS_COMPLETED => '已完成',
        self::ORDER_STATUS_CLOSED => '已关闭',
        self::ORDER_STATUS_CANCELLED => '已取消',
        // self::ORDER_STATUS_DELETE => '已删除',
        // self::ORDER_STATUS_DETAIL => '查看详情'
    ];

    // 状态流转规则 - 设备
    const DEVICE_STATUS_FLOW = [
        self::DEVICE_STATUS_PENDING_CHECK => [self::DEVICE_STATUS_CHECKING],
        self::DEVICE_STATUS_CHECKING => [self::DEVICE_STATUS_CHECKED, self::DEVICE_STATUS_PENDING_CONFIRM, self::DEVICE_STATUS_RETURNED],
        self::DEVICE_STATUS_CHECKED => [self::DEVICE_STATUS_PENDING_CONFIRM, self::DEVICE_STATUS_RETURNED],
        self::DEVICE_STATUS_PENDING_CONFIRM => [self::DEVICE_STATUS_RECYCLED, self::DEVICE_STATUS_RETURNED],
        self::DEVICE_STATUS_RECYCLED => [],
        self::DEVICE_STATUS_RETURNED => [],
        self::DEVICE_STATUS_PRICED => [self::DEVICE_STATUS_RETURNED, self::DEVICE_STATUS_RECYCLED]
    ];

    // 状态流转规则 - 订单
    const ORDER_STATUS_FLOW = [
        self::ORDER_STATUS_PENDING_SIGN => [self::ORDER_STATUS_SIGNED, self::ORDER_STATUS_CANCELLED],
        self::ORDER_STATUS_SIGNED => [self::ORDER_STATUS_SIGNED, self::ORDER_STATUS_CHECKING, self::ORDER_STATUS_CANCELLED],
        self::ORDER_STATUS_CHECKING => [self::ORDER_STATUS_CHECKED, self::ORDER_STATUS_PENDING_CONFIRM, self::ORDER_STATUS_CANCELLED],
        self::ORDER_STATUS_CHECKED => [self::ORDER_STATUS_PENDING_CONFIRM, self::ORDER_STATUS_CANCELLED],
        self::ORDER_STATUS_PENDING_CONFIRM => [self::ORDER_STATUS_PENDING_PAYMENT, self::ORDER_STATUS_CLOSED, self::ORDER_STATUS_CANCELLED],
        self::ORDER_STATUS_PENDING_PAYMENT => [self::ORDER_STATUS_COMPLETED, self::ORDER_STATUS_CANCELLED],
        self::ORDER_STATUS_COMPLETED => [self::ORDER_STATUS_DELETE],
        self::ORDER_STATUS_CLOSED => [self::ORDER_STATUS_DELETE],
        self::ORDER_STATUS_CANCELLED => [self::ORDER_STATUS_DELETE],
        self::ORDER_STATUS_DELETE => []
    ];

    // 发货方式
    const DELIVERY_TYPE_EXPRESS = 'express';    // 快递
    const DELIVERY_TYPE_SELF = 'self';         // 自送

    // 订单操作类型
    const ORDER_CREATE = 'order_create';                    // 订单创建
    const ORDER_SIGN = 'order_sign';                        // 商家签收
    const ORDER_CHECK_START = 'order_check_start';          // 开始质检
    const ORDER_CHECK_COMPLETE = 'order_check_complete';    // 质检完成
    const ORDER_PRICE_CONFIRM = 'order_price_confirm';      // 价格确认
    const ORDER_PAYMENT = 'order_payment';                  // 打款
    const ORDER_PAYMENT_CONFIRM = 'order_payment_confirm';  // 确认已打款
    const ORDER_COMPLETE = 'order_complete';                // 订单完成
    const ORDER_CLOSE = 'order_close';                      // 订单关闭
    const ORDER_CANCEL = 'order_cancel';                    // 订单取消
    const ORDER_DELETE = 'order_delete';                    // 订单删除
    const ORDER_DETAIL = 'order_detail';                    // 订单详情
    const ORDER_PUSH_NOTIFY = 'order_push_notify';          // 推送通知
    /**
     * 获取订单状态列表
     * @param string $status
     * @return array
     */
    public static function getOrderStatus($status = '')
    {
        $data = [
            self::ORDER_STATUS_PENDING_SIGN => [
                'name' => '待签收',
                'status' => self::ORDER_STATUS_PENDING_SIGN,
                'action' => [
                    ['key' => self::ORDER_SIGN, 'value' => '签收订单', 'id' => self::ORDER_STATUS_SIGNED],
                    ['key' => self::ORDER_CANCEL, 'value' => '取消订单', 'id' => self::ORDER_STATUS_CANCELLED]
                ],
            ],
            self::ORDER_STATUS_SIGNED => [
                'name' => '已签收',
                'status' => self::ORDER_STATUS_SIGNED,
                'action' => [
                    ['key' => self::ORDER_SIGN, 'value' => '修改订单', 'id' => self::ORDER_STATUS_SIGNED],
                    ['key' => self::ORDER_CANCEL, 'value' => '取消订单', 'id' => self::ORDER_STATUS_CANCELLED]
                ],
            ],
            self::ORDER_STATUS_CHECKING => [
                'name' => '质检中',
                'status' => self::ORDER_STATUS_CHECKING,
                'action' => [
                    // ['key' => self::ORDER_CHECK_COMPLETE, 'value' => '完成质检', 'id' => self::ORDER_STATUS_CHECKED],
                    // ['key' => self::ORDER_CANCEL, 'value' => '取消订单', 'id' => self::ORDER_STATUS_CANCELLED]
                ],
            ],
            self::ORDER_STATUS_CHECKED => [
                'name' => '已质检',
                'status' => self::ORDER_STATUS_CHECKED,
                'action' => [
                    // ['key' => self::ORDER_PRICE_CONFIRM, 'value' => '确认价格', 'id' => self::ORDER_STATUS_PENDING_CONFIRM],
                    // ['key' => self::ORDER_CANCEL, 'value' => '取消订单', 'id' => self::ORDER_STATUS_CANCELLED]
                ],
            ],
            self::ORDER_STATUS_PENDING_CONFIRM => [
                'name' => '待确认',
                'status' => self::ORDER_STATUS_PENDING_CONFIRM,
                'action' => [
                    ['key' => self::ORDER_PUSH_NOTIFY, 'value' => '推送通知', 'id' => self::ORDER_STATUS_PENDING_CONFIRM],
                    ['key' => self::ORDER_DETAIL, 'value' => '查看详情', 'id' => self::ORDER_STATUS_DETAIL]
                ],
            ],
            self::ORDER_STATUS_PENDING_PAYMENT => [
                'name' => '待打款',
                'status' => self::ORDER_STATUS_PENDING_PAYMENT,
                'action' => [
                    ['key' => self::ORDER_PAYMENT, 'value' => '确认打款', 'id' => self::ORDER_STATUS_PENDING_PAYMENT],
                    
                   //  ['key' => self::ORDER_COMPLETE, 'value' => '完成订单', 'id' => self::ORDER_STATUS_COMPLETED],
                   //  ['key' => self::ORDER_CANCEL, 'value' => '取消订单', 'id' => self::ORDER_STATUS_CANCELLED]
                ],
            ],
            self::ORDER_STATUS_COMPLETED => [
                'name' => '已完成',
                'status' => self::ORDER_STATUS_COMPLETED,
                'action' => [
                    ['key' => self::ORDER_DELETE, 'value' => '删除订单', 'id' => self::ORDER_STATUS_DELETE],
                    //查看详情
                    ['key' => self::ORDER_DETAIL, 'value' => '查看详情', 'id' => self::ORDER_STATUS_DETAIL]
                ],
            ],
            self::ORDER_STATUS_CLOSED => [
                'name' => '已关闭',
                'status' => self::ORDER_STATUS_CLOSED,
                'action' => [
                    ['key' => self::ORDER_DELETE, 'value' => '删除订单', 'id' => self::ORDER_STATUS_DELETE],
                    ['key' => self::ORDER_DETAIL, 'value' => '查看详情', 'id' => self::ORDER_STATUS_DETAIL]
                ],
            ],
            self::ORDER_STATUS_CANCELLED => [
                'name' => '已取消',
                'status' => self::ORDER_STATUS_CANCELLED,
                'action' => [
                    ['key' => self::ORDER_DELETE, 'value' => '删除订单', 'id' => self::ORDER_STATUS_DELETE]
                ],
            ],
            // self::ORDER_STATUS_DELETE => [
            //     'name' => '已删除',
            //     'status' => self::ORDER_STATUS_DELETE,
            //     'action' => [],
            // ],
        ];

        return empty($status) ? $data : ($data[$status] ?? []);
    }

    /**
     * 获取设备状态列表
     * @param string $status
     * @return array
     */
    public static function getDeviceStatus($status = '')
    {
        $data = [
            self::DEVICE_STATUS_PENDING_CHECK => '待质检',
            self::DEVICE_STATUS_CHECKING => '质检中',
            self::DEVICE_STATUS_CHECKED => '已质检',
            self::DEVICE_STATUS_PENDING_CONFIRM => '待确认',
            self::DEVICE_STATUS_RECYCLED => '已回收',
            self::DEVICE_STATUS_RETURNED => '已退回',
        ];

        return empty($status) ? $data : ($data[$status] ?? '');
    }

    /**
     * 获取设备操作类型列表
     * @param string $type
     * @return array
     */
    public static function getDeviceOpType($type = ''){

    
        // 获取设备操作类型
        $data = [
            self::DEVICE_OP_TYPE_CHECK => '开始质检',
            self::DEVICE_OP_TYPE_PRICE => '定价',
            self::DEVICE_OP_TYPE_CHECK_RESULT => '质检完成',
            self::DEVICE_OP_TYPE_RETURN => '确认',
            self::DEVICE_OP_TYPE_REPRICE => '重新定价'
        ];

        return empty($type) ? $data : ($data[$type] ?? '');
        
    }

    /**
     * 获取发货方式列表
     * @param string $type
     * @return array
     */
    public static function getDeliveryType($type = '')
    {
        $data = [self::DELIVERY_TYPE_SELF => '自送',
            self::DELIVERY_TYPE_EXPRESS => '快递'
            
        ];

        return empty($type) ? $data : ($data[$type] ?? '');
    }

    /**
     * 获取订单状态ID
     * @param string $status 状态关键字
     * @return int 状态ID
     */
    public static function getOrderStatusId($status)
    {
        $statusMap = [
            'cancel' => self::ORDER_STATUS_CANCELLED,
            'confirm' => self::ORDER_STATUS_PENDING_PAYMENT,
            'delete' => self::ORDER_STATUS_DELETE,
            'sign' => self::ORDER_STATUS_SIGNED,
            'check_start' => self::ORDER_STATUS_CHECKING,
            'check_complete' => self::ORDER_STATUS_CHECKED,
            'price_confirm' => self::ORDER_STATUS_PENDING_CONFIRM,
            'payment' => self::ORDER_STATUS_PENDING_PAYMENT,
            'complete' => self::ORDER_STATUS_COMPLETED,
            'close' => self::ORDER_STATUS_CLOSED,
            'detail' => self::ORDER_STATUS_DETAIL
        ];
        
        return $statusMap[$status] ?? 0;
    }
    
    /**
     * 检查状态流转是否合法
     * @param int $currentStatus 当前状态
     * @param int $targetStatus 目标状态
     * @param string $type 类型：order或device
     * @return bool
     */
    public static function isValidStatusTransition($currentStatus, $targetStatus, $type = 'order')
    {
        $flowMap = $type === 'order' ? self::ORDER_STATUS_FLOW : self::DEVICE_STATUS_FLOW;
        
        if (!isset($flowMap[$currentStatus])) {
            return false;
        }
        
        return in_array($targetStatus, $flowMap[$currentStatus]);
    }
}
