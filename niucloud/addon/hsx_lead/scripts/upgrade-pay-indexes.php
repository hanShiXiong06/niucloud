<?php
declare(strict_types=1);

namespace addon\hsx_lead\scripts;

use RuntimeException;
use think\facade\Db;

/**
 * Explicit additive index upgrade on the framework cashier tables `pay` and `pay_refund`.
 * Never invoked by an API or a worker; run by an administrator after a full backup.
 *
 * The framework ships both tables with only a primary key, so every plugin lookup of a
 * payment by order (trade_type + trade_id) or of a refund by out_trade_no scans the
 * whole table. These two secondary indexes are generic (no plugin name, no plugin column)
 * and also benefit the framework's own lookups. No row, column or table is changed.
 */
final class PayIndexUpgrade
{
    /** table => [index name, columns] */
    private const PLANS = [
        'pay' => ['idx_site_trade', ['site_id', 'trade_type', 'trade_id']],
        'pay_refund' => ['idx_site_out_trade_no', ['site_id', 'out_trade_no']],
    ];

    public function __construct(private string $backend)
    {
    }

    /** Read-only: no cache, file, business-row or schema writes. */
    public function check(): array
    {
        $db = Db::getConfig();
        $prefix = (string) ($db['connections'][$db['default']]['prefix'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix))throw new RuntimeException('数据库表前缀无效，未执行');
        $plans = [];
        $pending = 0;
        foreach (self::PLANS as $base => [$index,$columnsWanted]) {
            $table = $prefix.$base;
            $meta = Db::query('SELECT ENGINE,TABLE_ROWS,DATA_LENGTH,INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            if (!$meta || strtoupper((string) $meta[0]['ENGINE']) !== 'INNODB')throw new RuntimeException('缺少框架表 '.$table.' 或不是InnoDB；本命令不创建表');
            $columns = Db::query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            if (array_diff($columnsWanted, array_column($columns, 'COLUMN_NAME')))throw new RuntimeException('框架表 '.$table.' 字段不完整，请先核对框架版本，未执行索引升级');
            $groups = [];
            foreach (Db::query('SHOW INDEX FROM `'.$table.'`') as $part)$groups[(string) $part['Key_name']][(int) $part['Seq_in_index']] = $part;
            $hasEquivalent = false;
            foreach ($groups as $name => $parts) {
                ksort($parts);
                $parts = array_values($parts);
                // Any visible BTREE index whose leading columns are exactly the wanted ones already serves these lookups.
                $leading = array_slice(array_column($parts, 'Column_name'), 0, count($columnsWanted));
                $usable = $leading === $columnsWanted;
                foreach (array_slice($parts, 0, count($columnsWanted)) as $part)$usable = $usable && $part['Sub_part'] === null && strtoupper((string) $part['Index_type']) === 'BTREE'
                    && strtoupper((string) ($part['Visible'] ?? 'YES')) === 'YES' && strtoupper((string) ($part['Ignored'] ?? 'NO')) === 'NO';
                if ($name === $index && !$usable)throw new RuntimeException($table.' 已有同名索引 '.$index.' 但结构不同，拒绝覆盖，请管理员核对');
                if ($usable)$hasEquivalent = true;
            }
            $sql = 'ALTER TABLE `'.$table.'` ADD INDEX `'.$index.'` (`'.implode('`,`', $columnsWanted).'`), ALGORITHM=INPLACE, LOCK=NONE';
            if (!$hasEquivalent)$pending++;
            $plans[] = ['table' => $table, 'index' => $index, 'columns' => $columnsWanted, 'pending' => !$hasEquivalent,
                'estimated_rows' => (int) $meta[0]['TABLE_ROWS'], 'estimated_table_bytes' => (int) $meta[0]['DATA_LENGTH'] + (int) $meta[0]['INDEX_LENGTH'],
                'sql' => $hasEquivalent ? null : $sql];
        }
        return ['plans' => $plans, 'pending_statements' => $pending, 'framework_tables' => true,
            'business_row_writes' => false, 'creates_tables' => false, 'changes_fields' => false];
    }

    public function apply(): array
    {
        $connection = Db::connect();
        // connect() only fills the pool; getPdo() returns the active link, which the first master query sets.
        $connection->query('SELECT 1', [], true);
        $pdo = $connection->getPdo();
        if (!$pdo)throw new RuntimeException('数据库连接未建立，未执行');
        if ($pdo->inTransaction())throw new RuntimeException('索引DDL不能在业务事务内执行');
        $plan = $this->check();
        if (!$plan['pending_statements'])return $plan + ['applied' => [], 'message' => '索引均已存在，未重复执行'];
        $scope = (string) $connection->getConfig('database').':'.(string) $connection->getConfig('prefix');
        $key = 'lead_pay_index:'.substr(hash('sha256', $scope), 0, 40);
        $lock = $connection->query('SELECT GET_LOCK(?, 0) AS acquired', [$key], true);
        if ((int) ($lock[0]['acquired'] ?? 0) !== 1)throw new RuntimeException('支付表索引升级正在执行，请勿重复运行');
        $oldWait = null;
        $applied = [];
        try {
            $plan = $this->check();
            if (!$plan['pending_statements'])return $plan + ['applied' => [], 'message' => '索引均已存在，未重复执行'];
            $path = $this->backend.'/runtime/hsx_lead_upgrade/pay-indexes-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
            for ($parent = dirname($path);$parent !== dirname($parent);$parent = dirname($parent))if (is_link($parent))throw new RuntimeException('结构备份路径含符号链接，未执行');
            if (!mkdir($path, 0700, true) && !is_dir($path))throw new RuntimeException('无法创建结构备份目录，未执行');
            chmod($path, 0700);
            $before = ['plan' => $plan];
            foreach ($plan['plans'] as $item)$before['tables'][$item['table']] = ['create_table' => Db::query('SHOW CREATE TABLE `'.$item['table'].'`'), 'indexes' => Db::query('SHOW INDEX FROM `'.$item['table'].'`')];
            $backup = $path.'/schema-before.json';
            if (file_put_contents($backup, json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false)throw new RuntimeException('结构备份失败，未执行');
            chmod($backup, 0600);
            $oldWait = (int) ($connection->query('SELECT @@SESSION.lock_wait_timeout AS wait_seconds')[0]['wait_seconds'] ?? 0);
            $connection->execute('SET SESSION lock_wait_timeout=5');
            // One statement per table. No blocking/COPY fallback. MySQL DDL commits independently;
            // if interrupted, re-run --check: a finished table is skipped, never rebuilt.
            foreach ($plan['plans'] as $item) {
                if (!$item['pending'])continue;
                $connection->execute($item['sql']);
                $applied[] = $item['table'].'.'.$item['index'];
            }
            $after = $this->check();
            if ($after['pending_statements'])throw new RuntimeException('执行后索引读回未确认，请重新运行--check核对');
            return $after + ['applied' => $applied, 'backup' => $backup, 'message' => '仅为框架支付/退款表新增通用查询索引，未修改任何付款、退款、金额或配置记录'];
        } finally {
            if ($oldWait !== null)try {
                $connection->execute('SET SESSION lock_wait_timeout='.$oldWait);
            } catch (\Throwable $ignored) {
            }
            try {
                $connection->query('SELECT RELEASE_LOCK(?) AS released', [$key], true);
            } catch (\Throwable $ignored) {
            }
        }
    }
}

if (!defined('HSX_LEAD_PAY_INDEX_LIBRARY_ONLY')) {
    if (PHP_SAPI !== 'cli')exit(1);
    $mode = $argv[1] ?? '';
    if (count($argv) !== 2 || !in_array($mode, ['--check', '--apply'], true))exit("php addon/hsx_lead/scripts/upgrade-pay-indexes.php --check|--apply\n");
    define('HSX_LEAD_UPGRADE_LIBRARY_ONLY', true);
    require __DIR__.'/upgrade.php';
    try {
        $backend = dirname(__DIR__, 3);
        bootstrap($backend);
        $command = new PayIndexUpgrade($backend);
        echo json_encode($mode === '--check' ? $command->check() : $command->apply(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),PHP_EOL;
    } catch (\Throwable $error) {
        fwrite(STDERR, '[未确认完成] '.($error instanceof RuntimeException ? $error->getMessage() : '索引操作失败或数据库不支持在线建索引，未降级为锁表建索引')."\n请重新--check核对；DDL不保证事务回滚，勿卸载重装。\n");
        exit(1);
    }
}
