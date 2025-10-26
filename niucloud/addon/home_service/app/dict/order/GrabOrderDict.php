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

namespace addon\home_service\app\dict\order;

/**
 * 抢单订单相关字典类
 * Class EvaluateDict
 */
class GrabOrderDict
{
    /**
     * 评价状态
     * @param $type
     * @return array|mixed|string
     */
    public static function getDistance($type = '')
    {
        $data = [
            0 => "不限", // 无需审核
            5 => "5km",
            10 => "10km",
            15 => "15km",
        ];
        return $data;
    }

}
