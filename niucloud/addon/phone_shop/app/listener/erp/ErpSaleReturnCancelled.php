<?php
declare(strict_types=1);
namespace addon\phone_shop\app\listener\erp;

class ErpSaleReturnCancelled
{
    public function handle(array $data): array
    {
        return (new \addon\phone_shop\app\service\core\order\CoreOrderDeviceReturnService())->undoErpReturn($data);
    }
}
