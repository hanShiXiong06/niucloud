<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\validate\technician;

/**
 * 师傅验证器
 */
class Technician extends \think\Validate
{

    protected $rule = [
        'member_id' => 'require',
        'real_name' => 'require',
        'mobile' => 'mobile',
    ];

    protected $message = [
        'member_id.require' => 'o2o_technician.member_id_require',
        'real_name.require' => 'o2o_technician.name_require',
        'mobile.require' => 'o2o_technician.mobile_require',
    ];


    protected $scene = [
        "add" => ['member_id', 'real_name', 'mobile'],
        "edit" => ['real_name', 'mobile']
    ];

}