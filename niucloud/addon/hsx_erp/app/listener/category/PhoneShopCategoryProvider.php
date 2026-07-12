<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\category;

use think\facade\Db;

/** 商城分类提供器兼容层；商城正式提供同名 Hook 后可直接移出 ERP。 */
final class PhoneShopCategoryProvider
{
    public function handle(array $params = []): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        if ($siteId <= 0 || !$this->available($siteId)) return [];
        return ['providers' => [[
            'key' => 'phone_shop', 'name' => '手机商城', 'enabled' => 1,
            'supports_pull' => 1, 'supports_push' => 1,
        ]]];
    }

    private function available(int $siteId): bool
    {
        if (!class_exists('addon\\phone_shop\\app\\model\\goods\\Category')) return false;
        $site = Db::name('site')->where('site_id', $siteId)->field('group_id,app_type,app,addons')->find() ?: [];
        $keys = array_merge($this->keys($site['app'] ?? []), $this->keys($site['addons'] ?? []));
        if (($site['app_type'] ?? '') === 'site' && (int)($site['group_id'] ?? 0) > 0) {
            $group = Db::name('site_group')->where('group_id', (int)$site['group_id'])->field('app,addon')->find() ?: [];
            $keys = array_merge($keys, $this->keys($group['app'] ?? []), $this->keys($group['addon'] ?? []));
        }
        return in_array('phone_shop', array_map('strval', $keys), true);
    }

    private function keys(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
