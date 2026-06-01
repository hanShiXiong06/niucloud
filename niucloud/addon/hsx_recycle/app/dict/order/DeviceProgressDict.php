<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\order;

/**
 * 订单设备进度分组字典
 *
 * PC 端订单列表"设备进度"列和移动端订单概览共用此定义。
 * 后端按此分组统计各组设备数量，前端直接渲染。
 */
class DeviceProgressDict
{
    /**
     * 获取设备进度分组定义
     * @return array
     */
    public static function getGroups(): array
    {
        return [
            [
                'key' => 'total',
                'label' => '共',
                'unit' => '台',
                'color' => 'info',
                'statuses' => [],
                'type' => 'total',
            ],
            [
                'key' => 'pending',
                'label' => '待处理',
                'unit' => '台',
                'color' => 'warning',
                'type' => 'normal',
                'statuses' => [
                    RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                    RecycleOrderDict::DEVICE_STATUS_CHECKING,
                    RecycleOrderDict::DEVICE_STATUS_CHECKED,
                    RecycleOrderDict::DEVICE_STATUS_PRICED,
                    RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE,
                ],
                'description' => '待质检、质检中、已质检、已定价',
            ],
            [
                'key' => 'pending_confirm',
                'label' => '待确认',
                'unit' => '台',
                'color' => 'primary',
                'type' => 'normal',
                'statuses' => [
                    RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
                ],
                'description' => '已报价等待客户确认',
            ],
            [
                'key' => 'pending_pay',
                'label' => '待打款',
                'unit' => '台',
                'color' => 'success',
                'type' => 'normal',
                'statuses' => [
                    RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                ],
                'pay_status' => RecycleOrderDict::PAY_STATUS_UNPAID,
                'description' => '已确认回收但尚未打款',
            ],
            [
                'key' => 'paid',
                'label' => '已打款',
                'unit' => '台',
                'color' => 'success',
                'type' => 'normal',
                'statuses' => [
                    RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                ],
                'pay_status' => RecycleOrderDict::PAY_STATUS_PAID,
                'description' => '已完成打款',
            ],
            [
                'key' => 'abnormal',
                'label' => '异常',
                'unit' => '台',
                'color' => 'danger',
                'type' => 'abnormal',
                'statuses' => [
                    RecycleOrderDict::DEVICE_STATUS_RETURNED,
                    RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
                ],
                'description' => '退货或转代卖',
            ],
        ];
    }
}