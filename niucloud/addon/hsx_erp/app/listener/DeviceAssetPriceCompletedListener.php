<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\core\ErpExternalPricingService;

class DeviceAssetPriceCompletedListener
{
    public function handle(array $event): array
    {
        if ((string)($event['event_name'] ?? '') !== 'device_asset.price.completed.v1') {
            return ['skipped' => true];
        }
        return (new ErpExternalPricingService())->applyDeviceAssetPrice($event);
    }
}
