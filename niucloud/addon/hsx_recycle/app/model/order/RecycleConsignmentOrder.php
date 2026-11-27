<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\order;

use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use app\model\member\Member;
use core\base\BaseModel;

/**
 * 回收代卖订单模型
 */
class RecycleConsignmentOrder extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_consignment_order';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $append = [
        'status_name',
        'pay_status_name',
    ];

    public function getStatusNameAttr($value, $data): string
    {
        return RecycleConsignmentDict::getStatus($data['status'] ?? RecycleConsignmentDict::STATUS_PENDING);
    }

    public function getPayStatusNameAttr($value, $data): string
    {
        return RecycleConsignmentDict::getPayStatus($data['pay_status'] ?? RecycleConsignmentDict::PAY_STATUS_UNPAID);
    }

    public function sourceOrder()
    {
        return $this->belongsTo(RecycleOrder::class, 'source_order_id', 'id');
    }

    public function sourceDevice()
    {
        return $this->belongsTo(RecycleDevice::class, 'source_device_id', 'id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }
}
