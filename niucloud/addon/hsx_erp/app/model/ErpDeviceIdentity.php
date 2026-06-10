<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpDeviceIdentity extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_device_identity';
    protected $autoWriteTimestamp = false;
}
