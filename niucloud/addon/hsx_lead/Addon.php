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
        $model = new SysSchedule();
        $where = [['addon', '=', 'hsx_lead'], ['key', '=', 'hsx_lead_inventory_sync']];
        $existing = $model->where($where)->findOrEmpty();
        $status = $disable || $existing->isEmpty() ? ScheduleDict::OFF : (int)$existing['status'];
        $promotionWhere = [['addon', '=', 'hsx_lead'], ['key', '=', 'hsx_lead_promotion_settle']];
        $promotion = $model->where($promotionWhere)->findOrEmpty();
        $promotionStatus = $disable || $promotion->isEmpty() ? ScheduleDict::OFF : (int)$promotion['status'];
        $virtualWhere = [['addon', '=', 'hsx_lead'], ['key', '=', 'hsx_lead_virtual_payment_reconcile']];
        $virtual = $model->where($virtualWhere)->findOrEmpty();
        $virtualStatus = $disable || $virtual->isEmpty() ? ScheduleDict::OFF : (int)$virtual['status'];
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_lead');
        $model->where($where)->update(['status' => $status]);
        $model->where($promotionWhere)->update(['status' => $promotionStatus]);
        $model->where($virtualWhere)->update(['status' => $virtualStatus]);
    }
}

