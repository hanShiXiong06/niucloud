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

namespace addon\hsx_phone_query\app\validate\hsx_phone_query_category;
use core\base\BaseValidate;
/**
 * 分类验证器
 * Class HsxPhoneQueryCategory
 * @package addon\hsx_phone_query\app\validate\hsx_phone_query_category
 */
class HsxPhoneQueryCategory extends BaseValidate
{

       protected $rule = [
            'channel_key' => 'require',
            'service_code' => 'require',
            'type_id' => 'require',
            'name' => 'require',
            'price' => 'require'
        ];

       protected $message = [
            'channel_key.require' => '请选择查询渠道',
            'service_code.require' => '请填写服务编码',
            'type_id.require' => ['common_validate.require', ['type_id']],
            'name.require' => ['common_validate.require', ['name']],
            'price.require' => ['common_validate.require', ['price']]
        ];

       protected $scene = [
            "add" => ['channel_key', 'service_code', 'type_id', 'name', 'price'],
            "edit" => ['channel_key', 'service_code', 'type_id', 'name', 'price']
        ];

}
