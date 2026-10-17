<?php
declare(strict_types=1);

namespace addon\phone_shop\scripts;

use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;
use think\facade\Db;

// 仅供服务器终端执行，不提供可被公网调用的升级入口。
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

/** 仅修复同行申请表；DDL 复用插件 install.sql，不执行全插件升级或业务数据迁移。 */
class ForwardApplicationUpgrade extends SchemaSyncService
{
    public function table(): string
    {
        // 这里接收的是无前缀的固定表名，不能用 starts_with 猜测它是否已有前缀。
        $table = $this->prefix . 'phone_shop_forward_application';
        if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $table)) {
            throw new \RuntimeException('数据库表前缀不符合本脚本支持的格式，未执行升级');
        }
        return $table;
    }

    /** 所有检查通过后才返回可执行计划；检查失败不会执行部分升级。 */
    public function plan(): array
    {
        $table = $this->table();
        $schema = $this->parseInstallSql()[$table] ?? [];
        $create = $this->rawCreateStatements()[$table] ?? '';
        if ($create === '' || count($schema['cols'] ?? []) < 17
            || !isset($schema['cols']['application_id'], $schema['idx']['uk_site_form_record'])) {
            throw new \RuntimeException('插件 sql/install.sql 缺少完整的同行申请表定义，请与当前 phone_shop 后端一起上传；不要执行整份 install.sql');
        }

        // 精确查表，避免 SHOW TABLES LIKE 将前缀中的下划线当作通配符。
        $tables = Db::query('SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table], true);
        if (!$tables) {
            return ['table' => $table, 'changes' => ['创建缺失的同行申请表'], 'sql' => [$create]];
        }
        if ($tables[0]['TABLE_TYPE'] !== 'BASE TABLE') {
            throw new \RuntimeException('同行申请表名被视图占用，未执行升级');
        }

        $columns = array_column(Db::query("SHOW COLUMNS FROM `{$table}`", [], true), null, 'Field');
        $indexes = [];
        foreach (Db::query("SHOW INDEX FROM `{$table}`", [], true) as $row) {
            $indexes[$row['Key_name']]['columns'][(int)$row['Seq_in_index']] = $row['Column_name'];
            $indexes[$row['Key_name']]['unique'] = (int)$row['Non_unique'] === 0;
            $indexes[$row['Key_name']]['partial'] = ($indexes[$row['Key_name']]['partial'] ?? false) || $row['Sub_part'] !== null;
        }
        foreach ($indexes as &$index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
        }
        unset($index);
        if (!isset($columns['application_id'])
            || stripos($columns['application_id']['Extra'], 'auto_increment') === false
            || ($indexes['PRIMARY']['columns'] ?? []) !== ['application_id']) {
            throw new \RuntimeException('已有申请表的 application_id 自增主键异常；为保留申请编号，脚本不会重建或覆盖，请单独核对');
        }

        $missing = array_diff_key($schema['cols'], $columns);
        // 不为已有申请猜测站点、会员或表单记录归属。
        if (array_intersect(['site_id', 'member_id', 'form_record_id'], array_keys($missing))
            && Db::query("SELECT 1 FROM `{$table}` LIMIT 1", [], true)) {
            throw new \RuntimeException('已有申请记录缺少站点、会员或表单关联字段，不能自动填 0；未执行升级，请先核对记录归属');
        }
        $clauses = [];
        $changes = [];
        foreach ($missing as $name => $definition) {
            $clauses[] = "ADD COLUMN `{$name}` {$definition}";
            $changes[] = '补字段：' . $name;
        }
        foreach ($schema['idx'] as $name => $index) {
            preg_match_all('/`([^`]+)`/', $index['cols'], $matches);
            $indexColumns = $matches[1];
            if (isset($indexes[$name])) {
                if ($indexes[$name]['columns'] !== $indexColumns
                    || $indexes[$name]['unique'] !== $index['unique'] || $indexes[$name]['partial']) {
                    throw new \RuntimeException("已有索引 {$name} 与插件定义不同，未执行升级；不会自动删除重建索引");
                }
                continue;
            }
            if ($index['unique'] && !array_diff($indexColumns, array_keys($columns))) {
                $duplicates = Db::query("SELECT 1 FROM `{$table}` GROUP BY {$index['cols']} HAVING COUNT(*) > 1 LIMIT 1", [], true);
                if ($duplicates) {
                    throw new \RuntimeException("存在重复的站点/表单申请关联，不能添加唯一索引 {$name}；未删除或合并任何记录，请先核对");
                }
            }
            $clauses[] = 'ADD ' . ($index['unique'] ? 'UNIQUE ' : '') . "INDEX `{$name}` ({$index['cols']})";
            $changes[] = '补索引：' . $name;
        }
        return ['table' => $table, 'changes' => $changes,
            'sql' => $clauses ? ["ALTER TABLE `{$table}` " . implode(', ', $clauses)] : []];
    }

    public function apply(): array
    {
        $database = (string)Db::connect()->getConfig('database');
        $lock = 'phone_forward_upgrade_' . substr(hash('sha256', $database . '.' . $this->table()), 0, 32);
        $result = Db::query('SELECT GET_LOCK(?, 5) AS acquired', [$lock], true);
        if ((int)($result[0]['acquired'] ?? 0) !== 1) {
            throw new \RuntimeException('另一个同行申请升级正在执行，请稍后重试');
        }
        try {
            $plan = $this->plan();
            foreach ($plan['sql'] as $sql) Db::execute($sql);
            if ($this->plan()['sql']) {
                throw new \RuntimeException('升级后检查未通过，请保留输出继续排查');
            }
            return $plan;
        } finally {
            Db::query('SELECT RELEASE_LOCK(?)', [$lock], true);
        }
    }
}

