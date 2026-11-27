<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpFinanceFactVoidService;
use core\exception\CommonException;

class ErpFinanceFactVoidRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP财务事实作废事件格式不正确');
        return (new ErpFinanceFactVoidService())->consume($event);
    }
}
