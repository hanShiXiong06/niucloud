<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\device;

use core\base\BaseModel;

/**
 * 回收设备型号字典
 */
class RecycleDeviceModelDict extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_device_model_dict';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
