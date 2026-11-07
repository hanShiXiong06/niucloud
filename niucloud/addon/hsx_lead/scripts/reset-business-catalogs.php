<?php
declare(strict_types=1);

namespace addon\hsx_lead\scripts;

use PDO;
use RuntimeException;
use stdClass;
use Throwable;

/** Only these fixed, non-sensitive diagnostics are printed to the CLI. */
final class CatalogResetException extends RuntimeException {}

/** Explicit, site-scoped directory reset. Never invoked by an HTTP request or upgrade. */
final class BusinessCatalogResetCommand
{
    public const DEMAND_KEY = 'HSX_LEAD_DEMAND_CATALOG';
    public const COOPERATION_KEY = 'HSX_LEAD_COOPERATION';
    private PDO $pdo;
    private string $prefix;
    private string $backend;
    private $deleteCache;
    public bool $committed = false;
    public string $backup = '';

    /** Injectable dependencies are only for isolated tests, never accepted as CLI arguments. */
    public function __construct(PDO $pdo, string $prefix, string $backend, callable $deleteCache)
    {
        if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix)) throw new CatalogResetException('数据库表前缀格式无效');
        $real = realpath($backend);
        if ($real === false || !is_dir($real)) throw new CatalogResetException('后端目录不存在');
        $this->pdo = $pdo;
        $this->prefix = $prefix;
        $this->backend = $real;
        $this->deleteCache = $deleteCache;
    }

    public static function arguments(array $arguments): array
    {
        $site = 0; $mode = 'check'; $seenMode = false; $localOnly = false;
        foreach ($arguments as $argument) {
            if (is_string($argument) && preg_match('/^--site=([1-9][0-9]{0,8})$/D', $argument, $match) && $site === 0) $site = (int)$match[1];
            elseif (in_array($argument, ['--check', '--apply'], true) && !$seenMode) { $mode = substr($argument, 2); $seenMode = true; }
            elseif ($argument === '--local-only' && !$localOnly) $localOnly = true;
            else throw new CatalogResetException('参数无效或重复；必须指定 --site=站点ID，默认仅检查，实际清空须指定 --apply');
        }
        if ($site <= 0) throw new CatalogResetException('必须明确指定 --site=站点ID；不支持全站清空');
        return ['site' => $site, 'mode' => $mode, 'local_only' => $localOnly];
    }

    /** Pure transformation: preserve JSON objects, unknown keys and non-directory values. */
    public static function prepare(array $rows, int $now): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $key = $row['config_key'] ?? null;
            if (!in_array($key, [self::DEMAND_KEY, self::COOPERATION_KEY], true) || isset($byKey[$key])) throw new CatalogResetException('目录配置存在重复或非目标记录，未执行清空');
            $byKey[$key] = $row;
        }
        if (!isset($byKey[self::COOPERATION_KEY])) return ['can_apply' => false, 'reason' => '本站尚未保存业务配置；请先到业务配置页保存，再重新检查。不会根据默认值替造业务。', 'businesses' => [], 'changes' => []];
        $cooperation = self::objectValue($byKey[self::COOPERATION_KEY]['value'] ?? null);
        if (!property_exists($cooperation, 'businesses') || !is_array($cooperation->businesses) || !self::isList($cooperation->businesses)) throw new CatalogResetException('业务配置缺少有效 businesses 数组，未执行清空');
        $businesses = []; $seen = []; $optionCount = 0;
        foreach ($cooperation->businesses as $business) {
            if (!$business instanceof stdClass || !is_string($business->key ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,15}$/D', $business->key) || isset($seen[$business->key])
                || !property_exists($business, 'options') || !is_array($business->options) || !self::isList($business->options)) throw new CatalogResetException('业务目录结构异常或业务标识重复，未执行清空');
            foreach ($business->options as $option) if (!$option instanceof stdClass) throw new CatalogResetException('业务目录选项不是有效对象，未执行清空');
            $seen[$business->key] = true;
            $count = count($business->options); $optionCount += $count;
            $businesses[] = ['business_type' => $business->key, 'saved_options' => $count];
            $business->options = [];
        }
        $changes = [];
        if ($optionCount > 0) $changes[self::COOPERATION_KEY] = self::encode($cooperation);
        $demandCount = 0;
        if (isset($byKey[self::DEMAND_KEY])) {
            $demand = self::objectValue($byKey[self::DEMAND_KEY]['value'] ?? null);
            if (!property_exists($demand, 'items') || !is_array($demand->items) || !self::isList($demand->items)) throw new CatalogResetException('通用需求目录缺少有效 items 数组，未执行清空');
            foreach ($demand->items as $item) if (!$item instanceof stdClass) throw new CatalogResetException('通用需求目录选项不是有效对象，未执行清空');
            $demandCount = count($demand->items);
            if ($demandCount > 0) { $demand->items = []; $demand->updated_at = $now; $changes[self::DEMAND_KEY] = self::encode($demand); }
        } else {
            // Explicit empty configuration prevents a later implicit fallback.
            $changes[self::DEMAND_KEY] = self::encode((object)['items' => [], 'updated_at' => $now]);
        }
        return ['can_apply' => true, 'businesses' => $businesses, 'saved_business_options' => $optionCount,
            'saved_shared_options' => $demandCount, 'shared_configuration_exists' => isset($byKey[self::DEMAND_KEY]), 'changes' => $changes];
    }

    /** SELECT-only; no directory creation, cache mutation or lock file. */
    public function check(int $site): array
    {
        $this->assertSiteId($site);
        if ($this->pdo->inTransaction()) throw new CatalogResetException('已有数据库事务，拒绝混入目录检查');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
        try {
            $this->site($site, false);
            $plan = self::prepare($this->rows($site, false), time());
            $this->pdo->rollBack();
            return $this->report($site, 'check', $plan);
        } catch (Throwable $error) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $error; }
    }

    public function apply(int $site): array
    {
        $this->assertSiteId($site);
        if ($this->pdo->inTransaction()) throw new CatalogResetException('已有数据库事务，拒绝混入目录清空');
        $this->assertTransactionalTables();
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->beginTransaction();
        try {
            // The same site row is locked by admin configuration/catalog saves.
            $this->site($site, true);
            $before = $this->rows($site, true);
            $now = time(); $plan = self::prepare($before, $now);
            if (!$plan['can_apply']) throw new CatalogResetException($plan['reason']);
            $this->backup = $this->writeBackup($site, $before, $now);
            // Exact keys only; never clear the site's full cache or enqueue work.
            $this->clearCaches($site);
            $expected = array_column($before, null, 'config_key');
            foreach ($plan['changes'] as $key => $value) {
                if (isset($expected[$key])) {
                    $row = $expected[$key];
                    $statement = $this->pdo->prepare('UPDATE ' . $this->table('sys_config') . ' SET `value`=?,`update_time`=? WHERE `site_id`=? AND `id`=? AND `config_key`=?');
                    $statement->execute([$value, $now, $site, $row['id'], $key]);
                    if ($statement->rowCount() !== 1) throw new CatalogResetException('目录精确更新行数异常，事务已停止');
                    $expected[$key]['value'] = $value; $expected[$key]['update_time'] = (string)$now;
                } else {
                    if ($key !== self::DEMAND_KEY) throw new CatalogResetException('不允许创建业务配置');
                    $statement = $this->pdo->prepare('INSERT INTO ' . $this->table('sys_config') . ' (`site_id`,`config_key`,`value`,`create_time`,`update_time`) VALUES (?,?,?,?,?)');
                    $statement->execute([$site, $key, $value, $now, $now]);
                    if ($statement->rowCount() !== 1) throw new CatalogResetException('显式空目录创建行数异常，事务已停止');
                    $id = $this->pdo->lastInsertId();
                    $read = $this->pdo->prepare('SELECT * FROM ' . $this->table('sys_config') . ' WHERE `site_id`=? AND `id`=? AND `config_key`=? FOR UPDATE');
                    $read->execute([$site, $id, $key]); $inserted = $read->fetchAll(PDO::FETCH_ASSOC);
                    if (count($inserted) !== 1 || $inserted[0]['value'] !== $value) throw new CatalogResetException('显式空目录创建回读异常，事务已停止');
                    $expected[$key] = $inserted[0];
                }
            }
            $after = $this->rows($site, true);
            $this->verifyExpected($expected, $after);
            $verified = self::prepare($after, $now);
            if (!$verified['can_apply'] || $verified['changes']) throw new CatalogResetException('目录清空回读未通过，事务已停止');
            $this->pdo->commit(); $this->committed = true;
        } catch (Throwable $error) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $error; }
        // A concurrent reader may have filled the framework cache before commit.
        // If eviction fails now, report committed data explicitly, never fake rollback.
        $this->clearCaches($site);
        $this->verifyExpected($expected, $this->rows($site, false));
        return $this->report($site, 'complete', $plan) + ['data_committed' => true, 'backup' => $this->backup,
            'message' => '仅清空本站所有业务的当前分类/型号选项；库存、业务类型、收费规则、商户能力和既有工单均未修改。请到后台重新导入目录。'];
    }

    private static function isList(array $value): bool { return !$value || array_keys($value) === range(0, count($value) - 1); }
    private static function objectValue($raw): stdClass
    {
        if (!is_string($raw) || $raw === '') throw new CatalogResetException('原配置不是有效 JSON 对象，未执行清空');
        try { $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR); }
        catch (Throwable $error) { throw new CatalogResetException('原配置 JSON 无法完整读取，未执行清空'); }
        if (!$decoded instanceof stdClass) throw new CatalogResetException('原配置必须为 JSON 对象，未执行清空');
        // Refuse floating-point values that may represent an oversized JSON integer.
        $inspect = static function ($value) use (&$inspect): void {
            if (is_float($value) && (!is_finite($value) || abs($value) > 9007199254740991)) throw new CatalogResetException('原配置包含无法无损保留的超大数值，未执行清空');
            if (is_array($value) || $value instanceof stdClass) foreach ($value as $part) $inspect($part);
        };
        $inspect($decoded);
        return $decoded;
    }
    private static function encode($value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR); }
    private function assertSiteId(int $site): void { if ($site <= 0 || $site > 999999999) throw new CatalogResetException('站点 ID 无效，不支持全站清空'); }
    private function table(string $name): string { return '`' . $this->prefix . $name . '`'; }
    private function site(int $site, bool $lock): void
    {
        $statement = $this->pdo->prepare('SELECT `site_id` FROM ' . $this->table('site') . ' WHERE `site_id`=?' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([$site]);
        if (count($statement->fetchAll(PDO::FETCH_ASSOC)) !== 1) throw new CatalogResetException('指定站点不存在，未执行清空');
    }
    private function rows(int $site, bool $lock): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('sys_config') . ' WHERE `site_id`=? AND `config_key` IN (?,?) ORDER BY `config_key`,`id`' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([$site, self::DEMAND_KEY, self::COOPERATION_KEY]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    private function assertTransactionalTables(): void
    {
        $statement = $this->pdo->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)');
        $statement->execute([$this->prefix . 'site', $this->prefix . 'sys_config']); $tables = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($tables) !== 2) throw new CatalogResetException('基础配置表缺失，未执行清空');
        foreach ($tables as $table) if (strcasecmp((string)$table['ENGINE'], 'InnoDB') !== 0) throw new CatalogResetException('基础配置表不支持完整事务回滚，未执行清空');
    }
    private function clearCaches(int $site): void
    {
        foreach ([self::DEMAND_KEY, self::COOPERATION_KEY] as $key) ($this->deleteCache)('site_config_cache' . $key . '_' . $site);
    }
    private function verifyExpected(array $expected, array $actual): void
    {
        $actual = array_column($actual, null, 'config_key');
        if (count($expected) !== count($actual)) throw new CatalogResetException('目录配置记录集合已变化，请重新只读核对');
        foreach ($expected as $key => $row) {
            if (!isset($actual[$key]) || count($row) !== count($actual[$key])) throw new CatalogResetException('目录配置回读结构不一致');
            foreach ($row as $field => $value) if (!array_key_exists($field, $actual[$key]) || (string)$actual[$key][$field] !== (string)$value) throw new CatalogResetException('目录配置回读与预期不一致，请重新只读核对');
        }
    }
    private function report(int $site, string $mode, array $plan): array
    {
        return ['mode' => $mode, 'site_id' => $site, 'can_apply' => $plan['can_apply'], 'reason' => $plan['reason'] ?? '',
            'businesses' => $plan['businesses'], 'saved_shared_options' => $plan['saved_shared_options'] ?? 0,
            'saved_business_options' => $plan['saved_business_options'] ?? 0, 'configuration_changes' => count($plan['changes']),
            'database_writes' => $mode === 'complete' ? count($plan['changes']) : 0,
            'schema_changes' => false, 'inventory_writes' => false, 'business_record_writes' => false, 'external_calls' => false];
    }
    private function writeBackup(int $site, array $rows, int $now): string
    {
        $oldMask = umask(0077);
        try {
            $runtime = $this->backend . '/runtime';
            if (is_link($runtime) || !is_dir($runtime) || realpath($runtime) !== $runtime) throw new CatalogResetException('后端 runtime 必须为本机真实私有目录，未执行清空');
            $root = $runtime . '/hsx_lead_catalog_reset';
            if (is_link($root) || (file_exists($root) && !is_dir($root))) throw new CatalogResetException('目录备份路径异常，未执行清空');
            if (!is_dir($root) && !mkdir($root, 0700)) throw new CatalogResetException('无法创建私有备份目录，未执行清空');
            if (!chmod($root, 0700) || (fileperms($root) & 0777) !== 0700) throw new CatalogResetException('无法保护目录备份权限，未执行清空');
            $run = $root . '/site-' . $site . '-' . date('Ymd-His', $now) . '-' . bin2hex(random_bytes(5));
            if (!mkdir($run, 0700) || !chmod($run, 0700)) throw new CatalogResetException('无法创建本次私有备份，未执行清空');
            $path = $run . '/before.json';
            $checksums = [];
            foreach ($rows as $row) $checksums[$row['config_key']] = hash('sha256', (string)$row['value']);
            $content = self::encode(['schema' => 'hsx_lead.catalog_reset.v1', 'site_id' => $site, 'captured_at' => $now,
                'keys' => [self::DEMAND_KEY, self::COOPERATION_KEY], 'rows' => $rows, 'value_sha256' => $checksums]) . "\n";
            $stream = fopen($path, 'xb');
            if (!$stream) throw new CatalogResetException('无法创建原始配置备份，未执行清空');
            try {
                if (!chmod($path, 0600) || fwrite($stream, $content) !== strlen($content) || !fflush($stream)) throw new CatalogResetException('原始配置备份未完整写入，未执行清空');
                if (function_exists('fsync') && !fsync($stream)) throw new CatalogResetException('原始配置备份未持久化，未执行清空');
            } finally { fclose($stream); }
            if ((fileperms($path) & 0777) !== 0600 || file_get_contents($path) !== $content) throw new CatalogResetException('原始配置备份回读失败，未执行清空');
            return $path;
        } finally { umask($oldMask); }
    }
}

