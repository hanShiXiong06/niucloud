<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\service\admin\MemberLevelNoService;
use addon\phone_shop\app\support\TierPriceRule;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

final class CoreTierPricingService
{
    private const KEY = 'PHONE_SHOP_TIER_PRICING';

    public function policy(int $siteId): array
    {
        $raw = (array)(new CoreConfigService())->getConfigValue($siteId, self::KEY);
        $levels = array_values(array_filter(MemberLevelNoService::levelsWithNo($siteId), static fn($v) => (int)$v['level_no'] > 0 && (int)$v['status'] === 1));
        $defaultBase = 0;
        foreach ($levels as $level) {
            if ((int)$level['growth'] === max(array_column($levels, 'growth'))) $defaultBase = (int)$level['level_no'];
        }
        return array_merge(['enabled' => 0, 'base_level_no' => $defaultBase, 'rules' => []], $raw, ['levels' => $levels]);
    }

    public function save(int $siteId, array $data): void
    {
        $levels = $this->policy($siteId)['levels'];
        try { $config = TierPriceRule::normalize($data, $levels); }
        catch (\InvalidArgumentException $e) { throw new CommonException($e->getMessage()); }
        (new CoreConfigService())->setConfig($siteId, self::KEY, $config);
    }

    public function quote(int $siteId, $base, ?array $policy = null): array
    {
        $policy = $policy ?? $this->policy($siteId);
        try { return TierPriceRule::quote($base, $policy, $policy['levels']); }
        catch (\InvalidArgumentException $e) { throw new CommonException($e->getMessage()); }
    }

    public static function snapshot($value): array
    {
        return is_array($value) ? $value : (array)json_decode((string)$value, true);
    }

    public static function baseFromSku(array $sku, array $policy)
    {
        $snapshot = self::snapshot($sku['device_snapshot'] ?? '');
        if (isset($snapshot['_tier_pricing']['base_price'])) return $snapshot['_tier_pricing']['base_price'];
        $prices = self::snapshot($sku['member_price'] ?? '');
        // 没有明确基准的旧商品留空，不能把普通售价冒充最低会员价再加一次。
        return $prices['level_' . (int)($policy['base_level_no'] ?? 0)] ?? null;
    }
}
