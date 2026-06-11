<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpCounterpartyMember extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_counterparty_member';
    protected $autoWriteTimestamp = false;
}
