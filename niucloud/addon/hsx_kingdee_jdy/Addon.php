<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy;

use app\service\core\schedule\CoreScheduleInstallService;

final class Addon
{
    public function install(): bool
    {
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_kingdee_jdy');
        return true;
    }

    public function upgrade(): bool { return $this->install(); }

    public function uninstall(): bool
    {
        (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_kingdee_jdy');
        return true;
    }
}
