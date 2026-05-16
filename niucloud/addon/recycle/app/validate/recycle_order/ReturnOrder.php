<?php
declare(strict_types=1);

namespace addon\recycle\app\validate\recycle_order;

use think\Validate;

/**
 * 退货订单验证器
 * Class ReturnOrder
 * @package addon\recycle\app\validate\recycle_order
 */
class ReturnOrder extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'site_id' => 'require|number',
        'order_id' => 'require|number',
        'express_id' => 'requireCallback:checkExpressRequired',
        'express_company' => 'requireCallback:checkExpressRequired',
        'return_address' => 'require|max:255',
        'status' => 'number|in:0,1,2',
        'device_ids' => 'require|array',
        'comment' => 'max:500',
    ];

    /**
     * 错误信息
     * @var array
     */
    protected $message = [
        'site_id.require' => '站点ID不能为空',
        'site_id.number' => '站点ID必须为数字',
        'order_id.require' => '订单ID不能为空',
        'order_id.number' => '订单ID必须为数字',
        'express_id.requireCallback' => '快递单号不能为空',
        'express_company.requireCallback' => '快递公司不能为空',
        'return_address.require' => '退货地址不能为空',
        'return_address.max' => '退货地址最多255个字符',
        'status.number' => '退货状态必须为数字',
        'status.in' => '退货状态不正确',
        'device_ids.require' => '退货设备不能为空',
        'device_ids.array' => '退货设备格式不正确',
        'comment.max' => '备注最多500个字符',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'create' => ['site_id', 'order_id', 'return_address', 'device_ids', 'comment'],
        'express' => ['express_id', 'express_company'],
        'status' => ['status'],
    ];

    /**
     * 检查快递信息是否必填
     * @param $value
     * @param $data
     * @return bool
     */
    protected function checkExpressRequired($value, $data)
    {
        return isset($data['status']) && $data['status'] == 1;
    }
}
