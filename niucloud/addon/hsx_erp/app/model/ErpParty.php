<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpParty extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_party';
    protected $autoWriteTimestamp = false;
}
