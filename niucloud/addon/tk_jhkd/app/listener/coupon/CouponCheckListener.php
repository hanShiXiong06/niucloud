<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\listener\coupon;

use addon\tk_jhkd\app\dict\coupon\CouponDict;
use addon\tk_jhkd\app\model\coupon\Coupon;

/**
 * 优惠券状态检测
 */
class CouponCheckListener
{
    public function handle(array $data)
    {
        $old_coupon_list = $data['coupon_list'];

        $coupon_id = [];
        $coupon_list = [];
        $coupon_data = (new Coupon())->field('id,status,remain_count')
            ->where([ [ 'site_id', '=', $data['site_id'] ], [ 'id', 'in', $data['coupon_id'] ] ])
            ->select()->toArray();
        foreach ($coupon_data as $coupon) {
            //检测优惠券状态和剩余数量
            if ($coupon['status'] == CouponDict::NORMAL && ($coupon[ 'remain_count' ] == '-1' || $coupon[ 'remain_count' ] >= $old_coupon_list[ 'id_' . $coupon['id']])) {
                $coupon_id[] = $coupon['id'];
                $coupon_list[] = 'id_' . $coupon['id'];
            }
        }
        return [
            'coupon_id' => $coupon_id,
            'coupon_list' => $coupon_list,
        ];
    }
}
