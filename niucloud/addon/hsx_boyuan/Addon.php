<?php
declare(strict_types=1);
namespace addon\hsx_boyuan;
use app\service\core\schedule\CoreScheduleInstallService;
final class Addon
{
    public function install(): bool {
        $sql=file_get_contents(__DIR__.'/sql/references.sql');
        $prefix=(string)\think\facade\Db::connect()->getConfig('prefix');
        foreach(parse_sql($sql) as $statement) if(trim($statement)!=='') \think\facade\Db::execute(str_replace('{{prefix}}',$prefix,$statement));
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_boyuan'); return true;
    }
    public function upgrade(): bool { return $this->install(); }
    public function uninstall(): bool { (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_boyuan'); return true; }
}
