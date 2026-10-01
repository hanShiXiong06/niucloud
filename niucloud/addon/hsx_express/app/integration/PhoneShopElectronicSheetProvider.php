<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

use addon\hsx_express\app\service\core\WaybillTaskDispatcher;

class PhoneShopElectronicSheetProvider
{
    public static function withBusinessLock(int $siteId, string $businessType, int $orderId, callable $operation)
    {
        return \addon\hsx_express\app\support\OperationLock::run($siteId, $businessType . ':order:' . $orderId, $operation);
    }

    public static function execute(int $siteId, string $operation, array $payload): array
    {
        return (new WaybillTaskDispatcher())->execute($siteId, $operation, $payload, 'kuaidi100');
    }
}
