<?php

namespace addon\hsx_device_asset;

use think\facade\Db;

/**
 * 设备资产中台插件
 */
class Addon
{
    public function install()
    {
        $this->installTables();
        $this->migrateColumns();
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function upgrade()
    {
        // install.sql 用 CREATE TABLE IF NOT EXISTS：新表(库位责任表)会被建出，但已存在的表不会加新列，
        // 因此新增列需要在此幂等补齐（与 hsx_recycle 升级迁移同一套做法）。
        $this->installTables();
        $this->migrateColumns();
        return true;
    }

    private function installTables(): void
    {
        $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';
        if (!is_file($sqlFile)) {
            return;
        }

        $prefix = config('database.connections.mysql.prefix');
        $sql = str_replace('{{prefix}}', $prefix, (string)file_get_contents($sqlFile));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            Db::execute($statement);
        }
    }

    /**
     * 幂等补齐增量列：资产档案表的目标仓库/库位（来自回收定价），用于库位责任过滤。
     * 已存在的列会被跳过，不影响全新安装。
     */
    private function migrateColumns(): void
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $table = $prefix . 'device_asset_item';

        $columns = [
            'warehouse_id' => "ADD COLUMN `warehouse_id` int NOT NULL DEFAULT '0' COMMENT '目标仓库ID(来自回收定价)' AFTER `category_id`",
            'warehouse_name' => "ADD COLUMN `warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '目标仓库名称快照' AFTER `warehouse_id`",
            'location_id' => "ADD COLUMN `location_id` int NOT NULL DEFAULT '0' COMMENT '目标库位ID(责任分配/过滤依据)' AFTER `warehouse_name`",
            'location_name' => "ADD COLUMN `location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '目标库位名称快照' AFTER `location_id`",
        ];
        foreach ($columns as $column => $alter) {
            if (!$this->hasColumn($table, $column)) {
                Db::execute("ALTER TABLE `{$table}` {$alter}");
            }
        }

        // 库位索引（便于按库位过滤）
        if (!$this->hasIndex($table, 'idx_site_location')) {
            Db::execute("ALTER TABLE `{$table}` ADD KEY `idx_site_location` (`site_id`,`location_id`)");
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return !empty(Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"));
    }

    private function hasIndex(string $table, string $index): bool
    {
        return !empty(Db::query("SHOW INDEX FROM `{$table}` WHERE `Key_name` = '{$index}'"));
    }
}
