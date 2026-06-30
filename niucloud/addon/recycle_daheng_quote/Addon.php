<?php

namespace addon\recycle_daheng_quote;

use app\model\sys\SysSchedule;
use think\facade\Db;

/**
 * DH速收报价插件
 */
class Addon
{
    public function install()
    {
        $this->upgradeSchema();
        (new \app\service\core\schedule\CoreScheduleInstallService())->installAddonSchedule('recycle_daheng_quote');
        $this->fixScheduleTime();
        return true;
    }

    public function uninstall()
    {
        (new \app\service\core\schedule\CoreScheduleInstallService())->uninstallAddonSchedule('recycle_daheng_quote');
        return true;
    }

    public function upgrade()
    {
        $this->upgradeSchema();
        (new \app\service\core\schedule\CoreScheduleInstallService())->installAddonSchedule('recycle_daheng_quote');
        $this->fixScheduleTime();
        return true;
    }

    private function upgradeSchema(): void
    {
        $prefix = config('database.connections.mysql.prefix');
        $table = $prefix . 'recycle_quotation_v2_dataset';
        if (!$this->tableExists($table)) {
            return;
        }
        if (!$this->columnExists($table, 'sync_enabled')) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `sync_enabled` tinyint NOT NULL DEFAULT 0 COMMENT '是否开启自动同步' AFTER `follow_crawler`");
        }
        if (!$this->columnExists($table, 'sync_interval')) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `sync_interval` int NOT NULL DEFAULT 86400 COMMENT '自动同步间隔秒' AFTER `sync_enabled`");
        }
        // 浏览量埋点字段(幂等):查看报价数据集 +1
        if (!$this->columnExists($table, 'view_count')) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `view_count` int NOT NULL DEFAULT 0 COMMENT '浏览量' AFTER `sort`");
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return !empty(Db::query("SHOW TABLES LIKE '{$table}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return !empty(Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fixScheduleTime(): void
    {
        (new SysSchedule())->where([
            ['addon', '=', 'recycle_daheng_quote'],
            ['key', '=', 'recycle_daheng_quote_auto_sync'],
        ])->update([
            'time' => [
                'type' => 'min',
                'min' => 60,
            ],
            'update_time' => time(),
        ]);
    }
}
