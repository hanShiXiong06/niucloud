<?php
declare(strict_types=1);
namespace addon\phone_shop\app\support;

final class GoodsRecentActivity
{
    public const RECENT_SECONDS = 86400;

    /** 二手机业务约定：最新上架采用既有创建时间，重新上架不重新计时。 */
    public static function recentWhere(int $now): array
    {
        return [['goods.create_time', '>', max(0, $now - self::RECENT_SECONDS)], ['goods.create_time', '<=', $now]];
    }
}
