<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\api\member;


use addon\home_service\app\dict\card\MemberCardDict;
use addon\home_service\app\dict\coupon\CouponMemberDict;
use addon\home_service\app\model\card\MemberCard;
use addon\home_service\app\model\coupon\CouponMember;
use core\base\BaseApiService;

/**
 * 会员服务层
 */
class MemberService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function MemberDiscountCount()
    {
        $card_count = (new MemberCard())->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['status', '=', MemberCardDict::WAIT_USE]])->count();
        $coupon_count = (new CouponMember())->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['status', '=', CouponMemberDict::WAIT_USE]])->count();

        return [
            'card_count' => $card_count,
            'coupon_count' => $coupon_count,
        ];
    }
}
