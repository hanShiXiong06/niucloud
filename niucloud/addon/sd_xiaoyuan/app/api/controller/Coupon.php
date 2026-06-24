<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\CouponService;
use core\base\BaseApiController;

class Coupon extends BaseApiController
{
    public function lists()
    {
        $service = new CouponService();
        $list = $service->getList();
        return success($list);
    }

    public function receive()
    {
        $couponId = $this->request->param('id', 0);
        
        $service = new CouponService();
        $result = $service->receive($couponId);
        
        if ($result['code'] != 0) {
            return fail($result['msg']);
        }
        
        return success($result['msg']);
    }

    public function myCoupons()
    {
        $status = $this->request->param('status', 0);
        
        $service = new CouponService();
        $list = $service->getMyCoupons($status);
        return success($list);
    }
}
