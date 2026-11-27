<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\order;

use core\base\BaseModel;

/**
 * 回收设备成本调整记录
 */
class RecycleDeviceCostAdjustment extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_device_cost_adjustment';

    protected $autoWriteTimestamp = false;
}
