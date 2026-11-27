<?php
declare(strict_types=1);

/**
 * 离线生成手动升级 SQL：不加载应用，不连接数据库，不写文件。
 * php addon/hsx_wecom/sql/build_manual_upgrade.php
 * php addon/hsx_wecom/sql/build_manual_upgrade.php --check
 */
namespace think\facade {
    final class Db
    {
        public static array $statements = [];

        public static function execute(string $sql): int
        {
            self::$statements[] = $sql;
            return 0;
        }

        public static function query(string $sql): array
        {
            // 模拟迁移已存在表、但还没有增量列和索引，收集所有结构声明。
            return str_starts_with($sql, 'SHOW TABLES') ? [['exists' => 1]] : [];
        }
    }
}

namespace {
    const WECOM_SCHEMA_PREFIX = '__wecom_prefix__';

    function config(string $key): string
    {
        if ($key !== 'database.connections.mysql.prefix') {
            throw new RuntimeException('不支持的配置读取：' . $key);
        }
        return WECOM_SCHEMA_PREFIX;
    }

    function wecomSqlLiteral(string $value): string
    {
        // MySQL 常规模式与 NO_BACKSLASH_ESCAPES 均可使用重复单引号。
        if (str_contains($value, '\\')) {
            throw new RuntimeException('结构定义含反斜杠，请先补充 SQL 转义测试');
        }
        return "'" . str_replace("'", "''", $value) . "'";
    }

    function wecomPreparedBlock(string $expression): string
    {
        return "SET @wecom_sql = {$expression};\n"
            . "PREPARE wecom_stmt FROM @wecom_sql;\nEXECUTE wecom_stmt;\nDEALLOCATE PREPARE wecom_stmt;\n\n";
    }

    function wecomDynamicDdl(string $ddl): string
    {
        $parts = explode(WECOM_SCHEMA_PREFIX, $ddl);
        if (count($parts) !== 2) throw new RuntimeException('每条 DDL 必须只有一个受控表前缀');
        return 'CONCAT(' . wecomSqlLiteral($parts[0]) . ', @wecom_prefix, ' . wecomSqlLiteral($parts[1]) . ')';
    }

    function wecomNormalizeDdl(string $sql): string
    {
        return (string)preg_replace('/\s+/', ' ', trim(rtrim($sql, ";\r\n ")));
    }

