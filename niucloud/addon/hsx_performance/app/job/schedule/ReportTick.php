<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\job\schedule;

use addon\hsx_performance\app\service\core\PerformanceReportService;
use core\base\BaseJob;

final class ReportTick extends BaseJob
{
    public function doJob(array $params = []): void
    {
        (new PerformanceReportService())->tick();
    }
}
