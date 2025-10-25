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

namespace addon\recycle\app\validate\recycle_device_model;
use core\base\BaseValidate;
/**
 * 回收设备型号验证器
 * Class RecycleDeviceModel
 * @package addon\recycle\app\validate\recycle_device_model
 */
class RecycleDeviceModel extends BaseValidate
{

       protected $rule = [
            'brand_id' => 'require',
            'model_name' => 'require',
            'status' => 'require'
        ];

       protected $message = [
            'brand_id.require' => ['common_validate.require', ['brand_id']],
            'model_name.require' => ['common_validate.require', ['model_name']],
            'status.require' => ['common_validate.require', ['status']]
        ];

       protected $scene = [
            "add" => ['brand_id', 'model_name', 'network_model', 'capacity', 'device_type', 'status'],
            "edit" => ['brand_id', 'model_name', 'network_model', 'capacity', 'device_type', 'status']
        ];

}
