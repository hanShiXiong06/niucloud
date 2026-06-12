<?php
declare(strict_types=1);

namespace addon\hsx_finance;

use think\facade\Db;

/**
 * 二手机财务插件
 *
 * 职责边界(架构红线):
 *  - 只负责: 往来单位的应付/应收事实归集、结算(现金/折账/混合)、净额冲抵(折账)。
 *  - 不负责: 进销存事实/成本利润(归 ERP)、业务流转(归回收/中台)。
 *  - 解耦: 仅消费 finance.payable.created.v1 / finance.receivable.created.v1 事件落库;
 *          结算后发 finance.settlement.completed.v1 让业务/ERP 自行更新展示。
 *          绝不跨插件读写表。
 */
class Addon
{
    public function install()
    {
        $this->installTables();
        return true;
    }

    public function uninstall()
    {
        $tables = $this->getFinanceTables();
        if (empty($tables)) {
            return true;
        }
        Db::execute('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (array_reverse($tables) as $table) {
                Db::execute('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
            }
        } finally {
            Db::execute('SET FOREIGN_KEY_CHECKS=1');
        }
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

    private function getFinanceTables(): array
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $financePrefix = $prefix . 'finance_';
        $rows = Db::query('SHOW TABLES');
        $tables = array_values(array_filter(array_map(
            static fn(array $row): string => (string)current($row),
            $rows
        ), static fn(string $table): bool => strpos($table, $financePrefix) === 0));
        sort($tables);
        return $tables;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
