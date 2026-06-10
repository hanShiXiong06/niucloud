<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpStockOrderItem extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_stock_order_item';
    protected $autoWriteTimestamp = false;
}
