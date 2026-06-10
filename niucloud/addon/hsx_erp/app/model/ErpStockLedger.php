<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpStockLedger extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_stock_ledger';
    protected $autoWriteTimestamp = false;
    protected $json = ['payload'];
}
