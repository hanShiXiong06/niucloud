<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpSettlementLink extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_settlement_link';
    protected $autoWriteTimestamp = false;
}
