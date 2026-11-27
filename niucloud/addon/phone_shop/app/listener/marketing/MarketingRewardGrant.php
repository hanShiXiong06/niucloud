<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\marketing;

use addon\phone_shop\app\dict\coupon\CouponDict;
use addon\phone_shop\app\model\coupon\Coupon;
use addon\phone_shop\app\model\coupon\CouponMember;
use addon\phone_shop\app\service\core\coupon\CoreCouponMemberService;

final class MarketingRewardGrant
{
    public function handle(array $payload): array
    {
        if (($payload['provider_key'] ?? '') !== 'phone_shop' || ($payload['reward_type'] ?? '') !== 'coupon') return ['handled' => false];
        $siteId = (int)($payload['site_id'] ?? 0);
        $memberId = (int)($payload['member_id'] ?? 0);
        $quantity = max(1, (int)($payload['reward_quantity'] ?? 1));
        $couponId = (int)($payload['reward_config']['option_id'] ?? 0);
        $coupon = Coupon::where([['site_id', '=', $siteId], ['id', '=', $couponId], ['status', '=', CouponDict::NORMAL]])->lock(true)->findOrEmpty();
        if ($coupon->isEmpty()) return ['handled' => true, 'success' => false, 'message' => '商城优惠券不存在或已停用'];
        $beforeId = (int)CouponMember::where([['site_id', '=', $siteId], ['member_id', '=', $memberId], ['coupon_id', '=', $couponId]])->max('id');
        $count = (new CoreCouponMemberService())->sendCoupon($siteId, [$memberId], $couponId, $quantity);
        if ((int)$count !== $quantity) return ['handled' => true, 'success' => false, 'message' => '优惠券未能完整发放，请检查领取资格和券状态'];
        $issued = CouponMember::where([
            ['site_id', '=', $siteId], ['member_id', '=', $memberId], ['coupon_id', '=', $couponId], ['id', '>', $beforeId],
        ])->field('id,expire_time')->order('id asc')->limit($quantity)->select()->toArray();
        if (count($issued) !== $quantity) return ['handled' => true, 'success' => false, 'message' => '优惠券发放结果无法核对'];
        $expire = $issued[0]['expire_time'] ?? 0;
        return [
            'handled' => true, 'success' => true, 'provider_no' => implode(',', array_column($issued, 'id')),
            'coupon_member_ids' => array_map('intval', array_column($issued, 'id')),
            'expire_at' => is_numeric($expire) ? (int)$expire : (int)strtotime((string)$expire), 'message' => '商城优惠券已到账',
        ];
    }
}
