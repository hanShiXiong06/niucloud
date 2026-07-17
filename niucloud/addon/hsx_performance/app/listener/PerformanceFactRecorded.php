<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\listener;

use addon\hsx_performance\app\service\core\PerformanceFactService;

final class PerformanceFactRecorded
{
    public function handle(array $event): array
    {
        return (new PerformanceFactService())->consume($event);
    }
}
