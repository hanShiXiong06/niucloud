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

namespace addon\fast_pay\app\validate\businessorder;
use core\base\BaseValidate;
/**
 * 商户订单列验证器
 * Class BusinessOrder
 * @package addon\fast_pay\app\validate\businessorder
 */
class BusinessOrder extends BaseValidate
{

       protected $rule = [
            'member_id' => 'require',
            'order_from' => 'require',
            'order_id' => 'require',
            'order_money' => 'require',
            'order_discount_money' => 'require',
            'order_status' => 'require'
        ];

       protected $message = [
            'member_id.require' => ['common_validate.require', ['member_id']],
            'order_from.require' => ['common_validate.require', ['order_from']],
            'order_id.require' => ['common_validate.require', ['order_id']],
            'order_money.require' => ['common_validate.require', ['order_money']],
            'order_discount_money.require' => ['common_validate.require', ['order_discount_money']],
            'order_status.require' => ['common_validate.require', ['order_status']]
        ];

       protected $scene = [
            "add" => ['member_id', 'business_id', 'order_from', 'order_id', 'order_money', 'order_discount_money', 'order_status', 'refund_status', 'out_trade_no', 'remark', 'pay_time', 'close_reason', 'is_enable_refund', 'close_time', 'ip'],
            "edit" => ['member_id', 'business_id', 'order_from', 'order_id', 'order_money', 'order_discount_money', 'order_status', 'refund_status', 'out_trade_no', 'remark', 'pay_time', 'close_reason', 'is_enable_refund', 'close_time', 'ip']
        ];

}
