<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpAccountLedger extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_account_ledger';
    protected $autoWriteTimestamp = false;

    protected static function onAfterInsert($model): void
    {
        \addon\hsx_erp\app\support\ErpLedgerFact::record('account', $model->toArray());
    }
}
