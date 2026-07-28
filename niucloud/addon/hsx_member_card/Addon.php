<?php
declare(strict_types=1);

namespace addon\hsx_member_card;

use addon\hsx_member_card\app\support\MemberCardSchema;
use app\service\core\schedule\CoreScheduleInstallService;
use think\facade\Db;

final class Addon
{
    public function install(): bool
    {
        $this->executeSql(__DIR__ . '/sql/install.sql');
        MemberCardSchema::migrate();
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_member_card');
        return true;
    }

    public function uninstall(): bool
    {
        (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_member_card');
        $this->executeSql(__DIR__ . '/sql/uninstall.sql');
        return true;
    }

    public function upgrade(): bool
    {
        $this->executeSql(__DIR__ . '/sql/install.sql');
        MemberCardSchema::migrate();
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_member_card');
        return true;
    }

    private function executeSql(string $file): void
    {
        if (!is_file($file)) return;
        $prefix = (string)config('database.connections.mysql.prefix');
        foreach ((array)parse_sql((string)file_get_contents($file)) as $statement) {
            $statement = trim(str_replace('{{prefix}}', $prefix, (string)$statement));
            if ($statement !== '') Db::execute($statement);
        }
    }
}
