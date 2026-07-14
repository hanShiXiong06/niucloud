<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use think\facade\Db;

/** phone_shop 跨插件能力探测，兼容站点与套餐两种安装记录。 */
final class PhoneShopBridge
{
    public static function available(int $siteId): bool
    {
        if ($siteId <= 0 || !class_exists('addon\\phone_shop\\app\\model\\goods\\GoodsSku')) return false;
        $site = Db::name('site')->where('site_id', $siteId)->field('group_id,app_type,app,addons')->find() ?: [];
        $keys = array_merge(self::keys($site['app'] ?? []), self::keys($site['addons'] ?? []));
        if (($site['app_type'] ?? '') === 'site' && (int)($site['group_id'] ?? 0) > 0) {
            $group = Db::name('site_group')->where('group_id', (int)$site['group_id'])->field('app,addon')->find() ?: [];
            $keys = array_merge($keys, self::keys($group['app'] ?? []), self::keys($group['addon'] ?? []));
        }
        return in_array('phone_shop', array_map('strval', $keys), true);
    }

    private static function keys(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
