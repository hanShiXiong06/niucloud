<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\order;

/**
 * 代卖订单字典
 */
class RecycleConsignmentDict
{
    public const STATUS_PENDING = 0;
    public const STATUS_SELLING = 1;
    public const STATUS_SOLD = 2;
    public const STATUS_PENDING_SETTLEMENT = 3;
    public const STATUS_SETTLED = 4;
    public const STATUS_CANCELLED = 5;
    public const STATUS_RETURNED = 6;

    public const PAY_STATUS_UNPAID = 0;
    public const PAY_STATUS_PAID = 1;

    public static function getStatus($status = ''): array|string
    {
        $data = [
            self::STATUS_PENDING => '待上架',
            self::STATUS_SELLING => '代卖中',
            self::STATUS_SOLD => '已售出',
            self::STATUS_PENDING_SETTLEMENT => '待结算',
            self::STATUS_SETTLED => '已结算',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_RETURNED => '已退回',
        ];

        return $status === '' ? $data : ($data[(int)$status] ?? '');
    }

    public static function getPayStatus($status = ''): array|string
    {
        $data = [
            self::PAY_STATUS_UNPAID => '未结算',
            self::PAY_STATUS_PAID => '已结算',
        ];

        return $status === '' ? $data : ($data[(int)$status] ?? '');
    }

    public static function getStatusOptions(): array
    {
        $options = [];
        foreach (self::getStatus() as $value => $label) {
            $options[] = [
                'value' => (int)$value,
                'label' => $label,
            ];
        }
        return $options;
    }

    public static function getActionName(string $action = ''): array|string
    {
        $data = [
            'create' => '转入代卖',
            'listing' => '设置挂牌价',
            'sold' => '登记成交',
            'settle' => '结算客户',
            'cancel' => '取消代卖',
            'return' => '退回客户',
            'notify' => '推送通知',
        ];

        return $action === '' ? $data : ($data[$action] ?? $action);
    }
}
