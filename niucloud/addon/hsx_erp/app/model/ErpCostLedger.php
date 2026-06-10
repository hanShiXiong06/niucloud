<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpCostLedger extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_cost_ledger';
    protected $autoWriteTimestamp = false;
}
