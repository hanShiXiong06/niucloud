<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpSyncBatch extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_sync_batch';
    protected $autoWriteTimestamp = false;
    protected $json = ['payload'];
}
