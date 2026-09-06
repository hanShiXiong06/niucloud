<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\erp;

use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleErpPurchasePriceService;
use core\exception\CommonException;

class ErpPurchasePriceAdjustmentRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP 采购调价参数不正确');
        return (new CoreRecycleErpPurchasePriceService())->apply($event);
    }
}
