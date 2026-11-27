<?php
declare(strict_types=1);

namespace addon\hsx_marketing;

use app\service\core\schedule\CoreScheduleInstallService;

final class Addon
{
    public function install(): bool
    {
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_marketing');
        return true;
    }

    public function uninstall(): bool
    {
        (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_marketing');
        return true;
    }

    public function upgrade(): bool
    {
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_marketing');
        return true;
    }
}
