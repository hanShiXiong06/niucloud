<?php

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\service\admin\MemberLevelNoService;

/**
 * 商城会员价统一解析器。
 *
 * member_price 的 level_N 中，N 的唯一口径是“本站等级序号 level_no”。
 * 只有旧站点尚未生成 level_no 时才兼容全局 level_id，避免 level_1 同时被
 * 解释成“本站第一个等级”和“全局主键为 1 的等级”，造成普通会员误用同行价。
 */
class CoreMemberPriceService
{
    public static function calculate(array $memberInfo, string $memberDiscount, $memberPrice, $price): string
    {
        $originalPrice = number_format(max(0, (float)$price), 2, '.', '');
        $levelId = (int)($memberInfo['member_level'] ?? 0);
        if ($memberDiscount === '' || $levelId <= 0) {
            return $originalPrice;
        }

        if ($memberDiscount === 'discount') {
            $benefit = $memberInfo['memberLevelData']['level_benefits']['discount'] ?? [];
            if (!empty($benefit['is_use'])) {
                return CoreMemberDiscountPriceService::calculate($originalPrice, $benefit);
            }
            return $originalPrice;
        }

        if ($memberDiscount === 'fixed_price') {
            $fixedPrice = self::pickLevelPrice($memberPrice, $memberInfo);
            if ($fixedPrice !== null && (float)$fixedPrice > 0) {
                return number_format((float)$fixedPrice, 2, '.', '');
            }
        }

        return $originalPrice;
    }

    public static function pickLevelPrice($memberPrice, array $memberInfo)
    {
        $prices = self::decode($memberPrice);
        $levelId = (int)($memberInfo['member_level'] ?? 0);
        if (empty($prices) || $levelId <= 0) {
            return null;
        }

        $levelData = $memberInfo['memberLevelData'] ?? [];
        $siteId = (int)($memberInfo['site_id'] ?? ($levelData['site_id'] ?? 0));
        $levelNo = (int)($levelData['level_no'] ?? 0);
        if ($levelNo <= 0 && $siteId > 0) {
            $levelNo = MemberLevelNoService::idToNo($siteId, $levelId);
        }

        // 只要本站序号可解析，就绝不能再回退 level_id，否则键值会串等级。
        if ($levelNo > 0) {
            $key = 'level_' . $levelNo;
            return isset($prices[$key]) && $prices[$key] !== '' ? $prices[$key] : null;
        }

        // 仅兼容还没有 level_no 能力的历史站点。
        $legacyKey = 'level_' . $levelId;
        return isset($prices[$legacyKey]) && $prices[$legacyKey] !== '' ? $prices[$legacyKey] : null;
    }

    private static function decode($memberPrice): array
    {
        if (is_array($memberPrice)) {
            return $memberPrice;
        }
        if (!is_string($memberPrice) || trim($memberPrice) === '') {
            return [];
        }
        $decoded = json_decode($memberPrice, true);
        return is_array($decoded) ? $decoded : [];
    }
}
