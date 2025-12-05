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

namespace addon\phone_shop\app\validate\site;
use core\base\BaseValidate;
/**
 * 站点(二手)管理验证器
 * Class Site
 * @package addon\phone_shop\app\validate\site
 */
class Site extends BaseValidate
{

       protected $rule = [
            'site_name' => 'require',
            'client' => 'require|number',
            'category_status' => 'require',
            'brand_status' => 'require',
            'label_group_status' => 'require',
            'label_status' => 'require',
            'service_status' => 'require'
        ];

       protected $message = [
            'site_name.require' => ['common_validate.require', ['site_name']],
            'client.require' => ['common_validate.require', ['client']],
            'client.number' => ['common_validate.number', ['client']],
            'category_status.require' => ['common_validate.require', ['category_status']],
            'brand_status.require' => ['common_validate.require', ['brand_status']],
            'label_group_status.require' => ['common_validate.require', ['label_group_status']],
            'label_status.require' => ['common_validate.require', ['label_status']],
            'service_status.require' => ['common_validate.require', ['service_status']]
        ];

       protected $scene = [
            "add" => ['site_name', 'client', 'category_status', 'brand_status', 'label_group_status', 'label_status', 'service_status'],
            "edit" => ['site_name', 'client', 'category_status', 'brand_status', 'label_group_status', 'label_status', 'service_status']
        ];

}
