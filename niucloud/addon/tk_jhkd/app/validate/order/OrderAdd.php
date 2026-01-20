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
 * 订单列验证器
 * Class OrderAdd
 * @package addon\tk_jhkd\app\validate\order
 */
class OrderAdd extends BaseValidate
{

       protected $rule = [
            'order_id' => 'require',
        ];

       protected $message = [
            'order_id.require' => ['common_validate.require', ['order_id']],
        ];

       protected $scene = [
            "add" => ['member_id', 'order_id'],
            "edit" => ['member_id', 'order_id']
        ];

}
