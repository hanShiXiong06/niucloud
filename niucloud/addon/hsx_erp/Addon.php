<?php
declare(strict_types=1);

namespace addon\hsx_erp;

use think\facade\Db;

/**
 * 二手机 ERP 插件
 */
class Addon
{
    private const BACKUP_BATCH_SIZE = 500;

    public function install()
    {
        $this->installTables();
        return true;
    }

    public function uninstall()
    {
        $tables = $this->getErpTables();
        if (empty($tables)) {
            return true;
        }

        $this->backupTables($tables);
        $this->dropTables($tables);
        return true;
    }

    public function upgrade()
    {
        $this->installTables();
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
     * 卸载前将当前数据库中的全部 ERP 表导出为一个可独立恢复的 SQL 文件。
     * 备份失败会抛出异常并中止卸载，避免无备份删除财务数据。
     */
    private function backupTables(array $tables): string
    {
        $backupDir = runtime_path() . 'adminapi' . DIRECTORY_SEPARATOR . 'backup'
            . DIRECTORY_SEPARATOR . 'hsx_erp' . DIRECTORY_SEPARATOR . 'uninstall';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('ERP卸载备份目录创建失败：' . $backupDir);
        }

        $filename = 'hsx_erp_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.sql';
        $targetFile = $backupDir . DIRECTORY_SEPARATOR . $filename;
        $temporaryFile = $targetFile . '.tmp';
        $handle = fopen($temporaryFile, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('ERP卸载备份文件创建失败：' . $temporaryFile);
        }

        try {
            $this->writeSql($handle, "-- HSX ERP uninstall backup\n");
            $this->writeSql($handle, '-- Generated at: ' . date('Y-m-d H:i:s') . "\n");
            $this->writeSql($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($tables as $table) {
                $this->backupTable($handle, $table);
            }

            $this->writeSql($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            if (!fflush($handle)) {
                throw new \RuntimeException('ERP卸载备份文件刷新失败');
            }
            fclose($handle);
            $handle = null;

            if (!rename($temporaryFile, $targetFile)) {
                throw new \RuntimeException('ERP卸载备份文件落盘失败：' . $targetFile);
            }
            return $targetFile;
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
            throw $e;
        }
    }

    private function backupTable($handle, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $createResult = Db::query('SHOW CREATE TABLE ' . $quotedTable);
        $createSql = (string)($createResult[0]['Create Table'] ?? '');
        if ($createSql === '') {
            throw new \RuntimeException('无法读取 ERP 表结构：' . $table);
        }

        $this->writeSql($handle, '-- Table: ' . $table . "\n");
        $this->writeSql($handle, 'DROP TABLE IF EXISTS ' . $quotedTable . ";\n");
        $this->writeSql($handle, $createSql . ";\n\n");

        $offset = 0;
        while (true) {
            $rows = Db::table($table)->limit($offset, self::BACKUP_BATCH_SIZE)->select()->toArray();
            if (empty($rows)) {
                break;
            }
            $columns = array_keys($rows[0]);
            $columnSql = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
            $valueRows = [];
            foreach ($rows as $row) {
                $valueRows[] = '(' . implode(', ', array_map([$this, 'quoteValue'], array_values($row))) . ')';
            }
            $this->writeSql(
                $handle,
                'INSERT INTO ' . $quotedTable . ' (' . $columnSql . ") VALUES\n"
                . implode(",\n", $valueRows) . ";\n"
            );
            $offset += count($rows);
        }
        $this->writeSql($handle, "\n");
    }

    private function getErpTables(): array
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $erpPrefix = $prefix . 'erp_';
        $rows = Db::query('SHOW TABLES');
        $tables = array_values(array_filter(array_map(
            static fn(array $row): string => (string)current($row),
            $rows
        ), static fn(string $table): bool => strpos($table, $erpPrefix) === 0));
        sort($tables);
        return $tables;
    }

    private function dropTables(array $tables): void
    {
        Db::execute('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (array_reverse($tables) as $table) {
                Db::execute('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
            }
        } finally {
            Db::execute('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return Db::getPdo()->quote((string)$value);
    }

    private function writeSql($handle, string $sql): void
    {
        $length = strlen($sql);
        $written = 0;
        while ($written < $length) {
            $result = fwrite($handle, substr($sql, $written));
            if ($result === false || $result === 0) {
                throw new \RuntimeException('ERP卸载备份文件写入失败');
            }
            $written += $result;
        }
    }
}
