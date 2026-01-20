<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\dict\coupon;

class CouponDict
{

    const ALL = 1;
    const CATEGORY = 2;
    const GOODS = 3;

    const USER = 1;
    const GRANT = 2;

    /**
     * 优惠券类型
     * @param $status
     * @return array|mixed|string
     */
    public static function getType($type = '')
    {
        $list = [
            self::ALL => get_lang('dict_tk_jhkd_coupon.all'),
            //self::CATEGORY => get_lang('dict_tk_jhkd_coupon.category'),
        ];
        if ($type == '') return $list;
        return $list[ $type ] ?? '';
    }

    /**
     * 领取优惠券类型
     * @param $status
     * @return array|mixed|string
     */
    public static function getReceiveType($type = '')
    {
        $list = [
            self::USER => get_lang('dict_tk_jhkd_coupon.user'),
            self::GRANT => get_lang('dict_tk_jhkd_coupon.grant'),
        ];
        if ($type == '') return $list;
        return $list[ $type ] ?? '';
    }


    //未开始
    const WAIT_START = 0;
    //进行中
    const NORMAL = 1;
    //已过期
    const EXPIRE = 2;
    //已失效
    const INVALID = 3;

    /**
     * 优惠券活动状态
     * @param $status
     * @return array|mixed|string
     */
    public static function getStatus($status = '')
    {
        $list = [
            self::WAIT_START => get_lang('dict_tk_jhkd_coupon.wait_start'),
            self::NORMAL => get_lang('dict_tk_jhkd_coupon.normal'),
            self::EXPIRE => get_lang('dict_tk_jhkd_coupon.expire'),
            self::INVALID => get_lang('dict_tk_jhkd_coupon.invalid'),
        ];
        if ($status == '') return $list;
        return $list[ $status ] ?? '';
    }
}
