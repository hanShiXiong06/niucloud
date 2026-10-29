<?php
declare(strict_types=1);

// PHP8.0 独立回归：真实策略/服务 + 内存仓储/HTTP/存储/队列替身。不访问数据库或网络。
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\upload { class CoreFileService {} }
namespace think\facade { class Log { public static function warning(...$args): void {} public static function error(...$args): void {} } }
namespace {
    use addon\hsx_recycle\app\service\core\quote_image\HuishouBaojiaSource;
    use addon\hsx_recycle\app\service\core\quote_image\QuoteImageStorage;
    use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncPolicy as Policy;
    use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncRepository;
    use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncService;
    use core\exception\CommonException;

    foreach (['HuishouBaojiaSource', 'QuoteImageStorage', 'QuoteImageSyncPolicy', 'QuoteImageSyncRepository', 'QuoteImageSyncService'] as $class) {
        require __DIR__ . '/../app/service/core/quote_image/' . $class . '.php';
    }
    $checks = $failed = 0;
    function same($actual, $expected, string $message = ''): void {
        if ($actual !== $expected) throw new \RuntimeException($message . ' expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
    }
    function truth(bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); }
    function rejects(callable $operation, string $message = ''): void {
        try { $operation(); } catch (CommonException $e) {
            if ($message !== '' && strpos($e->getMessage(), $message) === false) throw new \RuntimeException('Wrong rejection: ' . $e->getMessage());
            return;
        }
        throw new \RuntimeException('Expected rejection: ' . $message);
    }
    function check(string $name, callable $operation): void {
        global $checks, $failed;
        $checks++;
        try { $operation(); echo 'PASS ' . $name . PHP_EOL; }
        catch (\Throwable $e) { $failed++; echo 'FAIL ' . $name . ': ' . $e->getMessage() . PHP_EOL; }
    }

