<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\report;

use addon\hsx_recycle\app\service\core\report\RecycleBusinessReportMetricsService;

final class RecycleBusinessReportMetricsProvider
{
    public function handle(array $request): array
    {
        try {
            return (new RecycleBusinessReportMetricsService())->collect($request);
        } catch (\Throwable $e) {
            return ['provider' => 'hsx_recycle', 'provider_name' => '回收业务', 'available' => false, 'error' => $e->getMessage()];
        }
    }
}
