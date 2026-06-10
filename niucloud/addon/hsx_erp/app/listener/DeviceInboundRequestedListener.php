<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\service\core\ErpInboundService;

class DeviceInboundRequestedListener
{
    public function handle(array $event): array
    {
        $targets = array_values(array_unique((array)($event['targets'] ?? [])));
        if (!in_array(ErpDict::TARGET_SELF_ERP, $targets, true)) {
            return [
                'target' => ErpDict::TARGET_SELF_ERP,
                'skipped' => true,
            ];
        }

        return (new ErpInboundService())->receive($event);
    }
}
