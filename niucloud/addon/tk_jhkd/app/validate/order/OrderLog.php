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

namespace addon\tk_jhkd\app\validate\order;
use core\base\BaseValidate;
/**
 * 订单日志验证器
 * Class OrderLog
 * @package addon\tk_jhkd\app\validate\order
 */
class OrderLog extends BaseValidate
{

       protected $rule = [
            'id' => 'require',
            'order_id' => 'require',
            'action' => 'require',
            'main_type' => 'require',
            'nick_name' => 'require',
        ];

       protected $message = [
            'id.require' => ['common_validate.require', ['id']],
            'order_id.require' => ['common_validate.require', ['order_id']],
            'action.require' => ['common_validate.require', ['action']],
            'main_type.require' => ['common_validate.require', ['main_type']],
            'nick_name.require' => ['common_validate.require', ['nick_name']],
        ];

       protected $scene = [
            "add" => ['order_id', 'action', 'main_type', 'main_id', 'nick_name', 'order_status'],
            "edit" => ['order_id', 'action', 'main_type', 'main_id', 'nick_name', 'order_status']
        ];

}
