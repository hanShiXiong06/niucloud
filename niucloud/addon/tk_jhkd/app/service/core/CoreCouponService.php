<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\coupon\CouponDict;
use addon\tk_jhkd\app\model\coupon\Coupon;
use core\base\BaseCoreService;

/**
 * 优惠券服务层
 */
class CoreCouponService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Coupon();
    }

    /**
     * 优惠券状态开启
     * @param $ids
     * @return true
     */
    public function couponNormal($ids)
    {
        $where = [
            [ 'id', 'in', $ids ],
            [ 'status', '=', CouponDict::WAIT_START ]
        ];
        $data = [
            'status' => CouponDict::NORMAL
        ];
        $this->model->where($where)->update($data);
        return true;
    }

    /**
     * 优惠券状态过期
     * @param $ids
     * @return true
     */
    public function couponExpire($ids)
    {
        $where = [
            [ 'id', 'in', $ids ],
            [ 'status', '=', CouponDict::NORMAL ]
        ];
        $data = [
            'status' => CouponDict::EXPIRE
        ];
        $this->model->where($where)->update($data);
        return true;
    }

}
