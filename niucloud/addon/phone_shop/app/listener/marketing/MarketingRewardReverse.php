<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\marketing;

use addon\phone_shop\app\dict\coupon\CouponMemberDict;
use addon\phone_shop\app\model\coupon\CouponMember;
use addon\phone_shop\app\service\core\coupon\CoreCouponMemberService;

final class MarketingRewardReverse
{
    public function handle(array $payload): array
    {
        if (($payload['provider_key'] ?? '') !== 'phone_shop' || ($payload['reward_type'] ?? '') !== 'coupon') return ['handled' => false];
        $ids = array_values(array_filter(array_map('intval', (array)($payload['provider_result']['coupon_member_ids'] ?? []))));
        if ($ids === []) return ['handled' => true, 'success' => false, 'message' => '缺少优惠券发放凭据，需人工冲红'];
        $used = CouponMember::where([['site_id', '=', (int)$payload['site_id']], ['id', 'in', $ids], ['status', '<>', CouponMemberDict::WAIT_USE]])->count();
        if ($used > 0) return ['handled' => true, 'success' => false, 'message' => '奖励优惠券已使用或已失效，需人工处理'];
        (new CoreCouponMemberService())->invalid($ids);
        return ['handled' => true, 'success' => true, 'message' => '奖励优惠券已冲红'];
    }
}
