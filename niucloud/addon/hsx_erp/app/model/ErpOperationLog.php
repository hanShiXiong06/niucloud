<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpOperationLog extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_operation_log';
    protected $autoWriteTimestamp = false;

    protected static function onAfterInsert($model): void
    {
        \addon\hsx_erp\app\support\ErpRecycleOperationAudit::record('operation', $model->getData());
    }
}