// 允许隔离测试加载类；只有直接执行此文件时才读取项目配置。
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;

$options = array_slice($argv, 1);
if (array_diff($options, ['--check', '--apply', '--help']) || (in_array('--check', $options, true) && in_array('--apply', $options, true))) {
    fwrite(STDERR, "参数错误。用 --check 检查，或用 --apply 执行；不能同时使用。\n");
    exit(1);
}
if (in_array('--help', $options, true)) {
    echo "同行申请表专项升级（使用当前项目数据库配置）\n";
    echo "php addon/phone_shop/scripts/upgrade_forward_application.php --check  仅检查（默认）\n";
    echo "php addon/phone_shop/scripts/upgrade_forward_application.php --apply  执行补表/补字段/补索引\n";
    echo "先备份数据库、暂停同行申请提交。不会清空数据、审批申请、修改等级或升级其他表。\n";
    exit(0);
}

try {
    $app = new \think\App(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);
    $app->initialize();
    $upgrade = new ForwardApplicationUpgrade();
    echo '数据库：' . Db::connect()->getConfig('database') . "\n目标表：" . $upgrade->table() . "\n";
    $apply = in_array('--apply', $options, true);
    $plan = $apply ? $upgrade->apply() : $upgrade->plan();
    foreach ($plan['changes'] as $change) echo ($apply ? '[已执行] ' : '[待执行] ') . $change . "\n";
    if (!$apply && $plan['sql']) {
        echo "检查完成，尚未修改数据库。确认目标数据库并备份后，加 --apply 执行。\n";
        exit(2);
    }
    echo "[通过] 同行申请表及所需字段、索引已齐全。未改动会员等级、申请审核状态或其他业务表。\n";
    echo "请重新打开同行申请页面；若仍报错，请提供完整异常。表结构通过不代表业务配置或整条申请流程已验证。\n";
} catch (\Throwable $e) {
    fwrite(STDERR, '[失败] ' . $e->getMessage() . "\n未自动清理任何业务记录；请保留此输出排查。\n");
    exit(1);
}
