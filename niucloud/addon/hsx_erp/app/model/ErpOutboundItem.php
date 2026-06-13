<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpOutboundItem extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_outbound_item';
    protected $autoWriteTimestamp = false;
}
