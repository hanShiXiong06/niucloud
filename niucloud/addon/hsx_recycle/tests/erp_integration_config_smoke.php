<?php
declare(strict_types=1);

// 模拟站点行锁与已有配置存储，不加载框架、不连接数据库。
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\model\sys {
    class Row {
        public function __construct(private array $data) {}
        public function toArray(): array { return $this->data; }
        public function isEmpty(): bool { return $this->data === []; }
    }
    class ConfigQuery {
        private bool $locked = false;
        public function __construct(private array $conditions) {}
        public function field(string $field): self { return $this; }
        public function lock(bool $locked): self { $this->locked = $locked; return $this; }
        public function findOrEmpty(): Row {
            if (\think\facade\Db::$active && !$this->locked) throw new \RuntimeException('configuration read inside save must be current/locking read');
            if (SysConfig::$failRead) throw new \RuntimeException('configuration lookup unavailable');
            foreach (SysConfig::$rows as $row) {
                $matches = true;
                foreach ($this->conditions as [$field, $operator, $value]) if ($row[$field] !== $value) $matches = false;
                if ($matches) return new Row($row);
            }
            return new Row([]);
        }
    }
    class SysConfig {
        public static array $rows = [];
        public static bool $failRead = false;
        public static function where(array $conditions): ConfigQuery { return new ConfigQuery($conditions); }
    }
}
namespace app\model\site {
    class SiteQuery {
        private bool $locked = false;
        public function __construct(private int $siteId) {}
        public function field(string $field): self { return $this; }
        public function lock(bool $lock): self { $this->locked = $lock; return $this; }
        public function findOrEmpty(): \app\model\sys\Row {
            if (!$this->locked || !\think\facade\Db::$active) throw new \RuntimeException('site row must be locked inside transaction');
            Site::$locks++;
            if (is_callable(Site::$onLock)) { $hook = Site::$onLock; Site::$onLock = null; $hook(); }
            return new \app\model\sys\Row(in_array($this->siteId, Site::$ids, true) ? ['site_id' => $this->siteId] : []);
        }
    }
    class Site {
        public static array $ids = [1, 2];
        public static int $locks = 0;
        public static mixed $onLock = null;
        public static function where(array $conditions): SiteQuery { return new SiteQuery($conditions[0][2]); }
    }
}
namespace app\service\core\site {
    class CoreSiteService {
        public static array $addons = [1 => ['hsx_erp'], 2 => []];
        public static bool $fail = false;
        public function getAddonKeysBySiteId(int $siteId): array {
            if (self::$fail) throw new \RuntimeException('installed lookup unavailable');
            return self::$addons[$siteId] ?? [];
        }
    }
}
namespace think\facade {
    class Cache {
        public static int $clears = 0;
        public static int $afterCommitClears = 0;
        public static function tag(string $tag): self { return new self(); }
        public function clear(): void { self::$clears++; if (!Db::$active) self::$afterCommitClears++; }
    }
    class Db {
        public static bool $active = false;
        public static function transaction(callable $callback): mixed {
            $snapshot = \app\model\sys\SysConfig::$rows;
            self::$active = true;
            try { return $callback(); }
            catch (\Throwable $error) { \app\model\sys\SysConfig::$rows = $snapshot; throw $error; }
            finally { self::$active = false; }
        }
    }
}
namespace app\service\core\sys {
    class CoreConfigService {
        public function __construct() {}
        public static string $cache_tag_name = 'sys_config';
        public static int $cacheReads = 0;
        public static int $creates = 0;
        public static int $updates = 0;
        public static bool $failSave = false;
        public function getConfig(int $site_id, string $key) { self::$cacheReads++; return []; }
        public function getConfigValue(int $site_id, string $key) { return $this->getConfig($site_id, $key)['value'] ?? []; }
        public function setConfig(int $site_id, string $key, array $value): bool {
            if (!\think\facade\Db::$active || \app\model\site\Site::$locks === 0) throw new \RuntimeException('write without site transaction lock');
            if (self::$failSave) return false;
            $existing = $this->getConfig($site_id, $key);
            $index = $site_id . ':' . $key;
            if ($existing === []) self::$creates++; else self::$updates++;
            \app\model\sys\SysConfig::$rows[$index] = ['id' => $site_id, 'site_id' => $site_id, 'config_key' => $key, 'value' => $value];
            \think\facade\Cache::tag(self::$cache_tag_name . $site_id)->clear();
            return true;
        }
    }
}
namespace {
    require dirname(__DIR__) . '/app/service/core/recycle_order/RecycleErpIntegrationService.php';
    require dirname(__DIR__) . '/app/support/RecycleErpOwnershipPolicy.php';
    use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpIntegrationService;
    use addon\hsx_recycle\app\support\RecycleErpOwnershipPolicy as Policy;
    use app\model\sys\SysConfig;
    use app\model\site\Site;
    use app\service\core\site\CoreSiteService;
    use app\service\core\sys\CoreConfigService;

