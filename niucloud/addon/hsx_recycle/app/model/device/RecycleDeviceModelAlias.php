<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\device;

use core\base\BaseModel;

class RecycleDeviceModelAlias extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_device_model_alias';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
