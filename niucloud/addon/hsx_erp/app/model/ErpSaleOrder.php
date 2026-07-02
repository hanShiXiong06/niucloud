<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpSaleOrder extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_sale_order';
    protected $autoWriteTimestamp = false;
}
