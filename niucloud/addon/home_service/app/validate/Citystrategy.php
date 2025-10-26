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

namespace addon\home_service\app\validate;

use core\base\BaseValidate;

/**
 * 城市策略验证器
 */
class Citystrategy extends BaseValidate
{
    protected $rule = [
        'province_id' => 'require|gt:0',
        'city_id' => 'require|gt:0',
        'way' => 'require|in:+,*',
        'value' => 'require|gt:0',
    ];

    protected $message = [
        'province_id.require' => '省份ID不能为空',
        'province_id.gt' => '省份ID必须大于0',
        'city_id.require' => '城市ID不能为空',
        'city_id.gt' => '城市ID必须大于0',
        'way.require' => '计算方式不能为空',
        'way.in' => '计算方式只能是+或*（乘）',
        'value.require' => '数值不能为空',
        'value.gt' => '数值必须大于0',
    ];

    protected $scene = [
        "add" => ['province_id', 'city_id', 'way', 'value'],
        "edit" => ['way', 'value'],
    ];


}