<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 退回设备模型
 * Class RecycleReturnDevice
 * @package addon\recycle\app\model
 */
class RecycleReturnDevice extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_return_device';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_at';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = false;

    /**
     * 完成时间字段
     * @var string
     */
    protected $overTime = 'over_at';

    /**
     * 追加属性
     * @var array
     */
    protected $append = [
        'status_name'
    ];

    /**
     * 获取退货状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = [
            0 => '待退货',
            1 => '退货中',
            2 => '已退货'
        ];
        return $status[$data['status']] ?? '';
    }

    /**
     * 关联退货订单表
     * @return \think\model\relation\BelongsTo
     */
    public function returnOrder()
    {
        return $this->belongsTo(RecycleReturnOrder::class, 'return_order_id', 'id');
    }

    /**
     * 关联设备表
     * @return \think\model\relation\BelongsTo
     */
    public function device()
    {
        return $this->belongsTo(RecycleDevice::class, 'device_id', 'id');
    }
}
