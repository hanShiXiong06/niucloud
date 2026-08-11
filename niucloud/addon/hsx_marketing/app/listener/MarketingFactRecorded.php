<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\listener;

use addon\hsx_marketing\app\service\core\MarketingEngineService;

final class MarketingFactRecorded
{
    public function handle(array $payload): array
    {
        return (new MarketingEngineService())->recordFact($payload);
    }
}
