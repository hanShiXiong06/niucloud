<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider;

use app\model\sys\SysSchedule;
use think\facade\Db;

/**
 * 回收报价增强插件
 */
class Addon
{
    public function install()
    {
        $this->installSchema();
        (new \app\service\core\schedule\CoreScheduleInstallService())->installAddonSchedule('recycle_quote_spider');
        $this->fixScheduleTime();
        return true;
    }

    public function uninstall()
    {
        (new \app\service\core\schedule\CoreScheduleInstallService())->uninstallAddonSchedule('recycle_quote_spider');
        $this->uninstallSchema();
        return true;
    }

    public function upgrade()
    {
        $this->migrateColumns();
        $this->fixScheduleTime();
        return true;
    }

    /**
     * 幂等地为已安装的库补字段（MySQL 5.7 无 ADD COLUMN IF NOT EXISTS）
     */
    private function migrateColumns(): void
    {
        $this->addColumnIfMissing(
            'recycle_quote_spider_item',
            'view_count',
            "ADD COLUMN `view_count` int NOT NULL DEFAULT 0 COMMENT '浏览量' AFTER `is_hot`"
        );
        $this->addColumnIfMissing(
            'recycle_quote_spider_row',
            'is_hot',
            "ADD COLUMN `is_hot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '本地热门状态' AFTER `is_show`"
        );
        // 历史价格快照表（已装库补建）
        $this->executeStatements([
            "CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_quote_spider_price_history` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `site_id` int NOT NULL DEFAULT '0',
                `source_id` int NOT NULL DEFAULT '0',
                `item_id` int NOT NULL DEFAULT '0',
                `row_id` int NOT NULL DEFAULT '0',
                `model_name` varchar(180) NOT NULL DEFAULT '',
                `columns` json DEFAULT NULL,
                `final_prices` json DEFAULT NULL,
                `record_date` date NOT NULL,
                `create_at` int NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_site_row_date` (`site_id`,`row_id`,`record_date`),
                KEY `idx_item_date` (`site_id`,`item_id`,`record_date`),
                KEY `idx_row_date` (`site_id`,`row_id`,`record_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收报价历史价格快照'",
        ]);
    }

    private function addColumnIfMissing(string $table, string $column, string $alter): void
    {
        $prefix = config('database.connections.mysql.prefix');
        $fullTable = $prefix . $table;
        try {
            $exists = Db::query(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$fullTable, $column]
            );
            if ((int)($exists[0]['c'] ?? 0) > 0) {
                return;
            }
            Db::execute("ALTER TABLE `{$fullTable}` {$alter}");
        } catch (\Throwable $e) {
            // 升级容错：字段已存在或权限问题时不阻断升级流程
        }
    }

    private function installSchema(): void
    {
        $this->executeSqlFile(__DIR__ . '/sql/schema.sql');
    }

    private function uninstallSchema(): void
    {
        $this->executeStatements([
            'DROP TABLE IF EXISTS `{{prefix}}recycle_quote_spider_price_history`',
            'DROP TABLE IF EXISTS `{{prefix}}recycle_quote_spider_import_task`',
            'DROP TABLE IF EXISTS `{{prefix}}recycle_quote_spider_sync_log`',
            'DROP TABLE IF EXISTS `{{prefix}}recycle_quote_spider_row`',
            'DROP TABLE IF EXISTS `{{prefix}}recycle_quote_spider_item`',
            'DROP TABLE IF EXISTS `{{prefix}}recycle_quote_spider_category`',
            'DROP TABLE IF EXISTS `{{prefix}}recycle_quote_spider_source`',
        ]);
    }

    private function executeSqlFile(string $sqlFile): void
    {
        if (!is_file($sqlFile)) {
            return;
        }

        $statements = parse_sql(file_get_contents($sqlFile));
        $this->executeStatements($statements);
    }

    private function executeStatements(array $statements): void
    {
        $prefix = config('database.connections.mysql.prefix');
        $defaultCollation = $this->getDefaultCollation();

        foreach ($statements as $statement) {
            $statement = trim((string)$statement);
            if ($statement === '') {
                continue;
            }

            $statement = str_ireplace('{{prefix}}', $prefix, $statement);
            $statement = preg_replace_callback(
                '/\bCOLLATE\s*(=)?\s*[`"\']?([a-zA-Z0-9_]+)[`"\']?/i',
                function () use ($defaultCollation) {
                    return 'COLLATE ' . $defaultCollation;
                },
                $statement
            );
            Db::execute($statement);
        }
    }

    private function getDefaultCollation(): string
    {
        try {
            return Db::query("SHOW VARIABLES LIKE 'collation_database'")[0]['Value'] ?? 'utf8mb4_general_ci';
        } catch (\Throwable $e) {
            return 'utf8mb4_general_ci';
        }
    }

    private function fixScheduleTime(): void
    {
        (new SysSchedule())->where([
            ['addon', '=', 'recycle_quote_spider'],
            ['key', '=', 'recycle_quote_spider_auto_sync'],
        ])->update([
            'time' => [
                'type' => 'min',
                'min' => 60,
            ],
            'update_time' => time(),
        ]);
    }
}
