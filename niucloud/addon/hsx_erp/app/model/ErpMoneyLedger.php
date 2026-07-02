<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpMoneyLedger extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_money_ledger';
    protected $autoWriteTimestamp = false;
}
