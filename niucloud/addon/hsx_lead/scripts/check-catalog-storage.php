<?php
declare(strict_types=1);

namespace addon\hsx_lead\scripts;

use addon\hsx_lead\app\service\CatalogConfigStorage as Storage;
use core\exception\CommonException;
use PDO;
use RuntimeException;
use think\facade\Db;
use Throwable;

/** Metadata-only diagnostic. No repair, cache access, DDL or business writes. */
final class CatalogStorageCheckCommand
{
    public static function arguments(array $arguments): array
    {
        $site = null;
        $localOnly = false;
        foreach ($arguments as $argument) {
            if (is_string($argument) && preg_match('/^--site=([1-9][0-9]*)$/D', $argument, $match) && $site === null) {
                $site = filter_var($match[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($site === false) throw new RuntimeException('站点ID必须是可表示的正整数');
            } elseif ($argument === '--local-only' && !$localOnly) $localOnly = true;
            else throw new RuntimeException('参数无效或重复；仅支持 --site=站点ID 和可选的 --local-only');
        }
        if ($site === null) throw new RuntimeException('必须明确指定 --site=站点ID，不支持全站诊断');
        return ['site' => $site, 'local_only' => $localOnly];
    }

    /** Never returns directory values, user text, hashes, credentials or row IDs from fragments. */
    public static function metadata($raw): array
    {
        $result = ['bytes' => is_string($raw) ? strlen($raw) : null, 'json_valid' => false, 'storage' => 'invalid', 'problems' => []];
        // The shared reader explicitly treats a missing/empty value as [].
        if ($raw === null || $raw === '') {
            $result['storage'] = 'empty';
            return $result;
        }
        if (!is_string($raw) || strlen($raw) > Storage::MAX_BYTES) {
            $result['problems'][] = 'value_format_or_size';
            return $result;
        }
        try {
            $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $result['problems'][] = 'invalid_json';
            return $result;
        }
        if (!is_array($value)) {
            $result['problems'][] = 'json_not_array_or_object';
            return $result;
        }
        $result['json_valid'] = true;
        if (!array_key_exists(Storage::MARKER, $value)) {
            $result['storage'] = 'inline';
            return $result;
        }
        $result['storage'] = 'chunked';
        $problems = [];
        $parts = $value['parts'] ?? null;
        $bytes = $value['bytes'] ?? null;
        $ids = $value['row_ids'] ?? null;
        $generationValid = is_string($value['generation'] ?? null) && preg_match('/^[a-f0-9]{16}$/D', $value['generation']) === 1;
        $hashValid = is_string($value['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $value['sha256']) === 1;
        $partsValid = is_int($parts) && $parts >= 1 && $parts <= Storage::MAX_PARTS;
        $bytesValid = is_int($bytes) && $bytes > Storage::INLINE_BYTES && $bytes <= Storage::MAX_BYTES;
        $isList = is_array($ids) && ($ids === [] || array_keys($ids) === range(0, count($ids) - 1));
        $idTypesValid = is_array($ids);
        $seen = [];
        $unique = is_array($ids);
        if (is_array($ids)) foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) {
                $idTypesValid = false;
                $unique = false;
                continue;
            }
            if (isset($seen[$id])) $unique = false;
            $seen[$id] = true;
        }
        if (($value[Storage::MARKER] ?? null) !== Storage::MANIFEST_VERSION) $problems[] = 'manifest_version';
        if (!$generationValid) $problems[] = 'generation_format';
        if (!$partsValid) $problems[] = 'parts_type_or_range';
        if (!$bytesValid) $problems[] = 'bytes_type_or_range';
        if ($partsValid && $bytesValid && $parts !== (int) ceil($bytes / Storage::PART_BYTES)) $problems[] = 'parts_bytes_mismatch';
        if (!$hashValid) $problems[] = 'sha256_format';
        if (!array_key_exists('row_ids', $value)) $problems[] = 'row_ids_missing';
        elseif (!is_array($ids)) $problems[] = 'row_ids_not_array';
        else {
            if (count($ids) !== $parts) $problems[] = 'row_ids_count';
            if (!$isList) $problems[] = 'row_ids_not_list';
            if (!$idTypesValid) $problems[] = 'row_ids_not_positive_integers';
            elseif (!$unique) $problems[] = 'row_ids_duplicate';
        }
        $result['manifest'] = [
            'version' => ($value[Storage::MARKER] ?? null) === Storage::MANIFEST_VERSION ? Storage::MANIFEST_VERSION : 'unrecognized',
            'generation_valid' => $generationValid,
            'parts' => is_int($parts) ? $parts : null, 'parts_type' => gettype($parts),
            'payload_bytes' => is_int($bytes) ? $bytes : null, 'payload_bytes_type' => gettype($bytes),
            'sha256_valid' => $hashValid,
            'row_ids' => ['present' => array_key_exists('row_ids', $value), 'type' => gettype($ids),
                'count' => is_array($ids) ? count($ids) : null, 'is_list' => $isList,
                'positive_integer_values' => $idTypesValid, 'unique' => $unique],
        ];
        $result['problems'] = $problems;
        return $result;
    }

    /** The shared reader's known constant messages only; never print arbitrary SQL/driver errors. */
    public static function safeReadError(Throwable $error): array
    {
        $known = [
            '后台目录存储索引异常，请管理员核对；不会使用默认数据替代',
            '后台目录分片主键索引异常，请管理员核对',
            '后台目录配置存在重复记录，请管理员核对',
            '后台目录配置分片缺失，请管理员核对或重新导入；没有返回不完整目录',
            '后台目录配置存在重复或错配分片，请管理员核对',
            '后台目录配置分片缺失或索引变化，请管理员核对或重新导入',
            '后台目录配置分片不匹配，请管理员核对；没有返回不完整目录',
            '后台目录配置分片内容不完整，请管理员核对',
            '后台目录配置完整性核对失败，请管理员核对；没有返回截断目录',
            '后台目录配置包含保留存储标识，请管理员核对',
            '后台目录配置格式或容量异常，请管理员核对',
            '后台目录配置JSON不完整，可能曾被数据库截断，请管理员核对或重新导入',
            '后台目录配置不是有效对象，请管理员核对',
            '后台目录配置定位已变化，请刷新后重试',
        ];
        return ['status' => 'failed', 'message' => $error instanceof CommonException && in_array($error->getMessage(), $known, true)
            ? $error->getMessage() : '读取未完成；请管理员核对数据库与后端文件，不输出原始错误或配置内容'];
    }

    public static function check(int $site): array
    {
        if ($site <= 0) throw new RuntimeException('站点ID必须为正整数');
        Db::query('SELECT 1');
        $pdo = Db::connect()->getPdo();
        if (!$pdo instanceof PDO || $pdo->inTransaction()) throw new RuntimeException('诊断需要独立数据库连接，不支持混入现有事务');
        // Enforced by MySQL, including nested Storage::read calls. ORM owns the
        // transaction counter, so the existing reader can safely use savepoints.
        $pdo->exec('SET TRANSACTION READ ONLY');
        Db::startTrans();
        try {
            if (!Db::name('site')->where('site_id', $site)->field('site_id')->find()) throw new RuntimeException('指定站点不存在');
            $rows = Db::name('sys_config')->where('site_id', $site)->where(function ($query): void {
                $query->whereIn('config_key', ['HSX_LEAD_DEMAND_CATALOG', 'HSX_LEAD_COOPERATION'])
                    ->whereOrRaw('config_key REGEXP ?', ['^HSX_LEAD_CAPABILITY_[1-9][0-9]{0,9}$']);
            })->field('id,config_key,value')->order('id')->select()->toArray();
            $reports = [];
            $keys = ['HSX_LEAD_DEMAND_CATALOG' => [], 'HSX_LEAD_COOPERATION' => []];
            $failed = false;
            foreach ($rows as $row) {
                $key = (string) $row['config_key'];
                if (!in_array($key, ['HSX_LEAD_DEMAND_CATALOG', 'HSX_LEAD_COOPERATION'], true)
                    && !preg_match('/^HSX_LEAD_CAPABILITY_[1-9][0-9]{0,9}$/D', $key)) continue;
                $metadata = self::metadata($row['value']);
                $reports[] = ['config_id' => (int) $row['id'], 'config_key' => $key] + $metadata;
                $keys[$key][] = (int) $row['id'];
                if ($metadata['problems']) $failed = true;
            }
            unset($rows, $row);
            $reads = [];
            foreach ($keys as $key => $ids) {
                try {
                    Storage::read($site, $key);
                    $read = ['status' => 'passed'];
                } catch (Throwable $error) {
                    $read = self::safeReadError($error);
                    $failed = true;
                }
                $reads[] = ['config_key' => $key, 'stored_rows' => count($ids), 'configured' => count($ids) > 0, 'read' => $read];
            }
            return ['schema' => 'hsx_lead.catalog_storage_check.v1', 'site_id' => $site, 'read_only' => true,
                'database_writes' => 0, 'cache_changes' => 0, 'automatic_repairs' => 0,
                'passed' => !$failed, 'stored_configs' => $reports, 'service_reads' => $reads];
        } finally {
            Db::rollback();
        }
    }
}

function catalogStorageCheckMain(array $arguments): int
{
    if (PHP_SAPI !== 'cli') return 1;
    if ($arguments === ['--help'] || $arguments === ['-h']) {
        echo "php addon/hsx_lead/scripts/check-catalog-storage.php --site=100005 [--local-only]\n仅检查目录和能力存储元数据；不修改数据库、缓存或业务配置。发现异常返回退出码1。\n";
        return 0;
    }
    try {
        $options = CatalogStorageCheckCommand::arguments($arguments);
    } catch (RuntimeException $error) {
        fwrite(STDERR, $error->getMessage()."\n");
        return 1;
    }
    $stage = 'bootstrap';
    try {
        if (PHP_VERSION_ID < 80000) throw new RuntimeException('Requires PHP 8.0');
        error_reporting(E_ALL & ~E_DEPRECATED);
        $root = dirname(__DIR__, 3);
        require_once $root.'/vendor/autoload.php';
        $app = new \think\App($root);
        $app->loadEnv();
        require_once $app->getThinkPath().'helper.php';
        $stage = 'database_configuration';
        $database = require $root.'/config/database.php';
        $connection = $database['connections'][$database['default']] ?? [];
        if (($connection['type'] ?? '') !== 'mysql' || !empty($connection['deploy'])) throw new RuntimeException('Unsupported diagnostic database');
        if ($options['local_only'] && !in_array($connection['hostname'] ?? '', ['127.0.0.1', 'localhost'], true)) throw new RuntimeException('Local-only diagnostic refused remote database');
        $database['connections'][$database['default']]['trigger_sql'] = false;
        $database['connections'][$database['default']]['fields_cache'] = false;
        $app->config->set($database, 'database');
        $manager = new \think\DbManager();
        $manager->setConfig($database);
        $app->instance('think\DbManager', $manager);
        $stage = 'read_only_check';
        $report = CatalogStorageCheckCommand::check($options['site']);
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
        return $report['passed'] ? 0 : 1;
    } catch (Throwable $error) {
        fwrite(STDERR, '只读目录诊断未完成（阶段：'.$stage."）；请核对站点参数、数据库连接和配套后端文件。没有执行保存、修复或缓存清理，不输出原始错误或凭据。\n");
        return 1;
    }
}

if (!defined('HSX_LEAD_CATALOG_STORAGE_CHECK_LIBRARY_ONLY')) exit(catalogStorageCheckMain(array_slice($argv ?? [], 1)));