    function wecomManualUpgradeSql(): string
    {
        require_once dirname(__DIR__) . '/app/support/WecomSchema.php';
        \addon\hsx_wecom\app\support\WecomSchema::migrate();
        $creates = array_values(array_filter(\think\facade\Db::$statements, static fn(string $sql): bool => str_starts_with($sql, 'CREATE TABLE')));
        if (count($creates) !== 6) throw new RuntimeException('服务商表数量变化，请人工检查升级范围');

        $install = file_get_contents(__DIR__ . '/install.sql');
        if ($install === false) throw new RuntimeException('无法读取 install.sql');
        preg_match_all('/CREATE TABLE IF NOT EXISTS .*?;(?=\s*(?:CREATE TABLE|$))/s', $install, $matches);
        $normalize = static fn(string $sql): string => wecomNormalizeDdl(str_replace('{{prefix}}', WECOM_SCHEMA_PREFIX, $sql));
        if (array_map($normalize, $matches[0]) !== array_map($normalize, $creates)) {
            throw new RuntimeException('WecomSchema 与 install.sql 不一致，禁止生成可能漂移的 SQL');
        }

        $tables = [];
        foreach ($creates as $create) {
            if (!preg_match('/CREATE TABLE IF NOT EXISTS `' . WECOM_SCHEMA_PREFIX . '(\w+)` \((.*)\) ENGINE=/s', $create, $match)) {
                throw new RuntimeException('无法读取建表结构');
            }
            $columns = $indexes = [];
            foreach (explode("\n", trim($match[2])) as $line) {
                $line = rtrim(trim($line), ',');
                if (preg_match('/^`(\w+)` /', $line, $field)) $columns[$field[1]] = $line;
                elseif (preg_match('/^(?:UNIQUE )?KEY `(\w+)` /', $line, $field)) $indexes[$field[1]] = $line;
                elseif (str_starts_with($line, 'PRIMARY KEY')) $indexes['PRIMARY'] = $line;
                else throw new RuntimeException('无法识别的表结构：' . $line);
            }
            $tables[$match[1]] = compact('create', 'columns', 'indexes');
        }
        $maxPrefix = 64 - max(array_map('strlen', array_keys($tables)));
        $hash = hash('sha256', implode("\n", array_map('wecomNormalizeDdl', $creates)));
        $sql = "-- 企业微信服务商手动增量升级（2026-09-29）\n"
            . "-- 自动生成来源：app/support/WecomSchema.php；请勿手改生成内容。\n"
            . "-- schema-sha256: {$hash}\n"
            . "-- 先备份并在客户端选择目标数据库；仅修改下面一处前缀。\n"
            . "-- 不删除数据、不覆盖配置、不自动给角色授权；DDL 不支持事务整体回滚。\n"
            . "-- 任意语句报错立即停止，不使用 mysql --force。执行后再运行插件的只读校验。\n"
            . "SET @wecom_prefix = 'ns_';\n"
            . "SET @wecom_schema = DATABASE();\n"
            . "SET @wecom_ready = (@wecom_schema IS NOT NULL AND @wecom_prefix IS NOT NULL AND @wecom_prefix REGEXP '^[A-Za-z0-9_]*$' AND CHAR_LENGTH(@wecom_prefix) <= {$maxPrefix});\n"
            . "SELECT @wecom_schema AS target_database, @wecom_prefix AS table_prefix, IF(@wecom_ready, 'READY - confirm database and prefix', 'STOP - select database and correct prefix') AS preflight;\n\n";
        $noChange = wecomSqlLiteral("SELECT 'SKIPPED (already exists or preflight not ready)' AS wecom_step");
        foreach ($tables as $name => $table) {
            $sql .= "-- {$name}\n";
            $sql .= wecomPreparedBlock('IF(@wecom_ready, ' . wecomDynamicDdl($table['create']) . ', ' . $noChange . ')');
            foreach ($table['columns'] as $column => $definition) {
                $condition = '@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, '
                    . wecomSqlLiteral($name) . ') AND COLUMN_NAME = ' . wecomSqlLiteral($column) . ')';
                $ddl = 'ALTER TABLE `' . WECOM_SCHEMA_PREFIX . $name . '` ADD COLUMN ' . $definition;
                $sql .= "-- 缺失列 {$name}.{$column}\n" . wecomPreparedBlock('IF(' . $condition . ', ' . wecomDynamicDdl($ddl) . ', ' . $noChange . ')');
            }
            foreach ($table['indexes'] as $index => $definition) {
                $condition = '@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, '
                    . wecomSqlLiteral($name) . ') AND INDEX_NAME = ' . wecomSqlLiteral($index) . ')';
                $ddl = 'ALTER TABLE `' . WECOM_SCHEMA_PREFIX . $name . '` ADD ' . $definition;
                $sql .= "-- 缺失索引 {$name}.{$index}\n" . wecomPreparedBlock('IF(' . $condition . ', ' . wecomDynamicDdl($ddl) . ', ' . $noChange . ')');
            }
        }
        $sql .= "SELECT @wecom_schema AS target_database, @wecom_prefix AS table_prefix, IF(@wecom_ready, 'SQL ended; run manual_upgrade.php --check and inspect any earlier errors', 'NOT APPLIED: preflight failed') AS next_action;\n";
        return $sql;
    }

    if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
        try {
            $sql = wecomManualUpgradeSql();
            if (in_array('--check', $argv, true)) {
                $file = __DIR__ . '/upgrade_20260929_provider_manual.sql';
                if (!is_file($file) || file_get_contents($file) !== $sql) throw new RuntimeException('交付 SQL 与当前结构不一致，请重新生成');
                echo "PASS: 6 tables; WecomSchema = install.sql = manual upgrade SQL; no database connection.\n";
            } else {
                echo $sql;
            }
        } catch (Throwable $e) {
            fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
            exit(1);
        }
    }
}
