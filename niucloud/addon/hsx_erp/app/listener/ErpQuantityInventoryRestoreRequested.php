<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpQuantityInventoryService;
use core\exception\CommonException;

class ErpQuantityInventoryRestoreRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP数量库存返库事件格式不正确');
        return (new ErpQuantityInventoryService())->restore($event);
    }
}
