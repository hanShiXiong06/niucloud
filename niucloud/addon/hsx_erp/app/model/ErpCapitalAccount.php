<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpCapitalAccount extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_capital_account';
    protected $autoWriteTimestamp = false;
}
