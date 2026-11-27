<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use addon\hsx_erp\app\support\ErpIdempotency;
use core\base\BaseModel;

class ErpStocktake extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_stocktake';
    protected $autoWriteTimestamp = false;

    public function setRequestIdAttr($value): ?string
    {
        return ErpIdempotency::nullable($value);
    }
}
