<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\listener;

use addon\hsx_device_asset\app\service\core\DeviceAssetErpEventService;

class ErpAssetReadyForPhotoListener
{
    public function handle(array $event): array
    {
        if ((string)($event['event_name'] ?? '') !== 'erp.asset.ready_for_photo.v1') {
            return ['skipped' => true];
        }
        return (new DeviceAssetErpEventService())->createFromReadyForPhotoEvent($event);
    }
}
