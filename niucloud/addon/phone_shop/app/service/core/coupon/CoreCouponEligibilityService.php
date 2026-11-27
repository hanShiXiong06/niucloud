<?php
// +----------------------------------------------------------------------
// | 二手商城优惠券会员资格校验
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\coupon;

use addon\phone_shop\app\model\coupon\Coupon;
use app\model\member\Member;
use core\base\BaseCoreService;
use core\exception\CommonException;

/**
 * 优惠券会员资格的唯一判断入口。
 *
 * 普通用户口径：member_level = 0。
 * 只要拥有任意会员等级（VIP、同行等），均视为非普通用户。
 */
class CoreCouponEligibilityService extends BaseCoreService
{
    /**
     * 当前用户是否拥有会员等级。
     */
    public function isVipMember(int $siteId, int $memberId): bool
    {
        if ($siteId <= 0 || $memberId <= 0) return false;

        return (new Member())->where([
            ['site_id', '=', $siteId],
            ['member_id', '=', $memberId],
            ['member_level', '>', 0],
        ])->count() > 0;
    }

    /**
     * 查询当前会员不可使用的优惠券模板 ID。
     */
    public function getUnavailableCouponIds(int $siteId, int $memberId): array
    {
        if (!$this->isVipMember($siteId, $memberId)) return [];

        return array_map('intval', (new Coupon())->where([
            ['site_id', '=', $siteId],
            ['is_non_vip_only', '=', 1],
        ])->column('id'));
    }

    /**
     * 判断优惠券是否允许当前会员领取或使用。
     */
    public function isEligible(int $siteId, int $memberId, array $coupon): bool
    {
        if ((int)($coupon['is_non_vip_only'] ?? 0) !== 1) return true;
        return !$this->isVipMember($siteId, $memberId);
    }

    /**
     * 不符合资格时统一抛出明确业务提示。
     */
    public function assertEligible(int $siteId, int $memberId, array $coupon): void
    {
        if (!$this->isEligible($siteId, $memberId, $coupon)) {
            throw new CommonException('COUPON_ONLY_NON_VIP');
        }
    }

    /**
     * 发券场景过滤掉不符合资格的会员，避免绕过前端领取限制。
     */
    public function filterEligibleMemberIds(int $siteId, array $memberIds, array $coupon): array
    {
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        if (!$memberIds || (int)($coupon['is_non_vip_only'] ?? 0) !== 1) return $memberIds;

        $vipIds = (new Member())->where([
            ['site_id', '=', $siteId],
            ['member_id', 'in', $memberIds],
            ['member_level', '>', 0],
        ])->column('member_id');

        return array_values(array_diff($memberIds, array_map('intval', $vipIds)));
    }
}
