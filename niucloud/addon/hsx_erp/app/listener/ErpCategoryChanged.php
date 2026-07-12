<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpCategorySyncService;

class ErpCategoryChanged
{
    public function handle(array $payload = []): array
    {
        return (new ErpCategorySyncService())->receiveExternalChange($payload);
    }
}
