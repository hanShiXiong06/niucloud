<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpQuantityProduct extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_quantity_product';
    protected $autoWriteTimestamp = false;
}
