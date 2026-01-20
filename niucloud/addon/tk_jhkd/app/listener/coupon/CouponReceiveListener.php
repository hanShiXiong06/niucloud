<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\listener\coupon;

/**
 * 优惠券领取分类信息
 * Class CouponReceiveListener
 */
class CouponReceiveListener
{
    public function handle(array $params)
    {
        return [
            [
                "name" => "send",
                "title" => get_lang('dict_tk_jhkd_member_coupon.send'),
            ],
            [
                "name" => "receive",
                "title" => get_lang('dict_tk_jhkd_member_coupon.receive'),
            ],
        ];
    }
}
