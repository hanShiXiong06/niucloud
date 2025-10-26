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
 * 师傅等级验证器
 */
class Level extends \think\Validate
{

    protected $rule = [
        'level_num' => 'require',
        'level_name' => 'require',
    ];

    protected $message = [
        'level_num.require' => 'home_service_technician_level.level_num_require',
        'level_name.require' => 'home_service_technician_level.level_name_require',
    ];

    protected $scene = [
        "add" => ['level_num', 'level_name'],
        "edit" => ['level_name']
    ];

}
