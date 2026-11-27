<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\virtualpay;

use PDO;
use RuntimeException;
use think\facade\Db;

/** Explicit additive DDL; never invoked by an API, callback, worker or page request. */
final class VirtualPaySchema
{
    public const TABLES = ['hsx_lead_virtual_order', 'hsx_lead_virtual_refund'];
    public function __construct(private string $backend)
    {
    }
    public function check(): array
    {
        $plan = $this->inspect();
        return [
            'tables' => self::TABLES,
            'missing_tables' => $plan['missing'],
            'pending_statements' => count($plan['missing']),
            'business_row_writes' => false,
            'changes_existing_fields_or_indexes' => false
        ];
    }
    public function apply(): array
    {
        $connection = Db::connect();
        $connection->query('SELECT DATABASE() AS name', [], true);
        $pdo = $connection->getPdo();
        if (!$pdo instanceof PDO || $pdo->inTransaction())throw new RuntimeException('虚拟支付结构升级需独立连接，不允许外层业务事务');
        $scope = (string) $connection->getConfig('database').':'.$this->prefix();
        $lock = 'lead_virtual_schema:'.substr(hash('sha256', $scope), 0, 40);
        if ((int) ($connection->query('SELECT GET_LOCK(?,0) AS acquired', [$lock], true)[0]['acquired'] ?? 0) !== 1)throw new RuntimeException('本结构升级正在执行，请勿重复运行');
        $wait = null;
        try {
            $plan = $this->inspect();
            if (!$plan['missing'])return $this->check() + ['applied' => false, 'created_tables' => [], 'backup' => ''];
            $dir = $this->backend.'/runtime/hsx_lead_upgrade/virtual-payment-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
            for ($parent = $dir;$parent !== dirname($parent);$parent = dirname($parent))if (is_link($parent))throw new RuntimeException('结构备份路径不能为符号链接');
            if (!mkdir($dir, 0700, true) || realpath($dir) !== $dir || !chmod($dir, 0700))throw new RuntimeException('无法建立私有结构备份');
            $backup = $dir.'/schema-before.json';
            $handle = fopen($backup, 'x');
            if (!$handle)throw new RuntimeException('结构备份创建失败');
            try {
                if (!chmod($backup, 0600))throw new RuntimeException('结构备份权限设置失败');
                $json = json_encode([
                        'schema' => 'hsx_lead.virtual_payment.v1',
                        'metadata' => $plan['metadata'],
                        'missing_tables' => $plan['missing'],
                        'sql_sha256' => hash_file('sha256', $this->sqlFile())
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (fwrite($handle, $json) !== strlen($json) || !fflush($handle))throw new RuntimeException('结构备份写入未完成');
            } finally {
                fclose($handle);
            }
            $wait = (int) ($connection->query('SELECT @@SESSION.lock_wait_timeout AS seconds', [], true)[0]['seconds'] ?? 0);
            $connection->execute('SET SESSION lock_wait_timeout=5');
            $created = [];
            foreach ($plan['missing'] as $table) {
                if ($pdo->inTransaction())throw new RuntimeException('检测到外层事务，未继续建表');
                $connection->execute(str_replace('{{prefix}}', $this->prefix(), $plan['definitions'][$table]['sql']));
                $created[] = $table;
                $this->inspect();
            }
            $after = $this->check();
            if ($after['missing_tables'])throw new RuntimeException('建表结果未完整读回，请重新--check');
            return $after + ['applied' => true, 'created_tables' => $created, 'backup' => $backup];
        } finally {
            if ($wait !== null)try {
                $connection->execute('SET SESSION lock_wait_timeout='.$wait);
            } catch (\Throwable $ignored) {
            }try {
                $connection->query('SELECT RELEASE_LOCK(?) AS released', [$lock], true);
            } catch (\Throwable $ignored) {
            }
        }
    }
    private function prefix(): string
    {
        $prefix = (string) Db::connect()->getConfig('prefix');
        if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix))throw new RuntimeException('数据库前缀无效');
        foreach (self::TABLES as $t)if (strlen($prefix.$t) > 64)throw new RuntimeException('数据库表名前缀过长');
        return $prefix;
    }
    private function sqlFile(): string
    {
        return dirname(__DIR__, 3).'/sql/virtual-payment.sql';
    }
    private function definitions(): array
    {
        $source = file_get_contents($this->sqlFile());
        if (!is_string($source))throw new RuntimeException('缺少虚拟支付规范SQL');
        $source = preg_replace('/^\s*--[^\r\n]*$/m', '', $source);
        $pattern = '/CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}([a-z_]+)`\s*\((.*?)\)\s*ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'[^\']*\'\s*;/si';
        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);
        if (count($matches) !== 2 || trim((string) preg_replace($pattern, '', $source)) !== '')throw new RuntimeException('本次升级仅允许两条指定CREATE语句');
        $definitions = [];
        foreach ($matches as $match) {
            $name = $match[1];
            if (!in_array($name, self::TABLES, true) || isset($definitions[$name]))throw new RuntimeException('SQL表范围或数量异常');
            $columns = [];
            $indexes = [];
            foreach (preg_split('/\R/', trim($match[2])) as $line) {
                $line = rtrim(trim($line), ',');
                if (preg_match('/^`([a-z_]+)`\s+((?:bigint|int|tinyint) unsigned|(?:var)?char\([0-9]+\))\s+(NOT NULL|NULL)(?:\s+DEFAULT\s+(NULL|\'[^\']*\'|[0-9]+))?(?:\s+(AUTO_INCREMENT))?$/i', $line, $f)) {
                    $columns[$f[1]] = [
                        'type' => strtolower($f[2]),
                        'nullable' => $f[3] === 'NULL',
                        'default' => isset($f[4]) && $f[4] !== '' ? $this->default($f[4]) : null,
                        'auto' => !empty($f[5])
                    ];
                } elseif (preg_match('/^(PRIMARY KEY|UNIQUE KEY `([a-z_]+)`|KEY `([a-z_]+)`)\s*\((`[a-z_]+`(?:,`[a-z_]+`)*)\)$/i', $line, $f)) {
                    preg_match_all('/`([a-z_]+)`/', $f[4], $parts);
                    $indexes[$f[1] === 'PRIMARY KEY' ? 'PRIMARY' : ($f[2] ?: $f[3])] = ['columns' => $parts[1], 'unique' => !str_starts_with($f[1], 'KEY ')];
                } else throw new RuntimeException('SQL含非规范字段或索引声明');
            }
            if (!$columns || !isset($indexes['PRIMARY']))throw new RuntimeException('SQL字段或主键缺失');
            $definitions[$name] = ['sql' => $match[0], 'columns' => $columns, 'indexes' => $indexes];
        }return $definitions;
    }
    private function inspect(): array
    {
        if (!is_dir($this->backend) || realpath($this->backend) !== $this->backend)throw new RuntimeException('后端路径必须是实际绝对路径');
        if (!Db::name('addon')->where('key', 'hsx_lead')->find())throw new RuntimeException('请先正常安装插件，本命令不执行安装');
        $prefix = $this->prefix();
        $definitions = $this->definitions();
        $missing = [];
        $metadata = [];
        $version = (string) (Db::query('SELECT VERSION() AS v')[0]['v'] ?? '');
        $maria = stripos($version, 'MariaDB') !== false;
        foreach ($definitions as $name => $expected) {
            $table = $prefix.$name;
            $info = Db::query('SELECT TABLE_NAME,ENGINE,TABLE_TYPE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            if (!$info) {
                $missing[] = $name;
                continue;
            }
            if (count($info) !== 1
                || $info[0]['TABLE_TYPE'] !== 'BASE TABLE'
                || strtoupper((string) $info[0]['ENGINE']) !== 'INNODB'
                || !str_starts_with(strtolower((string) $info[0]['TABLE_COLLATION']), 'utf8mb4_'))throw new RuntimeException('同名表存储结构不符，不覆盖：'.$name);
            $fields = Db::query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$table]);
            $actual = [];
            foreach ($fields as $f) {
                if (trim((string) str_ireplace('auto_increment', '', $f['EXTRA'])) !== '')throw new RuntimeException('不接受同名自动生成字段');
                $default = $f['COLUMN_DEFAULT'];
                if ($maria && $default !== null)$default = $this->default((string) $default);
                $actual[$f['COLUMN_NAME']] = [
                    'type' => preg_replace('/\b(bigint|int|tinyint)\([0-9]+\)/i', '$1', strtolower($f['COLUMN_TYPE'])),
                    'nullable' => $f['IS_NULLABLE'] === 'YES',
                    'default' => $default === null ? null : (string) $default,
                    'auto' => stripos($f['EXTRA'], 'auto_increment') !== false
                ];
            }
            if ($actual !== $expected['columns'])throw new RuntimeException('同名表字段不符，不补字段或覆盖：'.$name);
            $parts = Db::query('SHOW INDEX FROM `'.$table.'`');
            $indexes = [];
            foreach ($parts as $p) {
                if ($p['Sub_part'] !== null || strtoupper((string) $p['Index_type']) !== 'BTREE')throw new RuntimeException('同名表索引不符');
                $indexes[$p['Key_name']]['columns'][(int) $p['Seq_in_index']] = $p['Column_name'];
                $indexes[$p['Key_name']]['unique'] = (int) $p['Non_unique'] === 0;
            }foreach ($indexes as &$index) {
                ksort($index['columns']);
                $index['columns'] = array_values($index['columns']);
            }unset($index);
            ksort($indexes);
            $planned = $expected['indexes'];
            ksort($planned);
            if ($indexes !== $planned)throw new RuntimeException('同名表索引不符，不修改：'.$name);
            $metadata[$name] = ['table' => $info[0], 'columns' => $actual, 'indexes' => $indexes];
        }return ['definitions' => $definitions, 'missing' => $missing, 'metadata' => $metadata];
    }
    private function default(string $value): ?string
    {
        if (strtoupper($value) === 'NULL')return null;
        if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'")return substr($value, 1, -1);
        return $value;
    }
}
