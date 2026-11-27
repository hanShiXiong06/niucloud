<?php
declare(strict_types=1);

namespace addon\hsx_lead\scripts;

use addon\hsx_lead\app\service\CatalogConfigStorage as Storage;
use PDO;
use RuntimeException;
use think\facade\Db;
use Throwable;

final class CatalogIndexRepairException extends RuntimeException
{
}

/** Explicit maintenance only: append a verified missing fragment index, never repair payloads. */
final class CatalogIndexRepair
{
    private string $backupRoot;

    /** Injectable backup root is for isolated tests; the CLI accepts no path override. */
    public function __construct(?string $backupRoot = null)
    {
        $this->backupRoot = rtrim($backupRoot ?? dirname(__DIR__, 3).'/runtime/hsx_lead_catalog_index_repair', '/');
        if ($this->backupRoot === '' || $this->backupRoot[0] !== '/' || strpos($this->backupRoot, "\0") !== false) throw new CatalogIndexRepairException('备份目录必须是受控的绝对路径');
    }

    public static function arguments(array $arguments): array
    {
        $site = null;
        $key = null;
        $apply = false;
        $seenMode = false;
        $localOnly = false;
        foreach ($arguments as $argument) {
            if (is_string($argument) && preg_match('/^--site=([1-9][0-9]*)$/D', $argument, $match) && $site === null) {
                $site = filter_var($match[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($site === false) throw new CatalogIndexRepairException('站点ID必须是可表示的正整数');
            } elseif (is_string($argument) && strpos($argument, '--key=') === 0 && $key === null) $key = substr($argument, 6);
            elseif (in_array($argument, ['--check', '--apply'], true) && !$seenMode) {
                $apply = $argument === '--apply';
                $seenMode = true;
            } elseif ($argument === '--local-only' && !$localOnly) $localOnly = true;
            else throw new CatalogIndexRepairException('参数无效或重复；仅支持 --site=站点ID --key=配置键 [--check|--apply] [--local-only]');
        }
        if ($site === null || $key === null) throw new CatalogIndexRepairException('必须明确指定一个 --site=站点ID 和 --key=配置键');
        self::identity($site, $key);
        return ['site' => $site, 'key' => $key, 'apply' => $apply, 'local_only' => $localOnly];
    }

    public function run(int $site, string $key, bool $apply = false): array
    {
        self::identity($site, $key);
        Db::query('SELECT 1');
        $pdo = Db::connect()->getPdo();
        if (!$pdo instanceof PDO || $pdo->inTransaction()) throw new CatalogIndexRepairException('修复必须使用独立数据库事务，拒绝已有外层事务');
        // Next transaction only: check cannot write, and the connection's
        // session default is not changed for subsequent library callers.
        if (!$apply) $pdo->exec('SET TRANSACTION READ ONLY');
        Db::startTrans();
        try {
            // Match application writer lock order. Every locking query below
            // is scoped to exact primary IDs, never a site/key table-scan lock.
            $siteRow = Db::name('site')->force('PRIMARY')->where('site_id', $site)->field('site_id')
                ->lock($apply ? true : 'LOCK IN SHARE MODE')->find();
            if (!$siteRow) throw new CatalogIndexRepairException('指定站点不存在，未执行修复');
            $before = $this->load($site, $key, $apply);
            if ($before['missing_index'] === false) {
                Storage::read($site, $key);
                $report = $this->report($site, $key, $before, 'no_repair_needed', $apply);
                Db::rollback();
                return $report;
            }
            if (!$apply) {
                $report = $this->report($site, $key, $before, 'repairable', false);
                Db::rollback();
                return $report;
            }
            $newValue = self::encode($before['candidate']);
            $backup = $this->backup($site, $key, $before);
            $root = $before['root'];
            $affected = Db::name('sys_config')->where('id', (int) $root['id'])->where('site_id', $site)
                ->where('config_key', $key)->where('value', $root['value'])->update(['value' => $newValue]);
            if ($affected !== 1) throw new CatalogIndexRepairException('目录索引精确更新行数异常，事务已回滚');
            $after = $this->load($site, $key, true);
            if ($after['missing_index'] || $after['root']['value'] !== $newValue
                || $after['payload'] !== $before['payload'] || $after['decoded'] !== $before['decoded']) {
                throw new CatalogIndexRepairException('修复后目录字节或业务内容变化，事务已回滚');
            }
            if (Storage::read($site, $key) !== $before['decoded']) throw new CatalogIndexRepairException('修复后严格服务回读不一致，事务已回滚');
            Db::commit();
            return $this->report($site, $key, $after, 'repaired', true) + ['backup' => $backup, 'strict_read' => 'passed'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) Db::rollback();
            throw $error;
        }
    }

    private static function identity(int $site, string $key): void
    {
        if ($site <= 0 || (!in_array($key, ['HSX_LEAD_DEMAND_CATALOG', 'HSX_LEAD_COOPERATION'], true)
            && !preg_match('/^HSX_LEAD_CAPABILITY_[1-9][0-9]{0,9}$/D', $key))) throw new CatalogIndexRepairException('仅允许指定站点的业务目录或商户能力配置，不支持其他配置或全站修复');
    }

    private static function decode($raw): array
    {
        if ($raw === null || $raw === '') return [];
        if (!is_string($raw) || strlen($raw) > Storage::MAX_BYTES) throw new CatalogIndexRepairException('目录格式或容量异常，未执行修复');
        try {
            $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            throw new CatalogIndexRepairException('目录JSON不完整，不能修复索引');
        }
        if (!is_array($value)) throw new CatalogIndexRepairException('目录不是有效JSON对象或数组，不能修复索引');
        return $value;
    }

    private static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            throw new CatalogIndexRepairException('备份或索引无法完整编码，未执行修复');
        }
    }

