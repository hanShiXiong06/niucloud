<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device;

use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

/** 公共资料归属与双开关。业务站点身份不随资料来源切换。 */
final class DeviceCatalogPolicy
{
    public const PLATFORM_KEY = 'HSX_RECYCLE_PLATFORM_CATALOG';
    public const GRANT_KEY = 'HSX_RECYCLE_CATALOG_GRANT';
    public const CHOICE_KEY = 'HSX_RECYCLE_CATALOG_CHOICE';
    public const SEED_SITE_ID = 100005;

    public function platform(): array
    {
        return array_merge([
            'initialized' => false,
            'source_site_id' => self::SEED_SITE_ID,
            'contact_name' => '',
            'contact_mobile' => '',
            'contact_wechat' => '',
        ], $this->read(0, self::PLATFORM_KEY));
    }

    public function forSite(int $siteId): array
    {
        if ($siteId < 0) throw new CommonException('站点信息不正确');
        $platform = $this->platform();
        $grant = $siteId > 0 ? $this->read($siteId, self::GRANT_KEY) : [];
        $choice = $siteId > 0 ? $this->read($siteId, self::CHOICE_KEY) : [];
        $allowed = !empty($grant['allowed']);
        $enabled = !empty($choice['enabled']);
        $effective = $siteId > 0 && $allowed && $enabled && !empty($platform['initialized']);
        return [
            'site_id' => $siteId,
            'platform_allowed' => $allowed,
            'site_enabled' => $enabled,
            'initialized' => !empty($platform['initialized']),
            'effective' => $effective,
            'owner_site_id' => $effective ? 0 : $siteId,
            'source' => ($effective || $siteId === 0) ? 'platform' : 'local',
            'read_only' => $effective,
            'contact' => array_intersect_key($platform, array_flip(['contact_name', 'contact_mobile', 'contact_wechat'])),
        ];
    }

    public function ownerSiteId(int $siteId): int
    {
        return (int)$this->forSite($siteId)['owner_site_id'];
    }

    public function assertLocalWritable(int $siteId): void
    {
        if ($siteId > 0 && $this->forSite($siteId)['effective']) {
            throw new CommonException('当前使用平台型号及质检模板，缺少型号请联系平台管理员添加');
        }
    }

    private function read(int $siteId, string $key): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, $key);
        return is_array($value) ? $value : [];
    }
}
