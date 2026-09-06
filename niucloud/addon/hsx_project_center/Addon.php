<?php
declare(strict_types=1);

namespace addon\hsx_project_center;

use addon\hsx_project_center\app\support\ProjectCenterSchema;
use app\service\core\schedule\CoreScheduleInstallService;

final class Addon
{
    public function install(): bool
    {
        ProjectCenterSchema::migrate();
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_project_center');
        return true;
    }

    public function uninstall(): bool
    {
        (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_project_center');
        return true;
    }

    public function upgrade(): bool
    {
        ProjectCenterSchema::migrate();
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_project_center');
        return true;
    }
}
