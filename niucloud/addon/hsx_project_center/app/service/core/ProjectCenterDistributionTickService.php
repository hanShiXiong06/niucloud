<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

final class ProjectCenterDistributionTickService
{
    public function tick(): void
    {
        $service = new ProjectCenterDistributionService();
        $service->reconcileApproved(100);
        $service->reconcileRefunds(100);
        $service->settleDue(100);
    }
}
