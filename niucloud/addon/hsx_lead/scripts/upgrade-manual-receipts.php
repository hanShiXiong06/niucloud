<?php
declare(strict_types=1);
namespace addon\hsx_lead\scripts;

use PDO;
use RuntimeException;
use think\facade\Db;
use Throwable;

/** Explicit additive DDL. Never run from an API, login, queue or plugin lifecycle. */
final class ManualReceiptsUpgrade
{
    private const TABLES = ['hsx_lead_manual_receipt'];
    public function __construct(private string $backend) {}

    /** Reads only metadata and the installed-addon marker; no cache/file/advisory lock writes. */
    public function check(): array { return $this->report($this->inspect()); }

    public function apply(): array
    {
        $connection = Db::connect();
        $scope = $connection->query('SELECT DATABASE() AS db', [], true)[0]['db'] ?? '';
        $pdo = $connection->getPdo();
        if (!$pdo instanceof PDO || $pdo->inTransaction()) throw new RuntimeException('人工收款表DDL必须在独立连接执行，拒绝已有业务事务');
        $plan = $this->inspect();
        $prefix = $this->prefix();
        $key = 'lead_manual_receipt:' . substr(hash('sha256', $scope . ':' . $prefix), 0, 40);
        $locked = $connection->query('SELECT GET_LOCK(?,0) AS acquired', [$key], true);
        if ((int)($locked[0]['acquired'] ?? 0) !== 1) throw new RuntimeException('本数据库前缀的人工收款结构升级正在执行，请勿重复运行');
        $oldWait = null;
        try {
            if ($pdo->inTransaction()) throw new RuntimeException('人工收款表DDL不能混入业务事务');
            $plan = $this->inspect();
            if (!$plan['missing']) return $this->report($plan) + ['applied' => false, 'created_tables' => [], 'backup' => ''];
            $path = $this->backend . '/runtime/hsx_lead_upgrade/manual-receipts-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
            $this->privateDirectory($path);
            $backup = $path . '/schema-before.json';
            // Column/index metadata only: never export receipt rows, phone numbers, secrets,
            // payment facts, AUTO_INCREMENT counters or connection configuration.
            $this->privateJson($backup, ['schema' => 'hsx_lead.manual_receipts.v1', 'sql_sha256' => hash_file('sha256', dirname(__DIR__) . '/sql/manual-receipts.sql'),
                'missing_tables' => $plan['missing'], 'existing_schema' => $plan['metadata'], 'business_row_writes' => false]);
            $oldWait = (int)($connection->query('SELECT @@SESSION.lock_wait_timeout AS seconds', [], true)[0]['seconds'] ?? 0);
            $connection->execute('SET SESSION lock_wait_timeout=5');
            $created = [];
            foreach ($plan['missing'] as $table) {
                if ($pdo->inTransaction()) throw new RuntimeException('检测到外层事务，未继续执行DDL');
                $connection->execute(str_replace('{{prefix}}', $prefix, $plan['definitions'][$table]['sql']));
                $created[] = $table;
                // MySQL DDL commits independently. Verify each table; do not claim rollback.
                $this->inspect();
            }
            $after = $this->inspect();
            if ($after['missing']) throw new RuntimeException('人工收款表创建结果未完整读回，请重新--check核对');
            $result = $this->report($after) + ['applied' => true, 'created_tables' => $created, 'backup' => $backup];
            $this->privateJson($path . '/result.json', $result);
            return $result;
        } finally {
            if ($oldWait !== null) try { $connection->execute('SET SESSION lock_wait_timeout=' . $oldWait); } catch (Throwable $ignored) {}
            try { $connection->query('SELECT RELEASE_LOCK(?) AS released', [$key], true); } catch (Throwable $ignored) {}
        }
    }

    private function prefix(): string
    {
        $config = Db::getConfig();
        $prefix = (string)($config['connections'][$config['default']]['prefix'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix)) throw new RuntimeException('数据库表前缀无效');
        foreach (self::TABLES as $table) if (strlen($prefix . $table) > 64) throw new RuntimeException('数据库表名前缀过长');
        return $prefix;
    }

