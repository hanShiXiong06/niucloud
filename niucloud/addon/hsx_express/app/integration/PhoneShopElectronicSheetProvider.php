<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

use addon\hsx_express\app\service\core\LogisticsService;

class PhoneShopElectronicSheetProvider
{
    public static function withBusinessLock(int $siteId, string $businessType, int $orderId, callable $operation)
    {
        return \addon\hsx_express\app\support\OperationLock::run($siteId, $businessType . ':order:' . $orderId, $operation);
    }

    public static function execute(int $siteId, string $operation, array $payload): array
    {
        return (new LogisticsService())->execute($siteId, $operation, $payload);
    }
}
