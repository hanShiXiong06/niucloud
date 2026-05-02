<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\device_query;

class DeviceQueryPriceCalculator
{
    public function calculate(array $providerResult, array $service, array $mapping, bool $fromCache = false, bool $success = true): array
    {
        $costPrice = (float)($mapping['cost_price'] ?? $service['cost_price'] ?? 0);
        $thirdCost = ($fromCache || !$success) ? 0.0 : (float)($providerResult['cost'] ?? $costPrice);

        return [
            'third_cost' => $thirdCost,
            'cost_price' => $costPrice,
            'saved_cost' => $fromCache ? $costPrice : 0.0,
            'balance' => $fromCache ? 0.0 : (float)($providerResult['balance'] ?? 0),
        ];
    }
}
