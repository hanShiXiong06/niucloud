<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpFinanceSettlementRequestService;
use core\exception\CommonException;

class ErpFinanceSettlementRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP实际收付款事件格式不正确');
        return (new ErpFinanceSettlementRequestService())->consume($event);
    }
}
