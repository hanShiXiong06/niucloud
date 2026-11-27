<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\dict;

final class ProjectCenterDict
{
    public const PROJECT_DRAFT = 0;
    public const PROJECT_ENABLED = 1;
    public const PROJECT_DISABLED = 2;

    public const APPLICATION_SUBMITTED = 'submitted';
    public const APPLICATION_REVIEWING = 'reviewing';
    public const APPLICATION_REJECTED = 'rejected';
    public const APPLICATION_APPROVED = 'approved';
    public const APPLICATION_REFUND_PENDING = 'refund_pending';
    public const APPLICATION_REFUNDED = 'refunded';
    public const APPLICATION_ABANDONED = 'abandoned';

    public static function projectStatuses(): array
    {
        return [0 => '草稿', 1 => '启用', 2 => '停用'];
    }

    public static function applicationStatuses(): array
    {
        return [
            self::APPLICATION_SUBMITTED => '待审核',
            self::APPLICATION_REVIEWING => '审核中',
            self::APPLICATION_REJECTED => '待修改',
            self::APPLICATION_APPROVED => '已通过',
            self::APPLICATION_REFUND_PENDING => '待退款',
            self::APPLICATION_REFUNDED => '已退款关闭',
            self::APPLICATION_ABANDONED => '已放弃',
        ];
    }

    public static function groupStatuses(): array
    {
        return [
            'reserved' => '待建群', 'created' => '已建群', 'active' => '进行中',
            'completed' => '已完成', 'create_failed' => '企微建群未完成',
            'refund_pending' => '待退款', 'refunded' => '已退款关闭',
            'abandoned' => '已放弃', 'dissolved' => '已解散',
        ];
    }

    public static function refundStatuses(): array
    {
        return ['pending' => '待退款', 'refunded' => '已退款', 'cancelled' => '已取消退款'];
    }
}
