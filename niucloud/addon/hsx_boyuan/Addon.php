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
        \addon\hsx_boyuan\app\support\CatalogUpgrade::run();
        (new CoreScheduleInstallService())->installAddonSchedule('hsx_boyuan');

        // 注册到ERP集成框架
        $this->registerAdapter();

        return true;
    }

    public function upgrade(): bool {
        $this->registerAdapter();
        return $this->install();
    }

    public function uninstall(): bool {
        (new CoreScheduleInstallService())->uninstallAddonSchedule('hsx_boyuan');
        return true;
    }

    /**
     * 注册博远适配器到ERP集成框架
     */
    private function registerAdapter(): void
    {
        // 检查集成框架是否已安装
        if (!class_exists(\addon\hsx_erp_integration\app\service\AdapterRegistry::class)) {
            return;
        }

        try {
            $registry = app(\addon\hsx_erp_integration\app\service\AdapterRegistry::class);
            $adapter = new \addon\hsx_boyuan\app\adapter\BoyuanErpAdapter();
            $registry->register($adapter);

            \think\facade\Log::info('博远ERP适配器注册成功');
        } catch (\Throwable $e) {
            \think\facade\Log::warning('博远ERP适配器注册失败: ' . $e->getMessage());
        }
    }
}
