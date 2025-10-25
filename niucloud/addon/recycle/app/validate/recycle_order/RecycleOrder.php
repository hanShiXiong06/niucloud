<?php
declare(strict_types=1);

namespace addon\recycle\app\validate\recycle_order;

use think\Validate;

/**
 * 回收订单验证器
 * Class RecycleOrder
 * @package addon\recycle\app\validate\recycle_order
 */
class RecycleOrder extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'site_id' => 'require|number',
        'customer_name' => 'require|max:50',
        'customer_phone' => 'require|mobile',
        'delivery_type' => 'require|in:express,self',
        'express_company' => 'requireIf:delivery_type,express',
        'express_no' => 'requireIf:delivery_type,express',
        'device_count' => 'require|number|min:1',
        'devices' => 'array',
        'devices.*.imei' => 'requireWithout:devices.*.model|max:50',
        'devices.*.model' => 'requireWithout:devices.*.imei|max:100',
        'devices.*.initial_price' => 'float',
        'remark' => 'max:255',
    ];

    /**
     * 错误信息
     * @var array
     */
    protected $message = [
        'site_id.require' => '站点ID不能为空',
        'site_id.number' => '站点ID必须为数字',
        'customer_name.require' => '客户姓名不能为空',
        'customer_name.max' => '客户姓名最多50个字符',
        'customer_phone.require' => '客户电话不能为空',
        'customer_phone.mobile' => '客户电话格式不正确',
        'delivery_type.require' => '发货方式不能为空',
        'delivery_type.in' => '发货方式不正确',
        'express_company.requireIf' => '快递公司不能为空',
        'express_no.requireIf' => '快递单号不能为空',
        'device_count.require' => '设备数量不能为空',
        'device_count.number' => '设备数量必须为数字',
        'device_count.min' => '设备数量必须大于0',
        'devices.array' => '设备信息格式不正确',
        'devices.*.imei.requireWithout' => 'IMEI号码和型号至少填写一个',
        'devices.*.imei.max' => 'IMEI号码最多50个字符',
        'devices.*.model.requireWithout' => 'IMEI号码和型号至少填写一个',
        'devices.*.model.max' => '设备型号最多100个字符',
        'devices.*.initial_price.float' => '预估价格必须为数字',
        'remark.max' => '备注最多255个字符',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'create' => ['site_id', 'customer_name', 'customer_phone', 'delivery_type', 'express_company', 'express_no', 'device_count', 'devices', 'remark'],
        'edit' => ['site_id', 'customer_name', 'customer_phone', 'delivery_type', 'express_company', 'express_no', 'remark'],
    ];
}