function catalogResetMain(array $arguments): int
{
    if (PHP_SAPI !== 'cli') return 1;
    if ($arguments === ['--help'] || $arguments === ['-h']) {
        echo "php addon/hsx_lead/scripts/reset-business-catalogs.php --site=100005 [--check] [--local-only]\n";
        echo "php addon/hsx_lead/scripts/reset-business-catalogs.php --site=100005 --apply [--local-only]\n";
        echo "默认只检查。--apply 将清空指定站点所有业务的当前分类/型号选项，保留库存、收费和历史业务，并先保存私有备份。\n";
        return 0;
    }
    $command = null; $pdo = null;
    try {
        $options = BusinessCatalogResetCommand::arguments($arguments);
        if (PHP_VERSION_ID < 80000) throw new CatalogResetException('需要 PHP 8.0 或以上，请使用网站相同版本的 PHP 命令');
        error_reporting(E_ALL & ~E_DEPRECATED);
        $backend = dirname(__DIR__, 3);
        require_once $backend . '/vendor/autoload.php';
        $app = new \think\App($backend); $app->loadEnv(); require_once $app->getThinkPath() . 'helper.php'; require_once $backend . '/app/common.php';
        foreach (['app', 'database', 'cache'] as $name) $app->config->set(require $backend . '/config/' . $name . '.php', $name);
        $database = $app->config->get('database'); $connection = $database['connections'][$database['default']] ?? [];
        if (($connection['type'] ?? '') !== 'mysql' || !empty($connection['deploy'])) throw new CatalogResetException('仅支持单节点 MySQL 配置');
        if ($options['local_only'] && !in_array($connection['hostname'] ?? '', ['127.0.0.1', 'localhost'], true)) throw new CatalogResetException('--local-only 已拒绝非本机数据库');
        $database['connections'][$database['default']]['trigger_sql'] = false;
        $database['connections'][$database['default']]['fields_cache'] = false;
        $app->config->set($database, 'database');
        $manager = new \think\DbManager(); $manager->setConfig($database); $app->instance('think\DbManager', $manager);
        \think\facade\Db::query('SELECT 1'); $pdo = \think\facade\Db::connect()->getPdo();
        if (!$pdo instanceof PDO) throw new CatalogResetException('无法取得数据库连接');
        $command = new BusinessCatalogResetCommand($pdo, (string)($connection['prefix'] ?? ''), $backend, static function (string $key): void { \think\facade\Cache::delete($key); });
        if ($options['mode'] === 'apply' && (!defined('addon\\hsx_lead\\app\\service\\CatalogService::BACKEND_ONLY') || \addon\hsx_lead\app\service\CatalogService::BACKEND_ONLY !== true)) throw new CatalogResetException('请先上传配套 CatalogService.php 后端修复；旧版仍会从 ERP 衍生分类，当前未执行清空');
        $report = $options['mode'] === 'apply' ? $command->apply($options['site']) : $command->check($options['site']);
        if ($options['mode'] === 'apply') {
            $directory = (new \addon\hsx_lead\app\service\CatalogService(new \addon\hsx_lead\app\service\Context($options['site'], 1)))->directory();
            if (($directory['items'] ?? null) !== [] || ($directory['source'] ?? '') !== 'configured') throw new CatalogResetException('数据库目录已清空，但服务回读未保持显式空目录，请核对配套 CatalogService.php 和缓存');
            $report['service_readback'] = 'configured_empty';
        }
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
        return $report['can_apply'] ? 0 : 2;
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $known = $error instanceof CatalogResetException;
        $message = $known ? $error->getMessage() : '数据库或缓存操作失败，请核对服务器配置和私有日志；不输出连接凭据或原始配置';
        if ($command instanceof BusinessCatalogResetCommand && $command->committed) {
            fwrite(STDERR, "[数据已提交，后续核验未完成] " . $message . "\n请勿按事务回滚处理；先执行 --check 核对，备份：" . $command->backup . "\n");
            return 2;
        }
        fwrite(STDERR, "[未执行或已回滚] " . $message . "\n");
        return 1;
    }
}

if (!defined('HSX_LEAD_CATALOG_RESET_LIBRARY_ONLY')) exit(catalogResetMain(array_slice($argv ?? [], 1)));
