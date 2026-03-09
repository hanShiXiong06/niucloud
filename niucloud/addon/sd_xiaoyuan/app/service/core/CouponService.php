<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Coupon;
use addon\sd_xiaoyuan\app\model\CouponRecord;
use core\base\BaseApiService;

class CouponService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Coupon();
    }

    public function getList()
    {
        return $this->model->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['end_time', '>', time()]
        ])->select()->toArray();
    }

    public function receive($couponId)
    {
        $coupon = $this->model->where([
            ['id', '=', $couponId],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();

        if ($coupon->isEmpty()) {
            return ['code' => -1, 'msg' => '优惠券不存在'];
        }

        if ($coupon['received_count'] >= $coupon['total_count']) {
            return ['code' => -1, 'msg' => '优惠券已领完'];
        }

        $recordModel = new CouponRecord();
        $receivedCount = $recordModel->where([
            ['coupon_id', '=', $couponId],
            ['member_id', '=', $this->member_id]
        ])->count();

        if ($receivedCount >= $coupon['limit_per_user']) {
            return ['code' => -1, 'msg' => '您已领取过该优惠券'];
        }

        $expireTime = time() + ($coupon['valid_days'] * 86400);

        $recordModel->create([
            'site_id' => $this->site_id,
            'coupon_id' => $couponId,
            'member_id' => $this->member_id,
            'status' => 0,
            'expire_time' => $expireTime,
            'create_time' => time()
        ]);

        $this->model->where('id', $couponId)->inc('received_count')->update();

        return ['code' => 0, 'msg' => '领取成功'];
    }

    public function getMyCoupons($status = 0)
    {
        $recordModel = new CouponRecord();
        return $recordModel->alias('r')
            ->leftJoin('xiaoyuan_coupon c', 'r.coupon_id = c.id')
            ->where([
                ['r.member_id', '=', $this->member_id],
                ['r.site_id', '=', $this->site_id],
                ['r.status', '=', $status]
            ])
            ->field('r.*, c.name, c.type, c.discount_value, c.min_amount')
            ->select()
            ->toArray();
    }
}
