<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpPartyBridgeService;
use core\exception\CommonException;

class ErpPartyResolveRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP往来主体解析事件格式不正确');
        return (new ErpPartyBridgeService())->consume($event);
    }
}
