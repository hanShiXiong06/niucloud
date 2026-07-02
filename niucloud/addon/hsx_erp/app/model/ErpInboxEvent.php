<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpInboxEvent extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_inbox_event';
    protected $autoWriteTimestamp = false;
}
