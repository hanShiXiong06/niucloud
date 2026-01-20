<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\validate;

/**
 * 地址验证器
 * Class TkjhkdNotice
 * @package app\validate\tkjhkd_address
 */
class TkjhkdAddress extends \think\Validate
{

       protected $rule = [
            'site_id' => 'require',
            'name' => 'require',
            'mobile' => 'require',
            'latitude' => 'require',
            'longitude' => 'require',
            'city_id' => 'require',
            'address' => 'require',
            'full_address' => 'require',
        ];

       protected $message = [];

       protected $scene = [
            "add" => ['name','mobile','latitude','longitude','address','full_address'],
            "edit" => ['title','image','content','status','addon']
        ];

}