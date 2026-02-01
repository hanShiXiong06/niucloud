<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\fast_pay\app\validate\businessactive;
use core\base\BaseValidate;
/**
 * 商户活动验证器
 * Class BusinessActive
 * @package addon\fast_pay\app\validate\businessactive
 */
class BusinessActive extends BaseValidate
{

       protected $rule = [
            'name' => 'require',
            'desc' => 'require',
            'gift' => 'require',
            'image' => 'require'
        ];

       protected $message = [
            'name.require' => ['common_validate.require', ['name']],
            'desc.require' => ['common_validate.require', ['desc']],
            'gift.require' => ['common_validate.require', ['gift']],
            'image.require' => ['common_validate.require', ['image']]
        ];

       protected $scene = [
            "add" => ['business_id', 'name', 'desc', 'gift', 'image', 'contect'],
            "edit" => ['business_id', 'name', 'desc', 'gift', 'image', 'contect']
        ];

}
