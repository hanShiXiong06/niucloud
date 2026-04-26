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

namespace addon\wj_books\app\validate\wj_books_express_log;

use think\Validate;

/**
 * 物流回调日志验证器
 * Class WjBooksExpressLog
 * @package addon\wj_books\app\validate\wj_books_express_log
 */
class WjBooksExpressLog extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'waybill' => 'require|max:64',
        'shopbill' => 'max:64',
        'type' => 'max:50',
        'type_code' => 'integer|between:1,99',
        'weight' => 'float',
        'real_weight' => 'float',
        'transfer_weight' => 'float',
        'cal_weight' => 'float',
        'volume' => 'float',
        'parse_weight' => 'float',
        'total_freight' => 'float',
        'freight' => 'float',
        'freight_insured' => 'float',
        'freight_haocai' => 'float',
        'change_bill' => 'max:64',
        'change_bill_freight' => 'float',
        'fee_over' => 'in:0,1',
        'courier_name' => 'max:50',
        'courier_phone' => 'max:20',
        'pickup_code' => 'max:50'
    ];

    /**
     * 错误提示
     * @var array
     */
    protected $message = [
        'waybill.require' => '运单号不能为空',
        'waybill.max' => '运单号长度不能超过64个字符',
        'shopbill.max' => '商家单号长度不能超过64个字符',
        'type.max' => '运单状态长度不能超过50个字符',
        'type_code.integer' => '状态码必须是整数',
        'type_code.between' => '状态码必须在1-99之间',
        'weight.float' => '下单重量必须是浮点数',
        'real_weight.float' => '站点称重必须是浮点数',
        'transfer_weight.float' => '分拣称重必须是浮点数',
        'cal_weight.float' => '计费重量必须是浮点数',
        'volume.float' => '体积必须是浮点数',
        'parse_weight.float' => '体积换算重量必须是浮点数',
        'total_freight.float' => '运单总扣款费用必须是浮点数',
        'freight.float' => '快递费必须是浮点数',
        'freight_insured.float' => '保价费必须是浮点数',
        'freight_haocai.float' => '增值费用必须是浮点数',
        'change_bill.max' => '换单号长度不能超过64个字符',
        'change_bill_freight.float' => '逆向费必须是浮点数',
        'fee_over.in' => '订单扣费状态必须是0或1',
        'courier_name.max' => '快递员姓名长度不能超过50个字符',
        'courier_phone.max' => '快递员电话长度不能超过20个字符',
        'pickup_code.max' => '取件码长度不能超过50个字符'
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'callback' => ['waybill']
    ];
}