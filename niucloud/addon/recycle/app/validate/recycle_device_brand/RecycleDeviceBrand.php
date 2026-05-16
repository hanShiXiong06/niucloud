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

namespace addon\recycle\app\validate\recycle_device_brand;
use core\base\BaseValidate;
/**
 * 回收设备品牌验证器
 * Class RecycleDeviceBrand
 * @package addon\recycle\app\validate\recycle_device_brand
 */
class RecycleDeviceBrand extends BaseValidate
{

       protected $rule = [
            'id' => 'require',
            'brand_name' => 'require',
            'brand_code' => 'require',
            'status' => 'require',
            'sort' => 'require'
        ];

       protected $message = [
            'id.require' => ['common_validate.require', ['id']],
            'brand_name.require' => ['common_validate.require', ['brand_name']],
            'brand_code.require' => ['common_validate.require', ['brand_code']],
            'status.require' => ['common_validate.require', ['status']],
            'sort.require' => ['common_validate.require', ['sort']]
        ];

       protected $scene = [
            "add" => ['brand_name', 'brand_code', 'status', 'sort'],
            "edit" => ['brand_name', 'brand_code', 'status', 'sort']
        ];

}
