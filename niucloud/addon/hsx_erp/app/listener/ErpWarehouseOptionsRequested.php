<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpWarehouseOptionService;
use core\exception\CommonException;

class ErpWarehouseOptionsRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP仓库选项事件格式不正确');
        return (new ErpWarehouseOptionService())->consume($event);
    }
}