    class MemoryQuoteRepository extends QuoteImageSyncRepository
    {
        public array $configs = [], $states = [], $rows = [], $history = [], $events = [];
        public int $writes = 0, $replacements = 0, $cacheClears = 0;
        public bool $inWork = false, $workBusy = false, $failCheckpointWrite = false;
        public function readConfig(int $siteId): array { return $this->configs[$siteId] ?? []; }
        public function readState(int $siteId): array { return $this->states[$siteId] ?? []; }
        public function writeConfig(int $siteId, array $value): void { $this->writes++; $this->configs[$siteId] = $value; }
        public function writeState(int $siteId, array $value): void {
            if ($this->failCheckpointWrite && !empty($value['checkpoints'])) { $this->failCheckpointWrite = false; throw new CommonException('模拟检查点写入失败'); }
            $this->writes++; $this->states[$siteId] = $value;
        }
        public function locked(int $siteId, string $kind, callable $operation): mixed { return $operation(); }
        public function work(int $siteId, string $runId, callable $operation): mixed {
            if ($this->workBusy || $this->inWork) return null;
            $this->events[] = ['work.start', $runId]; $this->inWork = true;
            try { return $operation(); }
            finally { $this->inWork = false; $this->events[] = ['work.release', $runId]; }
        }
        public function transaction(callable $operation): mixed {
            $snapshot = [$this->configs, $this->states, $this->rows, $this->history, $this->replacements];
            try { return $operation(); }
            catch (\Throwable $e) { [$this->configs, $this->states, $this->rows, $this->history, $this->replacements] = $snapshot; throw $e; }
        }
        public function categories(int $siteId): array { return array_values($this->rows[$siteId] ?? []); }
        public function category(int $siteId, int $id, bool $forUpdate = false): array {
            if (!isset($this->rows[$siteId][$id])) throw new CommonException('报价分类已删除或不属于本站，请重新设置映射');
            return $this->rows[$siteId][$id];
        }
        public function replaceImages(int $siteId, int $id, string $expectedImages, string $newImages, string $remark, int $uid, string $operator): void {
            $row = $this->category($siteId, $id, true);
            if ($row['images'] !== $expectedImages) throw new CommonException('该分类报价已被人工修改，未覆盖现有图片');
            if ($newImages === '') throw new CommonException('未取得有效报价图片');
            $latest = null;
            foreach ($this->history as $entry) if ($entry['site_id'] === $siteId && $entry['category_id'] === $id) $latest = $entry['images'];
            $base = ['site_id' => $siteId, 'category_id' => $id, 'uid' => $uid, 'operator' => $operator];
            if ($row['images'] !== '' && $latest !== $row['images']) $this->history[] = $base + ['images' => $row['images'], 'remark' => '采集前备份'];
            $this->rows[$siteId][$id]['images'] = $newImages;
            $this->history[] = $base + ['images' => $newImages, 'remark' => $remark];
            $this->replacements++;
        }
        public function clearCategoryCache(int $siteId): void { $this->cacheClears++; }
        public function configuredSites(): array { return array_keys($this->configs); }
    }
    class MemoryQuoteSource extends HuishouBaojiaSource
    {
        public array $catalog = [], $failures = [], $versions = [], $fetches = [];
        public int $discoveries = 0;
        public $onFetch;
        public function discover(string $sourceUrl): array { $this->discoveries++; return $this->catalog; }
        public function fetchQuoteImages(string $sourceUrl, string $sourceId): array {
            $this->fetches[] = [$sourceUrl, $sourceId];
            if ($this->onFetch) ($this->onFetch)($sourceId);
            if (isset($this->failures[$sourceId])) throw new CommonException($this->failures[$sourceId]);
            $bytes = 'fixture-' . $sourceId . '-' . ($this->versions[$sourceId] ?? 'v1');
            return [['url' => 'https://cos.huishoubaojiadan.com/quotation/' . $sourceId . '.png',
                'hash' => hash('sha256', $bytes), 'bytes' => $bytes, 'mime' => 'image/png', 'width' => 1, 'height' => 1]];
        }
    }
    class MemoryQuoteStorage extends QuoteImageStorage
    {
        public int $attempts = 0;
        public array $stored = [];
        public bool $fail = false;
        public $onStore;
        public function store(int $siteId, array $image): string {
            $this->attempts++;
            if ($this->onStore) ($this->onStore)($siteId, $image);
            if ($this->fail) throw new CommonException('模拟本站存储失败');
            $url = 'upload/fixture/' . $siteId . '/' . $this->attempts . '-' . substr($image['hash'], 0, 8) . '.png';
            $this->stored[] = [$siteId, $url, $image['bytes']]; return $url;
        }
    }
    class SyncHarness
    {
        public const SITE = 100005;
        public MemoryQuoteRepository $repo;
        public MemoryQuoteSource $source;
        public MemoryQuoteStorage $storage;
        public QuoteImageSyncService $service;
        public array $queue = [];
        public int $now, $queueAttempts = 0, $failQueueAt = 0, $lockViolations = 0;
        public bool $concurrentAdmin = false;
        public static array $all = [];
        public function __construct(int $count = 1, bool $enabled = false, bool $queueEnabled = true) {
            $this->now = (new \DateTimeImmutable('2026-10-28 10:00:00', new \DateTimeZone('Asia/Shanghai')))->getTimestamp();
            $this->repo = new MemoryQuoteRepository(); $this->source = new MemoryQuoteSource(); $this->storage = new MemoryQuoteStorage();
            $mappings = [];
            for ($i = 0; $i < $count; $i++) {
                $source = (string)(16576 + $i); $id = 101 + $i;
                $this->source->catalog[] = ['id' => $source, 'name' => '报价' . $i, 'group' => '手机', 'url' => HuishouBaojiaSource::DEFAULT_SOURCE_URL . $source . '.html'];
                $this->repo->rows[self::SITE][$id] = ['category_id' => $id, 'site_id' => self::SITE, 'category_name' => '分类' . $i,
                    'category_full_name' => '手机/分类' . $i, 'images' => 'old-' . $id . '.png'];
                $mappings[] = ['source_id' => $source, 'category_id' => $id, 'enabled' => 1];
            }
            $this->repo->configs[self::SITE] = Policy::normalize(['enabled' => $enabled ? 1 : 0, 'run_time' => '09:30', 'mappings' => $mappings],
                $this->source->catalog, $this->repo->categories(self::SITE)) + ['revision' => 1];
            $this->repo->configs[self::SITE]['revision'] = 1;
            $this->service = new QuoteImageSyncService($this->repo, $this->source, $this->storage,
                function (int $site, string $run): bool {
                    $this->queueAttempts++;
                    if ($this->repo->inWork && !$this->concurrentAdmin) { $this->lockViolations++; throw new \RuntimeException('仍持执行锁时投递下一项'); }
                    if ($this->failQueueAt === $this->queueAttempts) throw new \RuntimeException('fixture enqueue failure');
                    $this->repo->events[] = ['enqueue', $run]; $this->queue[] = [$site, $run]; return true;
                }, $queueEnabled, fn(): int => $this->now);
            self::$all[] = $this;
        }
        public function start(): array { return $this->service->start(self::SITE, 'manual', 7, '测试管理员'); }
        public function step(): void { $task = array_shift($this->queue); if ($task) $this->service->step($task[0], $task[1]); }
        public function drain(): void { for ($i = 0; $this->queue && $i < 100; $i++) $this->step(); truth(!$this->queue, '队列未能结束'); }
        public function state(): array { return $this->repo->readState(self::SITE); }
        public function run(): array { return $this->state()['status'] ?? []; }
        public function config(): array { return $this->repo->readConfig(self::SITE); }
        public function image(int $id = 101): string { return $this->repo->category(self::SITE, $id)['images']; }
        public function saveConfig(array $changes): array { return $this->service->save(self::SITE, array_replace($this->config(), $changes)); }
    }

