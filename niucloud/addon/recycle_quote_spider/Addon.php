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
        $this->fixScheduleTime();
        return true;
    }

    private function installSchema(): void
    {
        $this->executeSqlFile(__DIR__ . '/sql/schema.sql');
    }

    private function uninstallSchema(): void
    {
        $this->executeStatements([
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
