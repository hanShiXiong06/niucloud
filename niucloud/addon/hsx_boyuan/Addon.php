<?php
declare(strict_types=1);
namespace addon\hsx_boyuan;
use app\service\core\schedule\CoreScheduleInstallService;
final class Addon
{
    public function install(): bool { (new CoreScheduleInstallService())->installAddonSchedule('hsx_boyuan'); return true; }
    public function upgrade(): bool { return $this->install(); }
    public function uninstall(): bool { (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_boyuan'); return true; }
}