    class TestIntegrationService extends RecycleErpIntegrationService {
        public int $clock = 100;
        protected function now(): int { return $this->clock; }
    }
    $count = 0;
    $assert = static function (bool $condition, string $message) use (&$count): void {
        if (!$condition) throw new \RuntimeException($message);
        $count++;
    };
    $throws = static function (callable $call, string $message) use ($assert): void {
        try { $call(); } catch (\Throwable $e) { $assert(str_contains($e->getMessage(), $message), 'unexpected exception: ' . $e->getMessage()); return; }
        throw new \RuntimeException('expected exception: ' . $message);
    };
    $service = new TestIntegrationService();
    $assert($service->get(1)['mode'] === 'self_erp' && !$service->get(1)['configured'], 'legacy installed site retains compatibility');
    $assert($service->get(2)['mode'] === 'local' && !$service->get(2)['installed'], 'standalone site stays local');
    $throws(fn() => $service->save(1, 'local', false), '请确认');
    $throws(fn() => $service->save(1, 'third_party', true), '不支持');
    $throws(fn() => $service->save(2, 'self_erp', true), '请先');
    $throws(fn() => $service->save(99, 'local', true), '站点不存在');
    $throws(fn() => $service->isInstalled(0), '无法确认');
    $assert(SysConfig::$rows === [], 'rejected settings never persist');

    $saved = $service->save(1, 'local', true);
    $history = $service->history(1);
    $assert($saved['mode'] === 'local' && $saved['configured'] && $saved['changed_at'] === 101, 'switch takes effect next second');
    $assert(str_contains($saved['message'], '预计生效时间') && str_contains($saved['message'], '新建'), 'settings explain future-device boundary');
    $assert($history['initial_mode'] === 'self_erp' && $history['installed'], 'first save freezes compatibility and includes installation status');
    $assert($history['history'] === [['at' => 101, 'mode' => 'local']], 'switch history is persisted');
    $assert(Policy::resolve($history, 100, '', [], true) === 'self_erp', 'existing same-second device retains ERP responsibility');
    $assert(Policy::resolve($history, 101, '', [], true) === 'local', 'new device follows new mode');
    $service->save(1, 'local', true);
    $assert(count($service->history(1)['history']) === 1 && CoreConfigService::$creates === 1, 'same-mode save neither duplicates history nor creates duplicate config');
    $service->save(1, 'self_erp', true);
    $assert($service->history(1)['history'][1] === ['at' => 102, 'mode' => 'self_erp'], 'same-second switches get strictly increasing cutoffs');
    $assert(CoreConfigService::$cacheReads === 0, 'all reads including setConfig existence checks bypass cache');

    // 模拟等待站点行锁期间另一位管理员已提交的新历史，必须在拿锁之后重新读取。
    Site::$onLock = static function (): void {
        $key = '1:' . RecycleErpIntegrationService::CONFIG_KEY;
        SysConfig::$rows[$key]['value']['history'][] = ['at' => 103, 'mode' => 'local'];
        SysConfig::$rows[$key]['value']['mode'] = 'local';
        SysConfig::$rows[$key]['value']['changed_at'] = 103;
    };
    $service->save(1, 'self_erp', true);
    $latest = $service->history(1);
    $assert(count($latest['history']) === 4 && $latest['history'][2]['at'] === 103 && $latest['history'][3]['at'] === 104, 'locked reread preserves preceding writer history');
    $assert(\think\facade\Cache::$afterCommitClears === 4, 'cache also invalidated after each committed save');

    $service->save(2, 'local', true);
    CoreSiteService::$addons[2] = ['hsx_erp'];
    $assert($service->get(2)['mode'] === 'local' && $service->get(2)['installed'], 'installation after explicit local setting does not seize payment ownership');
    $assert($service->history(2)['initial_mode'] === 'local' && $service->history(2)['history'] === [], 'same-mode first configuration freezes initial responsibility');
    CoreSiteService::$addons[1] = [];
    $assert($service->get(1)['mode'] === 'self_erp' && !$service->get(1)['installed'], 'uninstall does not rewrite saved ERP owner');
    $assert(str_contains($service->get(1)['message'], '不会改由本地付款'), 'missing ERP is surfaced as recovery need');
    CoreSiteService::$fail = true;
    $throws(fn() => $service->isInstalled(1), 'installed lookup unavailable');
    $throws(fn() => $service->history(1), 'installed lookup unavailable');
    CoreSiteService::$fail = false;
    SysConfig::$failRead = true;
    $throws(fn() => $service->get(1), 'configuration lookup unavailable');
    SysConfig::$failRead = false;
    CoreConfigService::$failSave = true;
    $before = SysConfig::$rows;
    $throws(fn() => $service->save(1, 'local', true), '保存失败');
    $assert(SysConfig::$rows === $before, 'save failure retains full previous history');
    CoreConfigService::$failSave = false;
    SysConfig::$rows['1:' . RecycleErpIntegrationService::CONFIG_KEY]['value']['history'][0]['at'] = 0;
    $throws(fn() => $service->get(1), '历史存在歧义');
    echo "[PASS] recycle ERP integration configuration: {$count} assertions; in-memory only\n";
}
