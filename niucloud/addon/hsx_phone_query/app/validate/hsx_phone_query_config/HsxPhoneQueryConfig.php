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

namespace addon\hsx_phone_query\app\validate\hsx_phone_query_config;
use core\base\BaseValidate;
/**
 * 配置信息验证器
 * Class HsxPhoneQueryConfig
 * @package addon\hsx_phone_query\app\validate\hsx_phone_query_config
 */
class HsxPhoneQueryConfig extends BaseValidate
{

       protected $rule = [
            'appid' => 'require',
            'Secret' => 'require'
        ];

       protected $message = [
            'appid.require' => ['common_validate.require', ['appid']],
            'Secret.require' => ['common_validate.require', ['Secret']]
        ];

       protected $scene = [
            "add" => ['appid', 'Secret'],
            "edit" => ['appid', 'Secret']
        ];

}
