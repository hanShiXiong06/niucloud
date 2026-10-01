<?php
declare(strict_types=1);
namespace addon\hsx_express\app\integration;

use addon\hsx_express\app\service\core\WaybillTaskDispatcher;
use addon\hsx_express\app\support\OperationLock;

final class PhoneShopSfElectronicSheetProvider
{
    /** Optional provider-neutral guard, including the original manual-number shipment path. */
    public static function assertDeliveryAllowed(int $siteId, array $payload): void
    {
        (new \addon\hsx_express\app\service\core\SfWaybillService())->assertDeliveryAllowed($siteId, $payload);
    }
    public static function withBusinessLock(int $siteId, string $businessType, int $orderId, callable $operation)
    {
        return OperationLock::run($siteId, $businessType . ':order:' . $orderId, $operation);
    }

    public static function execute(int $siteId, string $operation, array $payload): array
    {
        return (new WaybillTaskDispatcher())->execute($siteId, $operation, $payload, 'sf_direct');
    }
}
