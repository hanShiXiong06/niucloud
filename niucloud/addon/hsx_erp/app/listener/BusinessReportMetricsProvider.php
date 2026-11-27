<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\core\ErpBusinessReportMetricsService;

final class BusinessReportMetricsProvider
{
    public function handle(array $request): array
    {
        try {
            return (new ErpBusinessReportMetricsService())->collect($request);
        } catch (\Throwable $e) {
            return ['provider' => 'hsx_erp', 'provider_name' => '二手机 ERP', 'available' => false, 'error' => $e->getMessage()];
        }
    }
}
