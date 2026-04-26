<?php
declare(strict_types=1);

namespace addon\recycle\app\validate;

use think\Validate;

/**
 * 退回订单验证器
 * Class RecycleReturnOrderValidate
 * @package addon\recycle\app\validate
 */
class RecycleReturnOrderValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'order_id' => 'require|integer|gt:0',
        'device_ids' => 'require|array',
        'express_company' => 'max:100',
        'express_no' => 'max:100',
        'return_address' => 'max:255',
        'comment' => 'max:1000',
        'status' => 'require|integer|between:0,3',
        'page' => 'integer|gt:0',
        'limit' => 'integer|gt:0',
        'orders' => 'require|array',
    ];

    /**
     * 错误消息
     * @var array
     */
    protected $message = [
        'id.require' => '订单ID不能为空',
        'id.integer' => '订单ID必须为整数',
        'id.gt' => '订单ID必须大于0',
        'order_id.require' => '原订单ID不能为空',
        'order_id.integer' => '原订单ID必须为整数',
        'order_id.gt' => '原订单ID必须大于0',
        'device_ids.require' => '设备ID不能为空',
        'device_ids.array' => '设备ID必须为数组',
        'express_company.max' => '快递公司名称不能超过100个字符',
        'express_no.max' => '快递单号不能超过100个字符',
        'return_address.max' => '退回地址不能超过255个字符',
        'comment.max' => '备注不能超过1000个字符',
        'status.require' => '状态不能为空',
        'status.integer' => '状态必须为整数',
        'status.between' => '状态值必须在0-3之间',
        'page.integer' => '页码必须为整数',
        'page.gt' => '页码必须大于0',
        'limit.integer' => '每页数量必须为整数',
        'limit.gt' => '每页数量必须大于0',
        'orders.require' => '订单数据不能为空',
        'orders.array' => '订单数据必须为数组',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'id' => ['id'],
        'create' => ['order_id', 'device_ids', 'express_company', 'express_no', 'return_address', 'comment'],
        'batch_create' => ['orders'],
        'update_status' => ['id', 'status', 'comment'],
        'cancel' => ['id', 'comment'],
        'delete' => ['id'],
        'list' => ['page', 'limit'],
    ];
} 