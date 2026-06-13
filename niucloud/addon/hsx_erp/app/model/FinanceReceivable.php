<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class FinanceReceivable extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_finance_receivable';
    protected $autoWriteTimestamp = false;
}
