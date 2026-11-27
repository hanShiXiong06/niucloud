<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\dict;

final class MemberCardDict
{
    public const PRODUCT_DRAFT = 'draft';
    public const PRODUCT_ENABLED = 'enabled';
    public const PRODUCT_DISABLED = 'disabled';
    public const PRODUCT_DELETED = 'deleted';

    public const ORDER_PROCESSING = 'processing';
    public const ORDER_ACTIVE = 'active';
    public const ORDER_CANCELLED = 'cancelled';
    public const ORDER_REFUND_PENDING = 'refund_pending';
    public const ORDER_REFUNDED = 'refunded';
    public const ORDER_FAILED = 'failed';

    public const CARD_PENDING = 'pending';
    public const CARD_ACTIVE = 'active';
    public const CARD_EXHAUSTED = 'exhausted';
    public const CARD_EXPIRED = 'expired';
    public const CARD_FROZEN = 'frozen';
    public const CARD_REFUND_PENDING = 'refund_pending';
    public const CARD_REFUNDED = 'refunded';
    public const CARD_CANCELLED = 'cancelled';

    public static function lists(): array
    {
        return [
            'product_status' => [
                ['value' => self::PRODUCT_DRAFT, 'label' => '草稿', 'type' => 'info'],
                ['value' => self::PRODUCT_ENABLED, 'label' => '启用', 'type' => 'success'],
                ['value' => self::PRODUCT_DISABLED, 'label' => '停用', 'type' => 'warning'],
            ],
            'order_status' => [
                ['value' => self::ORDER_PROCESSING, 'label' => '处理中', 'type' => 'primary'],
                ['value' => self::ORDER_ACTIVE, 'label' => '已开卡', 'type' => 'success'],
                ['value' => self::ORDER_CANCELLED, 'label' => '已取消', 'type' => 'info'],
                ['value' => self::ORDER_REFUND_PENDING, 'label' => '退款中', 'type' => 'warning'],
                ['value' => self::ORDER_REFUNDED, 'label' => '已退款', 'type' => 'info'],
                ['value' => self::ORDER_FAILED, 'label' => '处理失败', 'type' => 'danger'],
            ],
            'card_status' => [
                ['value' => self::CARD_PENDING, 'label' => '待激活', 'type' => 'info'],
                ['value' => self::CARD_ACTIVE, 'label' => '可使用', 'type' => 'success'],
                ['value' => self::CARD_EXHAUSTED, 'label' => '已用完', 'type' => 'info'],
                ['value' => self::CARD_EXPIRED, 'label' => '已过期', 'type' => 'info'],
                ['value' => self::CARD_FROZEN, 'label' => '已冻结', 'type' => 'warning'],
                ['value' => self::CARD_REFUND_PENDING, 'label' => '退款中', 'type' => 'warning'],
                ['value' => self::CARD_REFUNDED, 'label' => '已退款', 'type' => 'info'],
                ['value' => self::CARD_CANCELLED, 'label' => '已取消', 'type' => 'info'],
            ],
            'settlement_mode' => [
                ['value' => 'immediate', 'label' => '现场收款'],
                ['value' => 'receivable', 'label' => '财务挂账'],
            ],
            'effective_mode' => [
                ['value' => 'immediate', 'label' => '开卡后立即生效'],
                ['value' => 'first_use', 'label' => '首次核销时生效'],
                ['value' => 'fixed', 'label' => '指定日期生效'],
            ],
            'validity_mode' => [
                ['value' => 'permanent', 'label' => '永久有效'],
                ['value' => 'duration', 'label' => '固定时长'],
                ['value' => 'fixed', 'label' => '固定日期'],
            ],
        ];
    }
}
