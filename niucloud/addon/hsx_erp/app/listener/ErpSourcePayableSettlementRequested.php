<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpSourcePayableSettlementService;
use core\exception\CommonException;

class ErpSourcePayableSettlementRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP来源设备付款事件格式不正确');
        return (new ErpSourcePayableSettlementService())->consume($event);
    }
}
