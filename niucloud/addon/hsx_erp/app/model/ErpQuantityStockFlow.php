<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpQuantityStockFlow extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_quantity_stock_flow';
    protected $autoWriteTimestamp = false;

    protected static function onAfterInsert($model): void
    {
        // Standard sales attach their source item later in the same transaction.
        if ((string)$model->biz_type === 'sale_standard' && (int)$model->biz_id === 0) return;
        \addon\hsx_erp\app\support\ErpLedgerFact::record('quantity', $model->toArray());
    }
}
