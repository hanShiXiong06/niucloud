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

namespace addon\phone_shop\app\dict\local_delivery;

/**
 * 配送订单操作前置状态枚举类
 * Class LocalDeliveryDict
 * @package app\dict\sys
 */
class LocalDeliveryPreStatusDict
{

    public static function getStatus(string $service = '')
    {
        $list = [];

        $extend = array_filter(event('DeliveryOrderPreStatus', ['service' => $service]));
        return array_merge($list, ...$extend);
    }

}