    private function definitions(): array
    {
        $sql = file_get_contents(dirname(__DIR__) . '/sql/manual-receipts.sql');
        if (!is_string($sql)) throw new RuntimeException('缺少人工收款表规范SQL，请完整上传插件');
        $clean = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);
        $pattern = '/CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}([a-z_]+)`\s*\((.*?)\)\s*ENGINE\s*=\s*InnoDB\s+DEFAULT\s+CHARSET\s*=\s*utf8mb4\s+COMMENT\s*=\s*\'[^\']*\'\s*;/si';
        preg_match_all($pattern, $clean, $matches, PREG_SET_ORDER);
        if (count($matches) !== 1 || trim((string)preg_replace($pattern, '', $clean)) !== '') throw new RuntimeException('人工收款规范SQL必须仅含一条指定CREATE语句，不接受其他操作');
        $result = [];
        foreach ($matches as $match) {
            $name = $match[1];
            if (!in_array($name, self::TABLES, true) || isset($result[$name])) throw new RuntimeException('人工收款规范SQL表名重复或超出本次范围');
            $columns = []; $indexes = [];
            foreach (preg_split('/\R/', trim($match[2])) as $line) {
                $line = rtrim(trim($line), ',');
                if (preg_match('/^`([a-z_]+)`\s+((?:bigint|int|tinyint)\s+unsigned|decimal\(\d+,\d+\)|(?:var)?char\(\d+\)|longtext)\s+(.*)$/i', $line, $field)) {
                    $rest = $field[3]; $default = null;
                    if (preg_match('/\bDEFAULT\s+(\'[^\']*\'|NULL|[0-9]+)/i', $rest, $found)) $default = $this->defaultValue($found[1]);
                    $unrecognized = preg_replace('/\bDEFAULT\s+(\'[^\']*\'|NULL|[0-9]+)|\bNOT NULL\b|\bNULL\b|\bAUTO_INCREMENT\b/i', '', $rest);
                    if (trim((string)$unrecognized) !== '' || isset($columns[$field[1]])) throw new RuntimeException('人工收款规范SQL字段声明异常');
                    $columns[$field[1]] = ['type' => strtolower($field[2]), 'nullable' => stripos($rest, 'NOT NULL') === false,
                        'default' => $default, 'auto_increment' => stripos($rest, 'AUTO_INCREMENT') !== false];
                } elseif (preg_match('/^(PRIMARY KEY|UNIQUE KEY `([a-z_]+)`|KEY `([a-z_]+)`)\s*\((`[a-z_]+`(?:\s*,\s*`[a-z_]+`)*)\)$/i', $line, $index)) {
                    $key = $index[1] === 'PRIMARY KEY' ? 'PRIMARY' : ($index[2] ?: $index[3]);
                    preg_match_all('/`([a-z_]+)`/', $index[4], $parts);
                    $indexes[$key] = ['columns' => $parts[1], 'unique' => stripos($index[1], 'KEY `') !== 0];
                } else throw new RuntimeException('人工收款规范SQL含不支持的声明');
            }
            if (!$columns || !isset($indexes['PRIMARY'])) throw new RuntimeException('人工收款规范SQL字段或主键声明缺失');
            $result[$name] = ['sql' => $match[0], 'columns' => $columns, 'indexes' => $indexes];
        }
        return $result;
    }

    private function inspect(): array
    {
        if (!is_dir($this->backend) || realpath($this->backend) !== $this->backend) throw new RuntimeException('PHP根目录必须是实际绝对路径');
        if (!Db::name('addon')->where('key', 'hsx_lead')->find()) throw new RuntimeException('插件尚未安装，本命令不执行插件安装');
        $prefix = $this->prefix(); $definitions = $this->definitions(); $metadata = []; $missing = [];
        $connection = Db::connect();
        $version = (string)($connection->query('SELECT VERSION() AS version', [], true)[0]['version'] ?? '');
        $quotedDefaults = stripos($version, 'MariaDB') !== false;
        foreach ($definitions as $name => $expected) {
            $table = $prefix . $name;
            $info = $connection->query('SELECT TABLE_NAME,ENGINE,TABLE_TYPE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table], true);
            if (!$info) { $missing[] = $name; continue; }
            if (count($info) !== 1 || $info[0]['TABLE_TYPE'] !== 'BASE TABLE' || strtoupper((string)$info[0]['ENGINE']) !== 'INNODB'
                || strpos(strtolower((string)$info[0]['TABLE_COLLATION']), 'utf8mb4_') !== 0) throw new RuntimeException('人工收款同名表存储结构不符，拒绝覆盖：' . $name);
            $fields = $connection->query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$table], true);
            $actual = [];
            foreach ($fields as $field) {
                if (trim((string)str_ireplace('auto_increment', '', $field['EXTRA'])) !== '') throw new RuntimeException('人工收款字段含非规范自动生成规则：' . $name);
                $actual[$field['COLUMN_NAME']] = ['type' => preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\([0-9]+\)/i', '$1', strtolower($field['COLUMN_TYPE'])),
                    'nullable' => $field['IS_NULLABLE'] === 'YES', 'default' => $this->defaultValue($field['COLUMN_DEFAULT'], true, $quotedDefaults), 'auto_increment' => stripos($field['EXTRA'], 'auto_increment') !== false];
            }
            if ($actual !== $expected['columns']) throw new RuntimeException('人工收款同名表字段异常；不会补字段或覆盖已有内容：' . $name);
            foreach ($fields as $field) if ($field['CHARACTER_SET_NAME'] !== null && $field['CHARACTER_SET_NAME'] !== 'utf8mb4') throw new RuntimeException('人工收款表文字编码异常：' . $name);
            $parts = $connection->query('SHOW INDEX FROM `' . $table . '`', [], true); $indexes = [];
            foreach ($parts as $part) {
                if ($part['Sub_part'] !== null || strtoupper((string)$part['Index_type']) !== 'BTREE' || strtoupper((string)($part['Visible'] ?? 'YES')) !== 'YES' || strtoupper((string)($part['Ignored'] ?? 'NO')) !== 'NO') throw new RuntimeException('人工收款索引不可完整使用：' . $name);
                $indexes[$part['Key_name']]['columns'][(int)$part['Seq_in_index']] = $part['Column_name'];
                $indexes[$part['Key_name']]['unique'] = (int)$part['Non_unique'] === 0;
            }
            foreach ($indexes as &$index) { ksort($index['columns']); $index['columns'] = array_values($index['columns']); } unset($index);
            foreach ($expected['indexes'] as $key => $index) if (($indexes[$key] ?? null) !== $index) throw new RuntimeException('人工收款同名表索引异常；不会盲目补建：' . $name . '.' . $key);
            foreach ($indexes as $key => $index) if ($index['unique'] && !isset($expected['indexes'][$key])) throw new RuntimeException('人工收款表存在非规范唯一约束，需人工核对：' . $name);
            $metadata[$name] = ['table' => $info[0], 'columns' => $fields, 'indexes' => $indexes];
        }
        return ['missing' => $missing, 'metadata' => $metadata, 'definitions' => $definitions];
    }

    private function defaultValue($value, bool $metadata = false, bool $quotedDefaults = false): ?string
    {
        if ($value === null) return null;
        if ($metadata && !$quotedDefaults) return (string)$value;
        if (strtoupper((string)$value) === 'NULL') return null;
        $value = (string)$value;
        if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") $value = substr($value, 1, -1);
        return $value;
    }

    private function report(array $plan): array
    {
        return ['tables' => self::TABLES, 'pending_statements' => count($plan['missing']), 'missing_tables' => $plan['missing'],
            'business_row_writes' => false, 'backfill_receipts' => false, 'changes_existing_fields_or_indexes' => false];
    }

    private function privateDirectory(string $path): void
    {
        for ($parent = $path; $parent !== dirname($parent); $parent = dirname($parent)) if (is_link($parent)) throw new RuntimeException('私有备份路径含符号链接');
        if (!mkdir($path, 0700, true) || realpath($path) !== $path || !chmod($path, 0700)) throw new RuntimeException('无法创建私有结构备份，未执行DDL');
    }

    private function privateJson(string $path, array $value): void
    {
        $handle = fopen($path, 'x');
        if (!$handle) throw new RuntimeException('无法创建结构备份文件，未执行后续DDL');
        try {
            if (!chmod($path, 0600)) throw new RuntimeException('无法保护结构备份文件');
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('结构备份不完整，未执行后续DDL');
        } finally { fclose($handle); }
    }
}

if (!defined('HSX_LEAD_MANUAL_RECEIPTS_LIBRARY_ONLY')) {
    if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000) exit(1);
    $mode = $argv[1] ?? '';
    if (count($argv) !== 2 || !in_array($mode, ['--check', '--apply'], true)) { fwrite(STDERR,"php addon/hsx_lead/scripts/upgrade-manual-receipts.php --check|--apply\n"); exit(2); }
    define('HSX_LEAD_UPGRADE_LIBRARY_ONLY', true); require __DIR__ . '/upgrade.php';
    try {
        $backend = dirname(__DIR__, 3); bootstrap($backend); $command = new ManualReceiptsUpgrade($backend);
        echo json_encode($mode === '--check' ? $command->check() : $command->apply(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
    } catch (Throwable $error) {
        fwrite(STDERR, '[未确认完成] ' . (get_class($error) === RuntimeException::class ? $error->getMessage() : '人工收款结构检查或创建失败，已隐藏连接信息') . "\nDDL逐表提交，不保证事务回滚；修正后重新--check，不卸载重装。\n"); exit(1);
    }
}
