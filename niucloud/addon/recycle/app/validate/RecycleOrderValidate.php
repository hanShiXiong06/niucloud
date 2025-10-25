<?php
declare(strict_types=1);

namespace addon\recycle\app\validate;

use core\base\BaseValidate;

/**
 * 回收订单验证器
 * Class RecycleOrderValidate
 * @package addon\recycle\app\validate
 */
class RecycleOrderValidate extends BaseValidate
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'member_id' => 'integer|egt:0',
        'customer_name' => 'max:50',
        'customer_phone' => 'mobile',
        'express_no' => 'max:50',
        'express_company' => 'max:50',
        'delivery_type' => 'integer|in:1,2',
        'remark' => 'max:500',
        'devices' => 'array',
        'pay_account' => 'max:100',
        'pay_type' => 'integer|in:1,2,3,4',
        'pay_name' => 'max:50',
        'pay_remark' => 'max:500',
        'pay_url' => 'max:500',
        'cancel_reason' => 'max:500',
        'close_reason' => 'max:500',
        'action' => 'max:50',
        'device_ids' => 'array',
        'page' => 'integer|gt:0',
        'limit' => 'integer|between:1,100',
        'imei' => 'max:50',
        'model' => 'max:100',
        'initial_price' => 'float|egt:0',
        'device_id' => 'integer|gt:0',
        'order_id' => 'integer|gt:0',
        'reason' => 'max:500'
    ];

    protected $message = [
        'id.require' => '订单ID不能为空',
        'id.integer' => '订单ID必须为整数',
        'id.gt' => '订单ID必须大于0',
        'member_id.integer' => '会员ID必须为整数',
        'member_id.egt' => '会员ID必须大于等于0',
        'customer_name.max' => '客户姓名不能超过50个字符',
        'customer_phone.mobile' => '客户手机号格式不正确',
        'express_no.max' => '快递单号不能超过50个字符',
        'express_company.max' => '快递公司不能超过50个字符',
        'delivery_type.integer' => '配送方式必须为整数',
        'delivery_type.in' => '配送方式值不正确',
        'remark.max' => '备注不能超过500个字符',
        'devices.array' => '设备列表必须为数组',
        'pay_account.max' => '收款账号不能超过100个字符',
        'pay_type.integer' => '支付方式必须为整数',
        'pay_type.in' => '支付方式值不正确',
        'pay_name.max' => '收款人姓名不能超过50个字符',
        'pay_remark.max' => '支付备注不能超过500个字符',
        'pay_url.max' => '支付凭证URL不能超过500个字符',
        'cancel_reason.max' => '取消原因不能超过500个字符',
        'close_reason.max' => '关闭原因不能超过500个字符',
        'action.max' => '操作类型不能超过50个字符',
        'device_ids.array' => '设备ID列表必须为数组',
        'page.integer' => '页码必须为整数',
        'page.gt' => '页码必须大于0',
        'limit.integer' => '每页数量必须为整数',
        'limit.between' => '每页数量必须在1-100之间',
        'imei.max' => 'IMEI不能超过50个字符',
        'model.max' => '设备型号不能超过100个字符',
        'initial_price.float' => '初始价格必须为数字',
        'initial_price.egt' => '初始价格必须大于等于0',
        'device_id.integer' => '设备ID必须为整数',
        'device_id.gt' => '设备ID必须大于0',
        'order_id.integer' => '订单ID必须为整数',
        'order_id.gt' => '订单ID必须大于0',
        'reason.max' => '操作原因不能超过500个字符'
    ];

    protected $scene = [
        'list' => ['page', 'limit'],
        'detail' => ['id'],
        'create' => ['member_id', 'customer_name', 'customer_phone', 'express_no', 'express_company', 'delivery_type', 'remark', 'devices'],
        'sign' => ['id', 'devices', 'remark'],
        'check' => ['id', 'devices', 'remark'],
        'price' => ['id', 'devices', 'pay_account', 'pay_type', 'pay_name', 'pay_remark', 'pay_url', 'remark'],
        'payment' => ['id', 'pay_account', 'pay_type', 'pay_name', 'pay_remark', 'pay_url', 'remark'],
        'close' => ['id', 'close_reason', 'remark'],
        'cancel' => ['id', 'cancel_reason', 'remark'],
        'delete' => ['id'],
        'update' => ['id', 'action', 'devices', 'remark', 'cancel_reason', 'close_reason', 'device_ids'],
        'addDevice' => ['id', 'imei', 'model', 'initial_price', 'remark'],
        'removeDevice' => ['order_id', 'device_id', 'reason']
    ];
} 