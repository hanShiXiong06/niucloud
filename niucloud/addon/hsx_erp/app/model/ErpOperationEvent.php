<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpOperationEvent extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_operation_event';
    protected $autoWriteTimestamp = false;
    protected $json = ['payload'];
}
