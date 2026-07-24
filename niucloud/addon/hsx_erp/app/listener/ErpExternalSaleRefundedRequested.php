<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpExternalSaleRefundedService;
use core\exception\CommonException;

class ErpExternalSaleRefundedRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP外部商品退款事件格式不正确');
        return ErpExternalSaleRefundedService::forSite((int)($event['site_id'] ?? 0))->consume($event);
    }
}