    check('队列未开启，手动运行拒绝且不写状态', static function (): void {
        $h = new SyncHarness(1, false, false); rejects(fn() => $h->start(), '队列'); same($h->repo->writes, 0); same($h->queueAttempts, 0);
    });
    check('队列未开启，不允许启用自动同步', static function (): void {
        $h = new SyncHarness(1, false, false); rejects(fn() => $h->saveConfig(['enabled' => 1]), '队列'); same($h->repo->writes, 0);
    });
    check('映射不能指向其他站点或公共分类', static function (): void {
        $h = new SyncHarness(); $h->repo->rows[100006][999] = ['category_id' => 999, 'site_id' => 100006, 'category_name' => '外站', 'images' => 'private.png'];
        rejects(fn() => $h->saveConfig(['mappings' => [['source_id' => '16576', 'category_id' => 999, 'enabled' => 1]]]), '本站'); same($h->repo->writes, 0);
    });
    check('启用映射不能重复占用同一目标分类', static function (): void {
        $h = new SyncHarness(2); rejects(fn() => $h->saveConfig(['mappings' => [
            ['source_id' => '16576', 'category_id' => 101, 'enabled' => 1], ['source_id' => '16577', 'category_id' => 101, 'enabled' => 1],
        ]]), '同一个');
    });
    check('来源ID不能重复，暂停映射可不选目标', static function (): void {
        $h = new SyncHarness(2); rejects(fn() => $h->saveConfig(['mappings' => [
            ['source_id' => '16576', 'category_id' => 101], ['source_id' => '16576', 'category_id' => 102],
        ]]), '重复');
        $h->saveConfig(['mappings' => [['source_id' => '16576', 'category_id' => 0, 'enabled' => 0]]]); same(Policy::activeMappings($h->config()), []);
    });
    check('手动运行分步成功，转存原图、保留新旧历史、清缓存', static function (): void {
        $h = new SyncHarness(); $run = $h->start(); same($run['status'], 'queued'); same($run['total'], 1); same($h->storage->attempts, 0);
        $h->drain(); same($h->run()['status'], 'success'); same($h->run()['updated'], 1); same($h->run()['done'], 1);
        same($h->repo->replacements, 1); same(count($h->repo->history), 2); same($h->repo->history[0]['images'], 'old-101.png');
        same($h->repo->history[1]['images'], $h->image()); same($h->repo->cacheClears, 1); same($h->repo->history[1]['uid'], 7);
        truth(isset($h->state()['checkpoints']['16576:101']), '未保存成功检查点');
    });
    check('运行中重复点击沿用同一个run且只投递一次', static function (): void {
        $h = new SyncHarness(); $a = $h->start(); $b = $h->start(); same($a['run_id'], $b['run_id']); same($h->queueAttempts, 1);
    });
    check('完成后迟到的重复step不会再次上传或写历史', static function (): void {
        $h = new SyncHarness(); $run = $h->start(); $h->drain(); $h->service->step(SyncHarness::SITE, $run['run_id']);
        same($h->repo->replacements, 1); same($h->storage->attempts, 1); same(count($h->repo->history), 2);
    });
    check('相同图片内容hash不重复上传或产生新版本', static function (): void {
        $h = new SyncHarness(); $h->start(); $h->drain(); $before = $h->image(); $h->start(); $h->drain();
        same($h->run()['status'], 'success'); same($h->run()['unchanged'], 1); same($h->run()['updated'], 0);
        same($h->storage->attempts, 1); same($h->repo->replacements, 1); same($h->image(), $before); same(count($h->repo->history), 2);
    });
    check('来源图片变更后生成新版本，不覆盖原文件', static function (): void {
        $h = new SyncHarness(); $h->start(); $h->drain(); $old = $h->image(); $h->source->versions['16576'] = 'v2'; $h->start(); $h->drain();
        truth($h->image() !== $old, '新版本图片路径未变化'); same($h->run()['updated'], 1); same($h->storage->attempts, 2); same(count($h->repo->history), 3);
    });
    check('来源失败保留旧报价，不调用存储、不写版本', static function (): void {
        $h = new SyncHarness(); $h->source->failures['16576'] = 'fixture来源维护'; $h->start(); $h->drain();
        same($h->image(), 'old-101.png'); same($h->run()['status'], 'failed'); same($h->run()['failed'], 1); same($h->storage->attempts, 0); same($h->repo->history, []);
    });
    check('存储失败保留旧报价，不生成检查点或历史', static function (): void {
        $h = new SyncHarness(); $h->storage->fail = true; $h->start(); $h->drain(); same($h->image(), 'old-101.png');
        same($h->run()['status'], 'failed'); same($h->repo->history, []); same($h->state()['checkpoints'] ?? [], []);
    });
    check('多映射一项失败，其他项仍完成并显示部分成功', static function (): void {
        $h = new SyncHarness(2); $h->source->failures['16577'] = '第二栏目不可用'; $h->start(); $h->drain();
        same($h->run()['status'], 'partial'); same($h->run()['done'], 2); same($h->run()['updated'], 1); same($h->run()['failed'], 1);
        truth($h->image() !== 'old-101.png', '成功项未更新'); same($h->image(102), 'old-102.png');
    });
    check('完成项后取消，只跳过剩余项而不撤回已完成结果', static function (): void {
        $h = new SyncHarness(2); $run = $h->start(); $h->step(); $first = $h->image(); $h->service->cancel(SyncHarness::SITE, $run['run_id']); $h->drain();
        same($h->run()['status'], 'cancelled'); same($h->image(), $first); same($h->image(102), 'old-102.png'); same($h->storage->attempts, 1);
    });
    check('取消后旧队列迟到不影响新run', static function (): void {
        $h = new SyncHarness(); $old = $h->start(); $h->service->cancel(SyncHarness::SITE, $old['run_id']); $new = $h->start();
        truth($old['run_id'] !== $new['run_id'], '取消后没有新run'); $h->step(); same($h->run()['run_id'], $new['run_id']); same($h->storage->attempts, 0);
        $h->drain(); same($h->run()['status'], 'success'); same($h->repo->replacements, 1);
    });
    check('下载期间取消与新run建立后，旧worker不能回写', static function (): void {
        $h = new SyncHarness(); $old = $h->start(); $new = [];
        $h->source->onFetch = function () use ($h, $old, &$new): void {
            $h->source->onFetch = null; $h->service->cancel(SyncHarness::SITE, $old['run_id']);
            // 模拟另一个管理员请求；新run投递不属于旧worker的后继任务投递。
            $h->concurrentAdmin = true; try { $new = $h->start(); } finally { $h->concurrentAdmin = false; }
        };
        $h->step(); same($h->image(), 'old-101.png'); same($h->run()['run_id'], $new['run_id']); same($h->run()['done'], 0); same($h->repo->history, []);
        $h->drain(); same($h->run()['status'], 'success'); same($h->repo->replacements, 1);
    });
    check('人工改图后自动暂停映射，保留人工结果', static function (): void {
        $h = new SyncHarness(); $h->start(); $h->drain(); $h->repo->rows[SyncHarness::SITE][101]['images'] = 'manual.png';
        $h->start(); $h->drain(); same($h->run()['status'], 'failed'); same($h->config()['mappings'][0]['enabled'], 0);
        same($h->image(), 'manual.png'); same($h->storage->attempts, 1);
    });
    check('管理员核对并重新启用后，可以再次同步人工改过的分类', static function (): void {
        $h = new SyncHarness(); $h->start(); $h->drain(); $h->repo->rows[SyncHarness::SITE][101]['images'] = 'manual.png'; $h->start(); $h->drain();
        $mappings = $h->config()['mappings']; $mappings[0]['enabled'] = 1; $h->saveConfig(['mappings' => $mappings]); $h->start(); $h->drain();
        same($h->run()['status'], 'success'); truth($h->image() !== 'manual.png', '重新启用后仍无法同步'); same($h->config()['mappings'][0]['enabled'], 1);
    });
    check('采集过程中人工修改，事务检查阻止覆盖并暂停映射', static function (): void {
        $h = new SyncHarness(); $h->source->onFetch = static function () use ($h): void { $h->repo->rows[SyncHarness::SITE][101]['images'] = 'manual-during.png'; };
        $h->start(); $h->drain(); same($h->image(), 'manual-during.png'); same($h->run()['status'], 'failed'); same($h->config()['mappings'][0]['enabled'], 0); same($h->repo->history, []);
    });
    check('正在运行时拒绝保存配置，原配置和run不变', static function (): void {
        $h = new SyncHarness(); $run = $h->start(); $config = $h->config(); $writes = $h->repo->writes;
        rejects(fn() => $h->saveConfig(['run_time' => '12:00']), '正在进行'); same($h->config(), $config); same($h->run()['run_id'], $run['run_id']); same($h->repo->writes, $writes);
    });
    check('每日到点只自动执行一次，未到点不运行', static function (): void {
        $h = new SyncHarness(1, true); $h->now = Policy::todayAt($h->config(), $h->now) - 1;
        same($h->service->tick()['started'], 0); $h->now++; same($h->service->tick()['started'], 1); $h->drain();
        same($h->service->tick()['started'], 0); same($h->queueAttempts, 1);
        $h->now += 86400 - 1; same($h->service->tick()['started'], 0); $h->now++; same($h->service->tick()['started'], 1); $h->drain(); same($h->queueAttempts, 2);
    });
    check('关闭自动同步后不自动执行，但仍允许手动采集', static function (): void {
        $h = new SyncHarness(1, false); same($h->service->tick()['started'], 0); same($h->queueAttempts, 0);
        $h->start(); $h->drain(); same($h->run()['status'], 'success'); same($h->config()['enabled'], 0);
    });
    check('首次投递失败形成明确失败终态，不改报价', static function (): void {
        $h = new SyncHarness(); $h->failQueueAt = 1; rejects(fn() => $h->start(), '队列投递失败');
        same($h->run()['status'], 'failed'); same($h->image(), 'old-101.png'); same(count($h->state()['recent_runs']), 1); same($h->storage->attempts, 0);
    });
    check('后继投递失败保留已完成项，未完成项不改图', static function (): void {
        $h = new SyncHarness(2); $h->failQueueAt = 2; $h->start(); rejects(fn() => $h->step(), '队列投递失败');
        same($h->run()['status'], 'failed'); same($h->run()['done'], 1); truth($h->image() !== 'old-101.png', '已完成项丢失'); same($h->image(102), 'old-102.png');
    });
    check('30分钟无进度自动终止，旧worker无法再更新', static function (): void {
        $h = new SyncHarness(); $run = $h->start(); $h->now += 1801; $status = $h->service->status(SyncHarness::SITE);
        same($status['status']['status'], 'failed'); $h->service->step(SyncHarness::SITE, $run['run_id']); same($h->storage->attempts, 0); same($h->image(), 'old-101.png');
    });
    check('执行锁释放后才投递下一项，避免后继消息被锁忙吞掉', static function (): void {
        $h = new SyncHarness(2); $h->start(); $h->step(); same($h->queueAttempts, 2); same($h->lockViolations, 0);
        $names = array_column($h->repo->events, 0); same(array_slice($names, -2), ['work.release', 'enqueue']); $h->drain(); same($h->run()['status'], 'success');
    });
    check('取得执行锁后恢复worker崩溃遗留的running项', static function (): void {
        $h = new SyncHarness(); $run = $h->start(); $state = $h->state(); $state['status']['status'] = 'running'; $state['status']['items'][0]['status'] = 'running';
        $h->repo->states[SyncHarness::SITE] = $state; $h->step(); same($h->run()['status'], 'success'); same($h->run()['done'], 1); same($h->repo->replacements, 1);
    });
    check('重复step拿不到执行锁时不写状态也不并行上传', static function (): void {
        $h = new SyncHarness(); $run = $h->start(); $before = $h->state(); $h->repo->workBusy = true;
        $h->service->step(SyncHarness::SITE, $run['run_id']); same($h->state(), $before); same($h->storage->attempts, 0); same($h->queueAttempts, 1);
    });
    check('检查点写入失败时图片和历史一起回滚，不产生半成功账面状态', static function (): void {
        $h = new SyncHarness(); $h->repo->failCheckpointWrite = true; $h->start(); $h->drain();
        same($h->run()['status'], 'failed'); same($h->image(), 'old-101.png'); same($h->repo->history, []); same($h->state()['checkpoints'] ?? [], []);
    });
    check('运行期间配置修订变化会阻止旧配置写入', static function (): void {
        $h = new SyncHarness(); $h->source->onFetch = static function () use ($h): void { $h->repo->configs[SyncHarness::SITE]['revision']++; };
        $h->start(); $h->drain(); same($h->run()['status'], 'failed'); same($h->image(), 'old-101.png'); same($h->repo->history, []);
    });
    check('所有模拟worker均未在持执行锁时投递后继任务', static function (): void {
        foreach (SyncHarness::$all as $h) same($h->lockViolations, 0);
    });
    echo '完成：' . $checks . ' 个服务/策略场景，失败 ' . $failed . '；无数据库、网络、真实存储或通知调用。' . PHP_EOL;
    exit($failed ? 1 : 0);
}
