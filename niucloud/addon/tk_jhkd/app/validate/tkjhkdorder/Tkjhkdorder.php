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

namespace addon\tk_jhkd\app\validate\tkjhkdorder;
use core\base\BaseValidate;
/**
 * 订单列验证器
 * Class Tkjhkdorder
 * @package addon\tk_jhkd\app\validate\tkjhkdorder
 */
class Tkjhkdorder extends BaseValidate
{

       protected $rule = [
            'member_id' => 'require',
        ];

       protected $message = [
            'member_id.require' => ['common_validate.require', ['member_id']],
        ];

       protected $scene = [
            "add" => ['member_id', 'order_from', 'order_id', 'order_money', 'order_discount_money', 'is_send', 'is_pick', 'order_status', 'out_trade_no', 'remark', 'pay_time', 'close_reason', 'is_enable_refund', 'close_time', 'ip', 'delete_time'],
            "edit" => ['member_id', 'order_from', 'order_id', 'order_money', 'order_discount_money', 'is_send', 'is_pick', 'order_status', 'out_trade_no', 'remark', 'pay_time', 'close_reason', 'is_enable_refund', 'close_time', 'ip', 'delete_time']
        ];

}
