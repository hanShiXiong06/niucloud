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

namespace addon\phone_shop\app\validate\phone_shop;
use core\base\BaseValidate;
/**
 * 二手机商户信息验证器
 * Class PhoneShop
 * @package addon\phone_shop\app\validate\phone_shop
 */
class PhoneShop extends BaseValidate
{

       protected $rule = [
            'id' => 'require',
            'name' => 'require',
            'client' => 'require'
        ];

       protected $message = [
            'id.require' => ['common_validate.require', ['id']],
            'name.require' => ['common_validate.require', ['name']],
            'client.require' => ['common_validate.require', ['client']]
        ];

       protected $scene = [
            "add" => ['name', 'client'],
            "edit" => ['name', 'client']
        ];

}
