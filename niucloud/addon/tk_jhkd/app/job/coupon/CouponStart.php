<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\coupon;

use addon\tk_jhkd\app\dict\coupon\CouponDict;
use addon\tk_jhkd\app\model\coupon\Coupon;
use addon\tk_jhkd\app\service\core\CoreCouponService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 优惠券限时自动开启
 */
class CouponStart extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        Log::write('聚合快速优惠券限时自动开启');
        try {
            $ids = (new Coupon())->where([
                ['status', '=', CouponDict::WAIT_START],
                ['start_time', '>', 0],
                ['start_time', '<=', time()],
                ['end_time', '>', time()]
            ])->column('id');
            if(!empty($ids)){
                //修改状态
                (new CoreCouponService())->couponNormal($ids);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
