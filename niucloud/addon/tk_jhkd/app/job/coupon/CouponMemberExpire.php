<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\coupon;

use addon\tk_jhkd\app\dict\coupon\CouponMemberDict;
use addon\tk_jhkd\app\model\coupon\CouponMember;
use addon\tk_jhkd\app\service\core\CoreCouponMemberService;
use core\base\BaseJob;

/**
 * 订单自动关闭
 */
class CouponMemberExpire extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        try {
            $ids = (new CouponMember())->where([
                ['status', '=', CouponMemberDict::WAIT_USE],
                ['expire_time', '<=', time()]
            ])->column('id');
            if(!empty($ids)){
                //过期
                (new CoreCouponMemberService())->expire($ids);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
