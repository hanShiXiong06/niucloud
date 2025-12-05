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

namespace addon\phone_shop\app\validate\phone_shop_;
use core\base\BaseValidate;
/**
 * 商户信息验证器
 * Class PhoneShop
 * @package addon\phone_shop\app\validate\phone_shop_
 */
class PhoneShop extends BaseValidate
{

       protected $rule = [
            'name' => 'require',
            'client' => 'require'
        ];

       protected $message = [
            'name.require' => ['common_validate.require', ['name']],
            'client.require' => ['common_validate.require', ['client']]
        ];

       protected $scene = [
            "add" => ['name', 'client'],
            "edit" => ['name', 'client']
        ];

}
