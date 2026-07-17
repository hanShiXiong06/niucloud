<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpFinanceTaskService;

final class BusinessTaskValidate
{
    public function handle(array $event = []): array
    {
        return ErpFinanceTaskService::forSite((int)($event['site_id'] ?? 0))->validate($event);
    }
}
