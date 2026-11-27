<?php
declare(strict_types=1);

namespace addon\hsx_lead;

use app\service\core\addon\CoreAddonInstallService;
use app\service\core\schedule\CoreScheduleInstallService;
use app\dict\schedule\ScheduleDict;
use app\model\sys\SysSchedule;
use addon\hsx_lead\app\service\NoticeConfigService;

/**
 * Plugin-owned tables only. Uninstall intentionally preserves business history.
 */
final class Addon
{
    public function install(): bool
    {
        CoreAddonInstallService::executeSql(__DIR__ . '/sql/install.sql');
        NoticeConfigService::syncAssignedSites();
        $this->registerSchedule(true);
        return true;
    }

    public function upgrade(): bool
    {
        CoreAddonInstallService::executeSql(__DIR__ . '/sql/install.sql');
        NoticeConfigService::syncAssignedSites();
        $this->registerSchedule(false);
        return true;
    }

    public function uninstall(): bool
    {
        (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_lead');
        return true;
    }

    private function registerSchedule(bool $disable): void
    {
        // Preserve each existing schedule's on/off status across reinstall; new or fresh installs stay OFF.
        $keys = ['hsx_lead_inventory_sync', 'hsx_lead_promotion_settle', 'hsx_lead_virtual_payment_reconcile'];
        $statuses = [];
        foreach ($keys as $key) {
            $existing = (new SysSchedule())->where([['addon', '=', 'hsx_lead'], ['key', '=', $key]])->findOrEmpty();
            $statuses[$key] = $disable || $existing->isEmpty() ? ScheduleDict::OFF : (int)$existing['status'];
        }
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_lead');
        foreach ($statuses as $key => $status) {
            (new SysSchedule())->where([['addon', '=', 'hsx_lead'], ['key', '=', $key]])->update(['status' => $status]);
        }
    }
}

