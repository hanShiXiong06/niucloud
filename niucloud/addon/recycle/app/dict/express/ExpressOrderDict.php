<?php
declare(strict_types=1);

namespace addon\recycle\app\dict\express;

/**
 * 快递订单相关枚举类
 * Class ExpressOrderDict
 * @package addon\recycle\app\dict\express
 */
class ExpressOrderDict
{
    // 订单状态
    const STATUS_PENDING = 'pending';           // 待揽收
    const STATUS_PICKED = 'picked';             // 已揽收
    const STATUS_IN_TRANSIT = 'in_transit';     // 运输中
    const STATUS_DELIVERED = 'delivered';       // 已签收
    const STATUS_CANCELLED = 'cancelled';       // 已关闭
    const STATUS_EXCEPTION = 'exception';       // 异常

    // 支付状态
    const PAYMENT_STATUS_UNPAID = 0;            // 未支付
    const PAYMENT_STATUS_PAID = 1;              // 已支付
    const PAYMENT_STATUS_PARTIAL = 2;           // 部分支付

    // 订单状态文本映射
    const STATUS_TEXT = [
        self::STATUS_PENDING => '待揽收',
        self::STATUS_PICKED => '已揽收',
        self::STATUS_IN_TRANSIT => '运输中',
        self::STATUS_DELIVERED => '已签收',
        self::STATUS_CANCELLED => '已关闭',
        self::STATUS_EXCEPTION => '异常',
    ];

    // 支付状态文本映射
    const PAYMENT_STATUS_TEXT = [
        self::PAYMENT_STATUS_UNPAID => '未支付',
        self::PAYMENT_STATUS_PAID => '已支付',
        self::PAYMENT_STATUS_PARTIAL => '部分支付',
    ];

    /**
     * 获取订单状态文本
     * @param string $status
     * @return string
     */
    public static function getStatusText(string $status): string
    {
        return self::STATUS_TEXT[$status] ?? '未知状态';
    }

    /**
     * 获取支付状态文本
     * @param int $status
     * @return string
     */
    public static function getPaymentStatusText(int $status): string
    {
        return self::PAYMENT_STATUS_TEXT[$status] ?? '未知状态';
    }

    /**
     * 获取所有订单状态
     * @return array
     */
    public static function getAllStatus(): array
    {
        return self::STATUS_TEXT;
    }

    /**
     * 获取所有支付状态
     * @return array
     */
    public static function getAllPaymentStatus(): array
    {
        return self::PAYMENT_STATUS_TEXT;
    }

    /**
     * 检查订单状态是否有效
     * @param string $status
     * @return bool
     */
    public static function isValidStatus(string $status): bool
    {
        return isset(self::STATUS_TEXT[$status]);
    }

    /**
     * 检查支付状态是否有效
     * @param int $status
     * @return bool
     */
    public static function isValidPaymentStatus(int $status): bool
    {
        return isset(self::PAYMENT_STATUS_TEXT[$status]);
    }
}

