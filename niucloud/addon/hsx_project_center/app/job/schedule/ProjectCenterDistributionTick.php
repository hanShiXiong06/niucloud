<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\job\schedule;

use addon\hsx_project_center\app\service\core\ProjectCenterDistributionTickService;
use core\base\BaseJob;

final class ProjectCenterDistributionTick extends BaseJob
{
    public function doJob(array $params = []): void
    {
        (new ProjectCenterDistributionTickService())->tick();
    }
}
