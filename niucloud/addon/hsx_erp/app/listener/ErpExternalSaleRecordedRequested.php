<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpExternalSaleRecordedService;
use core\exception\CommonException;

class ErpExternalSaleRecordedRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) throw new CommonException('ERP外部商品销售事件格式不正确');
        return ErpExternalSaleRecordedService::forSite((int)($event['site_id'] ?? 0))->consume($event);
    }
}