    /** Load a coherent, locked generation and verify every byte against its ORIGINAL checksum. */
    private function load(int $site, string $key, bool $exclusive): array
    {
        $found = Db::name('sys_config')->where('site_id', $site)->where('config_key', $key)->field('id')->limit(2)->select()->toArray();
        if (count($found) !== 1) throw new CatalogIndexRepairException('目标目录不存在或存在重复记录，未执行修复');
        $rootRows = Db::name('sys_config')->force('PRIMARY')->where('id', (int) $found[0]['id'])->where('site_id', $site)->where('config_key', $key)
            ->field('id,site_id,config_key,value')->lock($exclusive ? true : 'LOCK IN SHARE MODE')->select()->toArray();
        if (count($rootRows) !== 1) throw new CatalogIndexRepairException('目录定位已经变化，未执行修复');
        $root = $rootRows[0];
        $manifest = self::decode($root['value']);
        if (!array_key_exists(Storage::MARKER, $manifest)) return ['root' => $root, 'missing_index' => false,
            'storage' => 'inline', 'parts' => [], 'payload' => (string) $root['value'], 'decoded' => $manifest];
        if (($manifest[Storage::MARKER] ?? null) !== Storage::MANIFEST_VERSION
            || !is_string($manifest['generation'] ?? null) || !preg_match('/^[a-f0-9]{16}$/D', $manifest['generation'])
            || !is_int($manifest['parts'] ?? null) || $manifest['parts'] < 1 || $manifest['parts'] > Storage::MAX_PARTS
            || !is_int($manifest['bytes'] ?? null) || $manifest['bytes'] <= Storage::INLINE_BYTES || $manifest['bytes'] > Storage::MAX_BYTES
            || $manifest['parts'] !== (int) ceil($manifest['bytes'] / Storage::PART_BYTES)
            || !is_string($manifest['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $manifest['sha256'])) {
            throw new CatalogIndexRepairException('目录版本、分片数、字节数或原始校验和异常，不能补索引');
        }
        $missing = !array_key_exists('row_ids', $manifest);
        if (!$missing) {
            $ids = $manifest['row_ids'];
            if (!is_array($ids) || count($ids) !== $manifest['parts'] || array_keys($ids) !== range(0, $manifest['parts'] - 1)) throw new CatalogIndexRepairException('已有目录索引格式异常，不能按缺失索引修复');
            $seen = [];
            foreach ($ids as $id) {
                if (!is_int($id) || $id <= 0 || isset($seen[$id])) throw new CatalogIndexRepairException('已有目录索引值异常，不能按缺失索引修复');
                $seen[$id] = true;
            }
        }
        $prefix = 'HSX_LEAD_CFG_'.substr(hash('sha256', $key), 0, 32).'_'.$manifest['generation'].'_';
        $keys = [];
        for ($index = 0; $index < $manifest['parts']; $index++) $keys[] = $prefix.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        // Discovery is nonlocking; subsequently lock only discovered primary IDs.
        $foundParts = Db::name('sys_config')->where('site_id', $site)->whereIn('config_key', $keys)->field('id,config_key')->select()->toArray();
        if (count($foundParts) !== $manifest['parts']) throw new CatalogIndexRepairException('目录分片缺失、重复或不属于本站，不能补索引');
        $foundKeys = array_column($foundParts, 'config_key');
        if (count(array_unique($foundKeys)) !== $manifest['parts'] || array_diff($keys, $foundKeys)) throw new CatalogIndexRepairException('目录分片名称重复或错配，不能补索引');
        $partIds = array_map(static fn (array $row): int => (int) $row['id'], $foundParts);
        $parts = Db::name('sys_config')->force('PRIMARY')->whereIn('id', $partIds)->where('site_id', $site)
            ->field('id,site_id,config_key,value')->order('id')->lock($exclusive ? true : 'LOCK IN SHARE MODE')->select()->toArray();
        if (count($parts) !== $manifest['parts']) throw new CatalogIndexRepairException('目录分片定位已经变化，不能补索引');
        $byKey = [];
        foreach ($parts as $partRow) {
            $partKey = (string) $partRow['config_key'];
            if (isset($byKey[$partKey]) || !in_array($partKey, $keys, true)) throw new CatalogIndexRepairException('目录分片存在重复或错配，不能补索引');
            $byKey[$partKey] = $partRow;
        }
        $payload = '';
        $rowIds = [];
        foreach ($keys as $index => $partKey) {
            if (!isset($byKey[$partKey])) throw new CatalogIndexRepairException('目录分片已变化，不能补索引');
            $partRow = $byKey[$partKey];
            $rowIds[] = (int) $partRow['id'];
            if (!$missing && $manifest['row_ids'][$index] !== (int) $partRow['id']) throw new CatalogIndexRepairException('已有目录索引指向不同记录，不能覆盖修复');
            $part = self::decode($partRow['value']);
            if (($part[Storage::MARKER] ?? null) !== Storage::PART_VERSION || ($part['base_key'] ?? null) !== $key
                || ($part['generation'] ?? null) !== $manifest['generation'] || ($part['index'] ?? null) !== $index
                || !is_string($part['data'] ?? null) || strlen($part['data']) > 32768) throw new CatalogIndexRepairException('目录分片元数据不匹配，不能补索引');
            $bytes = base64_decode($part['data'], true);
            $expected = $index === $manifest['parts'] - 1 ? $manifest['bytes'] - $index * Storage::PART_BYTES : Storage::PART_BYTES;
            if ($bytes === false || strlen($bytes) !== $expected || base64_encode($bytes) !== $part['data']) throw new CatalogIndexRepairException('目录分片内容或长度异常，不能补索引');
            $payload .= $bytes;
        }
        if (strlen($payload) !== $manifest['bytes'] || !hash_equals($manifest['sha256'], hash('sha256', $payload))) throw new CatalogIndexRepairException('目录内容与原始校验和不符，不能补索引');
        $decoded = self::decode($payload);
        if (array_key_exists(Storage::MARKER, $decoded)) throw new CatalogIndexRepairException('目录内容使用了内部保留标识，不能补索引');
        $candidate = $manifest;
        $candidate['row_ids'] = $rowIds;
        return ['root' => $root, 'storage' => 'chunked', 'missing_index' => $missing, 'parts' => $parts,
            'manifest' => $manifest, 'candidate' => $candidate, 'payload' => $payload, 'decoded' => $decoded];
    }

    private function report(int $site, string $key, array $loaded, string $status, bool $apply): array
    {
        return ['schema' => 'hsx_lead.catalog_index_repair.v1', 'status' => $status, 'mode' => $apply ? 'apply' : 'check',
            'site_id' => $site, 'config_key' => $key, 'config_id' => (int) $loaded['root']['id'], 'storage' => $loaded['storage'],
            'parts' => count($loaded['parts']), 'payload_bytes' => strlen($loaded['payload']),
            'payload_sha256' => hash('sha256', $loaded['payload']), 'database_writes' => $status === 'repaired' ? 1 : 0,
            'cache_changes' => 0, 'business_payload_changed' => false, 'ddl' => false];
    }

    private function backup(int $site, string $key, array $loaded): string
    {
        $root = $this->backupRoot;
        $parent = dirname($root);
        if (!is_dir($parent) || realpath($parent) !== $parent || is_link($root)) throw new CatalogIndexRepairException('私有备份目录父路径异常，未执行修复');
        $mask = umask(0077);
        try {
            if (!is_dir($root) && !mkdir($root, 0700)) throw new CatalogIndexRepairException('无法创建私有备份目录，未执行修复');
            clearstatcache(true, $root);
            if (realpath($root) !== $root || (fileperms($root) & 0777) !== 0700) throw new CatalogIndexRepairException('备份目录必须仅当前用户可访问，未执行修复');
            $run = $root.'/site-'.$site.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(6));
            if (!mkdir($run, 0700)) throw new CatalogIndexRepairException('无法创建本次私有备份目录，未执行修复');
            clearstatcache(true, $run);
            if (is_link($run) || (fileperms($run) & 0777) !== 0700) throw new CatalogIndexRepairException('本次备份目录权限异常，未执行修复');
            $path = $run.'/before.json';
            $content = self::encode(['schema' => 'hsx_lead.catalog_index_backup.v1', 'site_id' => $site, 'config_key' => $key,
                'captured_at' => time(), 'root' => $loaded['root'], 'parts' => $loaded['parts'],
                'root_value_sha256' => hash('sha256', $loaded['root']['value']),
                'payload_sha256' => hash('sha256', $loaded['payload'])])."\n";
            $stream = fopen($path, 'xb');
            if ($stream === false) throw new CatalogIndexRepairException('无法创建原始目录备份，未执行修复');
            try {
                if (!chmod($path, 0600)) throw new CatalogIndexRepairException('无法保护备份文件权限，未执行修复');
                $offset = 0;
                $length = strlen($content);
                while ($offset < $length) {
                    $written = fwrite($stream, substr($content, $offset));
                    if ($written === false || $written === 0) throw new CatalogIndexRepairException('原始备份未完整写入，未执行修复');
                    $offset += $written;
                }
                if (!fflush($stream) || (function_exists('fsync') && !fsync($stream))) throw new CatalogIndexRepairException('备份未持久化，未执行修复');
            } finally {
                fclose($stream);
            }
            clearstatcache(true, $path);
            if (is_link($path) || (fileperms($path) & 0777) !== 0600 || !hash_equals(hash('sha256', $content), (string) hash_file('sha256', $path))) throw new CatalogIndexRepairException('备份权限或回读校验失败，未执行修复');
            return $path;
        } finally {
            umask($mask);
        }
    }
}

function catalogIndexRepairMain(array $arguments): int
{
    if (PHP_SAPI !== 'cli') return 1;
    if ($arguments === ['--help'] || $arguments === ['-h']) {
        echo "php addon/hsx_lead/scripts/repair-catalog-index.php --site=100005 --key=HSX_LEAD_DEMAND_CATALOG --check\n";
        echo "php addon/hsx_lead/scripts/repair-catalog-index.php --site=100005 --key=HSX_LEAD_DEMAND_CATALOG --apply\n";
        echo "默认只读检查；只有完整验证通过的旧分片配置缺失row_ids时，--apply才会私有备份并补索引。不清空目录，不更改业务内容。\n";
        return 0;
    }
    try {
        $options = CatalogIndexRepair::arguments($arguments);
        if (PHP_VERSION_ID < 80000) throw new CatalogIndexRepairException('需要PHP 8.0或以上');
        error_reporting(E_ALL & ~E_DEPRECATED);
        $root = dirname(__DIR__, 3);
        require_once $root.'/vendor/autoload.php';
        $app = new \think\App($root);
        $app->loadEnv();
        require_once $app->getThinkPath().'helper.php';
        $database = require $root.'/config/database.php';
        $connection = $database['connections'][$database['default']] ?? [];
        if (($connection['type'] ?? '') !== 'mysql' || !empty($connection['deploy'])) throw new CatalogIndexRepairException('仅支持单节点MySQL配置');
        if ($options['local_only'] && !in_array($connection['hostname'] ?? '', ['127.0.0.1', 'localhost'], true)) throw new CatalogIndexRepairException('--local-only已拒绝非本机数据库');
        $database['connections'][$database['default']]['trigger_sql'] = false;
        $database['connections'][$database['default']]['fields_cache'] = false;
        $app->config->set($database, 'database');
        $manager = new \think\DbManager();
        $manager->setConfig($database);
        $app->instance('think\DbManager', $manager);
        $report = (new CatalogIndexRepair())->run($options['site'], $options['key'], $options['apply']);
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
        return 0;
    } catch (Throwable $error) {
        fwrite(STDERR, ($error instanceof CatalogIndexRepairException ? $error->getMessage() : '目录索引操作未完成，请核对数据库及配套后端文件；不输出原始错误或凭据')."\n");
        return 1;
    }
}

if (!defined('HSX_LEAD_CATALOG_INDEX_REPAIR_LIBRARY_ONLY')) exit(catalogIndexRepairMain(array_slice($argv ?? [], 1)));
