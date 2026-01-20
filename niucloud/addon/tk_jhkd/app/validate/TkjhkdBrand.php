<?php

namespace addon\tk_jhkd\app\validate;

/**
 * 聚合快递品牌列表验证器
 * Class TkjhkdBrand
 * @package app\validate\tkjhkd_brand
 */
class TkjhkdBrand extends \think\Validate
{

       protected $rule = [
            'site_id' => 'require',
            'delivery_type' => 'require',
            'name' => 'require',
            'logo' => 'require',
            'status' => 'require',
            'create_time' => 'require',
            'update_time' => 'require',
            'addon' => 'require',
        ];

       protected $message = [];

       protected $scene = [
            "add" => ['site_id','delivery_type','logo','status','addon'],
            "edit" => ['site_id','delivery_type','name','logo','status','addon']
        ];

}