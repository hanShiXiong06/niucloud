<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpPurchaseItem extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_purchase_item';
    protected $autoWriteTimestamp = false;
}
