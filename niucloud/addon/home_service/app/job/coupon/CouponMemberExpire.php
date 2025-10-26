<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\home_service\app\job\coupon;

use addon\home_service\app\dict\coupon\CouponMemberDict;
use addon\home_service\app\model\coupon\CouponMember;
use addon\home_service\app\service\core\coupon\CoreCouponMemberService;
use core\base\BaseJob;

/**
 * 优惠券到期
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
