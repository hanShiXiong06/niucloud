<?php
declare(strict_types=1);

namespace addon\recycle\app\validate\recycle_order;

use think\Validate;

/**
 * 回收设备验证器
 * Class RecycleDevice
 * @package addon\recycle\app\validate\recycle_order
 */
class RecycleDevice extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'site_id' => 'require|number',
        'order_id' => 'require|number',
        'imei' => 'requireWithout:model|max:50',
        'model' => 'requireWithout:imei|max:100',
        'status' => 'number|in:1,2,3,4,5,6',
        'check_status' => 'number|in:0,1,2',
        'check_result' => 'max:65535',
        'check_images' => 'array',
        'initial_price' => 'float',
        'final_price' => 'float',
        'price_remark' => 'max:255',
        'remark' => 'max:255',
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
        'imei.requireWithout' => 'IMEI号码和型号至少填写一个',
        'imei.max' => 'IMEI号码最多50个字符',
        'model.requireWithout' => 'IMEI号码和型号至少填写一个',
        'model.max' => '设备型号最多100个字符',
        'status.number' => '设备状态必须为数字',
        'status.in' => '设备状态不正确',
        'check_status.number' => '质检状态必须为数字',
        'check_status.in' => '质检状态不正确',
        'check_result.max' => '质检结果过长',
        'check_images.array' => '质检图片格式不正确',
        'initial_price.float' => '预估价格必须为数字',
        'final_price.float' => '最终价格必须为数字',
        'price_remark.max' => '价格备注最多255个字符',
        'remark.max' => '备注最多255个字符',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'create' => ['site_id', 'order_id', 'imei', 'model', 'initial_price', 'remark'],
        'edit' => ['site_id', 'imei', 'model', 'remark'],
        'check' => ['check_status', 'check_result', 'check_images'],
        'price' => ['final_price', 'price_remark'],
    ];
}