<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpSyncTarget extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_sync_target';
    protected $autoWriteTimestamp = false;
    protected $json = ['response_data'];
}
