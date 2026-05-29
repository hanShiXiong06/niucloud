<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\order;

/**
 * 退回订单常量字典
 * Class RecycleReturnOrderDict
 * @package addon\hsx_recycle\app\dict\order
 */
class RecycleReturnOrderDict
{
    /**
     * 退回订单状态
     */
    // 待处理
    const ORDER_STATUS_PENDING = 0;
    // 退货中
    const ORDER_STATUS_RETURNING = 1;
    // 已完成
    const ORDER_STATUS_COMPLETED = 2;
    // 已取消
    const ORDER_STATUS_CANCELLED = 3;

    /**
     * 设备状态
     */
    // 待检测
    const DEVICE_STATUS_PENDING = 0;
    // 检测中
    const DEVICE_STATUS_CHECKING = 1;
    // 已回收
    const DEVICE_STATUS_RECYCLED = 2;
    // 已退回
    const DEVICE_STATUS_RETURNED = 3;
    // 已取消
    const DEVICE_STATUS_CANCELLED = 4;
    // 退回中
    const DEVICE_STATUS_RETURNING = 6;

    /**
     * 退货发货方式
     */
    // 系统快递
    const SHIPMENT_MODE_SYSTEM = 'system';
    // 手动录入快递
    const SHIPMENT_MODE_MANUAL = 'manual';
    // 物流车
    const SHIPMENT_MODE_LOGISTICS_CAR = 'logistics_car';
    // 自取
    const SHIPMENT_MODE_SELF_PICKUP = 'self_pickup';

    /**
     * 获取订单状态列表
     * @return array
     */
    public static function getOrderStatusList(): array
    {
        return [
            self::ORDER_STATUS_PENDING => [
                'status' => self::ORDER_STATUS_PENDING,
                'name' => '待处理',
                'desc' => '用户或商户发起退回申请，等待平台处理'
            ],
            self::ORDER_STATUS_RETURNING => [
                'status' => self::ORDER_STATUS_RETURNING,
                'name' => '退货中',
                'desc' => '平台已确认退回申请，等待用户寄回设备'
            ],
            self::ORDER_STATUS_COMPLETED => [
                'status' => self::ORDER_STATUS_COMPLETED,
                'name' => '已完成',
                'desc' => '平台已收到退回设备，退回流程完成'
            ],
            self::ORDER_STATUS_CANCELLED => [
                'status' => self::ORDER_STATUS_CANCELLED,
                'name' => '已取消',
                'desc' => '退回申请被取消'
            ]
        ];
    }

    /**
     * 获取设备状态列表
     * @return array
     */
    public static function getDeviceStatusList(): array
    {
        return [
            self::DEVICE_STATUS_PENDING => [
                'status' => self::DEVICE_STATUS_PENDING,
                'name' => '待检测',
                'desc' => '设备等待检测'
            ],
            self::DEVICE_STATUS_CHECKING => [
                'status' => self::DEVICE_STATUS_CHECKING,
                'name' => '检测中',
                'desc' => '设备正在检测中'
            ],
            self::DEVICE_STATUS_RECYCLED => [
                'status' => self::DEVICE_STATUS_RECYCLED,
                'name' => '已回收',
                'desc' => '设备已被平台回收'
            ],
            self::DEVICE_STATUS_RETURNED => [
                'status' => self::DEVICE_STATUS_RETURNED,
                'name' => '已退回',
                'desc' => '设备已退回给用户'
            ],
            self::DEVICE_STATUS_CANCELLED => [
                'status' => self::DEVICE_STATUS_CANCELLED,
                'name' => '已取消',
                'desc' => '设备回收已取消'
            ],
            self::DEVICE_STATUS_RETURNING => [
                'status' => self::DEVICE_STATUS_RETURNING,
                'name' => '退回中',
                'desc' => '设备正在退回过程中'
            ]
        ];
    }

    /**
     * 获取退货发货方式列表
     * @return array
     */
    public static function getShipmentModeList(): array
    {
        return [
            self::SHIPMENT_MODE_SYSTEM => [
                'value' => self::SHIPMENT_MODE_SYSTEM,
                'name' => '系统快递',
                'desc' => '通过已配置的快递 API 获取报价并创建运单',
                'require_express_no' => true,
                'need_quote' => true,
                'express_company' => '系统快递'
            ],
            self::SHIPMENT_MODE_MANUAL => [
                'value' => self::SHIPMENT_MODE_MANUAL,
                'name' => '手动快递',
                'desc' => '使用自己的快递，手动录入快递公司和单号',
                'require_express_no' => true,
                'need_quote' => false,
                'express_company' => ''
            ],
            self::SHIPMENT_MODE_LOGISTICS_CAR => [
                'value' => self::SHIPMENT_MODE_LOGISTICS_CAR,
                'name' => '物流车',
                'desc' => '线下物流车配送，无需快递单号',
                'require_express_no' => false,
                'need_quote' => false,
                'express_company' => '物流车'
            ],
            self::SHIPMENT_MODE_SELF_PICKUP => [
                'value' => self::SHIPMENT_MODE_SELF_PICKUP,
                'name' => '自取',
                'desc' => '客户到店自取，无需快递单号',
                'require_express_no' => false,
                'need_quote' => false,
                'express_company' => '自取'
            ]
        ];
    }

    /**
     * 获取订单状态信息
     * @param int $status
     * @return array
     */
    public static function getOrderStatus(int $status): array
    {
        $list = self::getOrderStatusList();
        return $list[$status] ?? [];
    }

    /**
     * 获取设备状态信息
     * @param int $status
     * @return array
     */
    public static function getDeviceStatus(int $status): array
    {
        $list = self::getDeviceStatusList();
        return $list[$status] ?? [];
    }

    /**
     * 获取退货发货方式信息
     * @param string $mode
     * @return array
     */
    public static function getShipmentMode(string $mode): array
    {
        $list = self::getShipmentModeList();
        return $list[$mode] ?? [];
    }

    /**
     * 获取有效的状态转换映射
     * @return array
     */
    public static function getValidStatusTransitions(): array
    {
        return [
            self::ORDER_STATUS_PENDING => [
                self::ORDER_STATUS_RETURNING,
                self::ORDER_STATUS_CANCELLED
            ],
            self::ORDER_STATUS_RETURNING => [
                self::ORDER_STATUS_COMPLETED,
                self::ORDER_STATUS_CANCELLED
            ],
            self::ORDER_STATUS_COMPLETED => [],
            self::ORDER_STATUS_CANCELLED => []
        ];
    }

    /**
     * 获取状态操作权限
     * @return array
     */
    public static function getStatusActionPermissions(): array
    {
        return [
            'DELETE' => [self::ORDER_STATUS_CANCELLED],
            'CANCEL' => [self::ORDER_STATUS_PENDING, self::ORDER_STATUS_RETURNING],
            'CONFIRM' => [self::ORDER_STATUS_PENDING],
            'COMPLETE' => [self::ORDER_STATUS_RETURNING]
        ];
    }
}
