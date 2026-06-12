<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\model;

use core\base\BaseModel;

class FinanceReceivable extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'finance_receivable';
    protected $autoWriteTimestamp = false;
}
