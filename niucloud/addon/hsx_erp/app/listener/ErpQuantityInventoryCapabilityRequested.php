<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpQuantityInventoryService;
use core\exception\CommonException;

class ErpQuantityInventoryCapabilityRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP数量库存能力事件格式不正确');
        return (new ErpQuantityInventoryService())->capability($event);
    }
}
