<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpCapitalAccountOptionService;
use core\exception\CommonException;

class ErpCapitalAccountOptionsRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP资金账户选项事件格式不正确');
        return (new ErpCapitalAccountOptionService())->consume($event);
    }
}
