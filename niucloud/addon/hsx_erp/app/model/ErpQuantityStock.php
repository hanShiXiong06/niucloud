<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpQuantityStock extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_quantity_stock';
    protected $autoWriteTimestamp = false;
}
