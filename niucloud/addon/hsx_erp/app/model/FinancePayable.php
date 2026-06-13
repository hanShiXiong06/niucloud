<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class FinancePayable extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_finance_payable';
    protected $autoWriteTimestamp = false;
}
