<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device;

use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

/** 只切换基础资料来源，绝不改变业务订单的站点身份。 */
final class DeviceCatalogPolicy
{
    public const PLATFORM_KEY = 'HSX_RECYCLE_PLATFORM_CATALOG';
    public const GRANT_KEY = 'HSX_RECYCLE_CATALOG_GRANT';
    public const CHOICE_KEY = 'HSX_RECYCLE_CATALOG_CHOICE';
    public const SEED_SITE_ID = 100005;
    public const READ_ONLY_MESSAGE = '当前使用公共资料库，缺少型号或模板请联系站点管理员，由管理员联系平台添加；本站不能修改公共资料。';

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
        $choice = $siteId > 0 ? $this->read($siteId, self::CHOICE_KEY) : [];
        $grant = $siteId > 0 ? $this->read($siteId, self::GRANT_KEY) : [];
        $canManage = !empty($grant['allowed']);
        $enabled = !empty($choice['enabled']);
        $effective = $siteId > 0 && $enabled && !empty($platform['initialized']);
        return [
            'site_id' => $siteId,
            'platform_allowed' => !empty($platform['initialized']),
            'site_enabled' => $enabled,
            'initialized' => !empty($platform['initialized']),
            'effective' => $effective,
            'owner_site_id' => $effective ? 0 : $siteId,
            'source' => ($effective || $siteId === 0) ? 'platform' : 'local',
            'can_manage_public' => $canManage,
            'read_only' => $effective && !$canManage,
            'message' => $effective ? ($canManage ? '本站获准维护公共资料库，修改将影响所有使用公共库的站点，请谨慎操作。' : self::READ_ONLY_MESSAGE) : '当前使用本站资料库，由本站自行维护型号与质检模板。',
            'contact' => array_intersect_key($platform, array_flip(['contact_name', 'contact_mobile', 'contact_wechat'])),
        ];
    }

    public function ownerSiteId(int $siteId): int
    {
        return (int)$this->forSite($siteId)['owner_site_id'];
    }

    public function assertLocalWritable(int $siteId): void
    {
        if ($siteId > 0 && $this->forSite($siteId)['read_only']) {
            throw new CommonException(self::READ_ONLY_MESSAGE);
        }
    }

    /** 仅用于按明确 ID 回看既有引用，列表与新增选择不能合并两个库。 */
    public static function referenceSiteIds(int $siteId): array
    {
        if ($siteId < 0) throw new CommonException('站点信息不正确');
        return $siteId === 0 ? [0] : [$siteId, 0];
    }

    private function read(int $siteId, string $key): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, $key);
        return is_array($value) ? $value : [];
    }
}
