<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpWarehouseLocation extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_warehouse_location';
    protected $autoWriteTimestamp = false;
}
