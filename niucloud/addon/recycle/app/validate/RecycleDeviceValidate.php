<?php
declare(strict_types=1);

namespace addon\recycle\app\validate;

use core\base\BaseValidate;

/**
 * 回收设备验证器
 * Class RecycleDeviceValidate
 * @package addon\recycle\app\validate
 */
class RecycleDeviceValidate extends BaseValidate
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'order_id' => 'require|integer|gt:0',
        'imei' => 'require|max:50',
        'model' => 'max:100',
        'initial_price' => 'float|egt:0',
        'final_price' => 'float|egt:0',
        'status' => 'integer|in:1,2,3,4,5,6',
        'remark' => 'max:500',
        'check_result' => 'max:1000',
        'check_images' => 'max:1000',
        'check_status' => 'integer|in:1,2',
        'price_remark' => 'max:500',
        'ids' => 'require',
        'page' => 'integer|gt:0',
        'limit' => 'integer|between:1,100'
    ];

    protected $message = [
        'id.require' => '设备ID不能为空',
        'id.integer' => '设备ID必须为整数',
        'id.gt' => '设备ID必须大于0',
        'order_id.require' => '订单ID不能为空',
        'order_id.integer' => '订单ID必须为整数',
        'order_id.gt' => '订单ID必须大于0',
        'imei.require' => 'IMEI不能为空',
        'imei.max' => 'IMEI不能超过50个字符',
        'model.max' => '设备型号不能超过100个字符',
        'initial_price.float' => '初始价格必须为数字',
        'initial_price.egt' => '初始价格必须大于等于0',
        'final_price.float' => '最终价格必须为数字',
        'final_price.egt' => '最终价格必须大于等于0',
        'status.integer' => '状态必须为整数',
        'status.in' => '状态值不正确',
        'remark.max' => '备注不能超过500个字符',
        'check_result.max' => '质检结果不能超过1000个字符',
        'check_images.max' => '质检图片不能超过1000个字符',
        'check_status.integer' => '质检状态必须为整数',
        'check_status.in' => '质检状态值不正确',
        'price_remark.max' => '价格备注不能超过500个字符',
        'ids.require' => '设备ID列表不能为空',
        'page.integer' => '页码必须为整数',
        'page.gt' => '页码必须大于0',
        'limit.integer' => '每页数量必须为整数',
        'limit.between' => '每页数量必须在1-100之间'
    ];

    protected $scene = [
        'list' => ['page', 'limit'],
        'detail' => ['id'],
        'create' => ['order_id', 'imei', 'model', 'initial_price', 'remark'],
        'update' => ['id', 'imei', 'model', 'initial_price', 'final_price', 'status', 'remark', 'check_result', 'check_images', 'check_status', 'price_remark'],
        'delete' => ['id'],
        'check' => ['id', 'remark', 'check_result', 'check_images', 'check_status', 'final_price'],
        'price' => ['id', 'final_price', 'price_remark', 'remark'],
        'recycle' => ['id', 'remark'],
        'return' => ['id', 'remark'],
        'batch' => ['ids', 'remark', 'status'],
        'imei' => ['imei']
    ];
} 