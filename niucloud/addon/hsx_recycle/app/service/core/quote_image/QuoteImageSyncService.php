<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\quote_image;

use addon\hsx_recycle\app\job\schedule\QuoteImageSyncStep;
use core\exception\CommonException;
use think\facade\Log;

/** 每个队列任务仅处理一个栏目，状态持久化到已有配置表，跨进程可查询/取消。 */
class QuoteImageSyncService
{
    private QuoteImageSyncRepository $repo;
    private HuishouBaojiaSource $source;
    private QuoteImageStorage $storage;
    private $enqueue;
    private $clock;
    private ?bool $queueOverride;

    public function __construct(?QuoteImageSyncRepository $repo = null, ?HuishouBaojiaSource $source = null,
        ?QuoteImageStorage $storage = null, ?callable $enqueue = null, ?bool $queueEnabled = null, ?callable $clock = null)
    {
        $this->repo = $repo ?? new QuoteImageSyncRepository();
        $this->source = $source ?? new HuishouBaojiaSource();
        $this->storage = $storage ?? new QuoteImageStorage();
        $this->enqueue = $enqueue ?? static fn(int $site, string $run) => QuoteImageSyncStep::dispatch([$site, $run]);
        $this->queueOverride = $queueEnabled;
        $this->clock = $clock ?? static fn(): int => time();
    }
    private function now(): int { return ($this->clock)(); }
    public function queueEnabled(): bool { return $this->queueOverride ?? (bool)env('queue.state', false); }
    private function config(int $site): array { return array_replace(QuoteImageSyncPolicy::defaults(), $this->repo->readConfig($site)); }
    private function state(int $site): array { return array_replace(['status' => ['status' => 'idle'], 'recent_runs' => [], 'checkpoints' => [], 'last_auto_day' => ''], $this->repo->readState($site)); }

    public function view(int $site): array
    {
        $config = $this->config($site);
        $status = $this->status($site);
        $config['category_options'] = array_map(static fn(array $row): array => ['value' => (int)$row['category_id'],
            'label' => (string)($row['category_full_name'] ?: $row['category_name'])], $this->repo->categories($site));
        unset($config['revision']);
        return array_replace($config, $status);
    }

    public function discover(string $url): array
    {
        $url = HuishouBaojiaSource::normalizeSourceUrl($url);
        return ['source_url' => $url, 'items' => $this->source->discover($url)];
    }

    public function save(int $site, array $input): array
    {
        $old = $this->config($site);
        $url = HuishouBaojiaSource::normalizeSourceUrl((string)($input['source_url'] ?? $old['source_url']));
        $catalog = $url === $old['source_url'] ? $old['source_catalog'] : [];
        $ids = array_column(is_array($input['mappings'] ?? null) ? $input['mappings'] : [], 'source_id');
        if ($ids && (!$catalog || array_diff(array_map('strval', $ids), array_map('strval', array_column($catalog, 'id'))))) $catalog = $this->source->discover($url);
        $config = QuoteImageSyncPolicy::normalize(array_replace($input, ['source_url' => $url]), $catalog, $this->repo->categories($site));
        if ($config['enabled'] && !$this->queueEnabled()) throw new CommonException('请先开启框架队列并运行队列消费者，再启用自动采集；配置可先关闭自动同步后保存');
        $this->repo->locked($site, 'state', function () use ($site, $config): void {
            $state = $this->state($site);
            if (QuoteImageSyncPolicy::active($state['status'])) throw new CommonException('采集正在进行，请先停止本次，再保存配置');
            $old = $this->config($site);
            $next = $config;
            $next['revision'] = (int)$old['revision'] + 1;
            $allowed = array_fill_keys(array_map([QuoteImageSyncPolicy::class, 'checkpointKey'], $next['mappings']), true);
            $state['checkpoints'] = $next['source_url'] === $old['source_url'] ? array_intersect_key($state['checkpoints'], $allowed) : [];
            $oldMappings = [];
            foreach ($old['mappings'] as $mapping) $oldMappings[QuoteImageSyncPolicy::checkpointKey($mapping)] = $mapping;
            foreach ($next['mappings'] as $mapping) {
                $key = QuoteImageSyncPolicy::checkpointKey($mapping);
                // 明确重新启用意味着管理员已核对人工版本，允许以当前图片为基线重新接管。
                if ($mapping['enabled'] && isset($oldMappings[$key]) && !$oldMappings[$key]['enabled']) unset($state['checkpoints'][$key]);
            }
            $this->repo->transaction(function () use ($site, $next, $state): void { $this->repo->writeConfig($site, $next); $this->repo->writeState($site, $state); });
        });
        return $this->view($site);
    }

    public function status(int $site): array
    {
        $state = $this->state($site);
        if (QuoteImageSyncPolicy::active($state['status']) && $this->now() - (int)($state['status']['heartbeat_at'] ?? $state['status']['started_at'] ?? 0) > 1800) {
            $this->repo->locked($site, 'state', function () use ($site): void {
                $state = $this->state($site);
                if (QuoteImageSyncPolicy::active($state['status']) && $this->now() - (int)($state['status']['heartbeat_at'] ?? $state['status']['started_at'] ?? 0) > 1800) {
                    $state['status']['status'] = 'failed';
                    $state['status']['message'] = '任务超过30分钟没有进展，已停止；请检查队列消费者、网络和存储，再重新采集';
                    $this->finish($state);
                    $this->repo->writeState($site, $state);
                }
            });
            $state = $this->state($site);
        }
        $config = $this->config($site);
        $next = QuoteImageSyncPolicy::nextAt($config, $state, $this->now());
        $run = $state['status'];
        unset($run['revision'], $run['operator_uid'], $run['operator_name'], $run['source_url'], $run['heartbeat_at']);
        $run['next_run_at'] = $next;
        $successTimes = array_column($state['checkpoints'], 'last_success_at');
        $run['last_success_at'] = $successTimes ? max($successTimes) : 0;
        return ['status' => $run, 'recent_runs' => $state['recent_runs'], 'queue_enabled' => $this->queueEnabled(), 'next_run_at' => $next];
    }

    public function start(int $site, string $type = 'manual', int $uid = 0, string $operator = '系统自动采集'): array
    {
        if (!$this->queueEnabled()) throw new CommonException('框架异步队列尚未开启，未开始采集；请开启队列并启动消费者');
        $this->status($site);
        $run = $this->repo->locked($site, 'state', function () use ($site, $type, $uid, $operator): array {
            $config = $this->config($site);
            $state = $this->state($site);
            if (QuoteImageSyncPolicy::active($state['status'])) return $state['status'] + ['already_running' => true];
            if ($type === 'auto' && (empty($config['enabled']) || $this->now() < QuoteImageSyncPolicy::todayAt($config, $this->now()) || $state['last_auto_day'] === QuoteImageSyncPolicy::day($this->now()))) return [];
            $mappings = QuoteImageSyncPolicy::activeMappings($config);
            if (!$mappings) throw new CommonException('请先保存并启用至少一条分类映射');
            $run = ['run_id' => bin2hex(random_bytes(12)), 'status' => 'queued', 'type' => $type,
                'message' => '已加入队列，等待消费者开始；若长时间不动，请检查队列服务',
                'total' => count($mappings), 'done' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0,
                'started_at' => $this->now(), 'heartbeat_at' => $this->now(), 'finished_at' => 0,
                'revision' => $config['revision'], 'source_url' => $config['source_url'], 'operator_uid' => $uid,
                'operator_name' => $operator, 'items' => array_map(static fn(array $row): array => $row + ['status' => 'pending', 'message' => '等待采集'], $mappings)];
            $state['status'] = $run;
            if ($type === 'auto') $state['last_auto_day'] = QuoteImageSyncPolicy::day($this->now());
            $this->repo->writeState($site, $state);
            return $run;
        });
        if (!$run) return [];
        if (empty($run['already_running'])) $this->push($site, $run['run_id']);
        return $this->status($site)['status'];
    }

    public function cancel(int $site, string $runId): array
    {
        $this->repo->locked($site, 'state', function () use ($site, $runId): void {
            $state = $this->state($site);
            if (($state['status']['run_id'] ?? '') !== $runId || !QuoteImageSyncPolicy::active($state['status'])) return;
            $state['status']['status'] = 'cancelled';
            $state['status']['message'] = '已停止未完成栏目，已经同步成功的图片保留，不撤回';
            foreach ($state['status']['items'] as &$item) if (in_array($item['status'], ['pending', 'running'], true)) { $item['status'] = 'skipped'; $item['message'] = '本次已停止'; }
            unset($item);
            $this->finish($state);
            $this->repo->writeState($site, $state);
        });
        return $this->status($site);
    }

    /** 一次只处理一项；重投、重复点击和取消后迟到的任务均不能覆盖新任务。 */
    public function step(int $site, string $runId): void
    {
        $continue = $this->repo->work($site, $runId, function () use ($site, $runId): bool {
            $claim = $this->repo->locked($site, 'state', function () use ($site, $runId): array {
                $state = $this->state($site);
                if (!$this->current($state, $runId)) return [];
                // 已拿到独占执行锁，遗留 running 表示上个消费者异常退出；重投可安全重领。
                // 图片与检查点同事务提交，提交过的项目已是 updated/unchanged，不会在此重做。
                foreach ($state['status']['items'] as &$unfinished) if ($unfinished['status'] === 'running') {
                    $unfinished['status'] = 'pending';
                    $unfinished['message'] = '消费者恢复，重新核实此栏目';
                }
                unset($unfinished);
                foreach ($state['status']['items'] as $index => $item) {
                    if ($item['status'] !== 'pending') continue;
                    $state['status']['status'] = 'running';
                    $state['status']['message'] = '正在采集：' . $item['source_name'];
                    $state['status']['heartbeat_at'] = $this->now();
                    $state['status']['items'][$index]['status'] = 'running';
                    $this->repo->writeState($site, $state);
                    return ['index' => $index, 'item' => $item, 'run' => $state['status'], 'checkpoint' => $state['checkpoints'][QuoteImageSyncPolicy::checkpointKey($item)] ?? []];
                }
                return [];
            });
            if (!$claim) return false;
            $index = $claim['index']; $item = $claim['item']; $run = $claim['run'];
            try {
                $category = $this->repo->category($site, (int)$item['category_id']);
                $before = (string)$category['images'];
                $checkpoint = $claim['checkpoint'];
                if ($checkpoint && ($checkpoint['images_hash'] ?? '') !== QuoteImageSyncPolicy::localHash($before)) throw new CommonException('本站报价已被人工修改，已暂停此映射；请核对后重新启用');
                $images = $this->source->fetchQuoteImages($run['source_url'], $item['source_id']);
                if (!$images) throw new CommonException('来源没有有效报价图片，原报价已保留');
                $hash = hash('sha256', implode('|', array_column($images, 'hash')));
                $unchanged = $checkpoint && ($checkpoint['hash'] ?? '') === $hash;
                $urls = [];
                if (!$unchanged) foreach ($images as $image) $urls[] = $this->storage->store($site, $image);
                $this->repo->locked($site, 'state', function () use ($site, $runId, $index, $item, $run, $before, $hash, $unchanged, $urls): void {
                    $state = $this->state($site);
                    if (!$this->current($state, $runId)) return;
                    $config = $this->config($site);
                    if ((int)$config['revision'] !== (int)$run['revision'] || $config['source_url'] !== $run['source_url']) throw new CommonException('配置已变化，本项未写入，请按新配置重新采集');
                    $matched = array_filter(QuoteImageSyncPolicy::activeMappings($config), static fn(array $row): bool => $row['source_id'] === $item['source_id'] && (int)$row['category_id'] === (int)$item['category_id']);
                    if (!$matched) throw new CommonException('此映射已暂停，本项未写入');
                    $this->repo->transaction(function () use ($site, &$state, $index, $item, $run, $before, $hash, $unchanged, $urls): void {
                        $category = $this->repo->category($site, (int)$item['category_id'], true);
                        if ((string)$category['images'] !== $before) throw new CommonException('本站报价在采集期间被人工修改，已暂停此映射，原报价已保留');
                        $newImages = $unchanged ? $before : implode(',', $urls);
                        if (!$unchanged) $this->repo->replaceImages($site, (int)$item['category_id'], $before, $newImages,
                            '自动采集：' . $item['source_name'], (int)$run['operator_uid'], (string)$run['operator_name']);
                        $state['checkpoints'][QuoteImageSyncPolicy::checkpointKey($item)] = ['hash' => $hash,
                            'images_hash' => QuoteImageSyncPolicy::localHash($newImages), 'last_success_at' => $this->now()];
                        $this->itemResult($state, $index, $unchanged ? 'unchanged' : 'updated', $unchanged ? '图片内容未变化，未重复保存' : '报价图片已更新并保存历史版本');
                        $this->repo->writeState($site, $state);
                    });
                });
                // 即使未变化也尝试清缓存，修复上次更新后短暂的缓存服务故障。
                try { $this->repo->clearCategoryCache($site); } catch (\Throwable $e) { Log::warning('报价采集缓存刷新失败', ['site_id' => $site]); }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->repo->locked($site, 'state', function () use ($site, $runId, $index, $item, $message): void {
                    $state = $this->state($site);
                    if (!$this->current($state, $runId)) return;
                    if (strpos($message, '人工修改') !== false) {
                        $config = $this->config($site);
                        foreach ($config['mappings'] as &$mapping) if ($mapping['source_id'] === $item['source_id'] && (int)$mapping['category_id'] === (int)$item['category_id']) $mapping['enabled'] = 0;
                        unset($mapping);
                        $this->repo->writeConfig($site, $config);
                    }
                    $this->itemResult($state, $index, 'failed', $this->safeMessage($message));
                    $this->repo->writeState($site, $state);
                });
            }
            $state = $this->state($site);
            return $this->current($state, $runId);
        });
        // 必须先释放执行锁再投递下一项，避免新消费者抢到任务却因锁忙直接丢掉。
        if ($continue) $this->push($site, $runId);
    }

    public function tick(): array
    {
        $result = ['started' => 0, 'failed' => 0];
        if (!$this->queueEnabled()) return $result + ['message' => '异步队列未开启，未发起自动采集'];
        foreach ($this->repo->configuredSites() as $site) {
            try {
                $config = $this->config((int)$site);
                if (!$config['enabled']) continue;
                $this->status((int)$site);
                $state = $this->state((int)$site);
                if (QuoteImageSyncPolicy::active($state['status']) || $state['last_auto_day'] === QuoteImageSyncPolicy::day($this->now()) || $this->now() < QuoteImageSyncPolicy::todayAt($config, $this->now())) continue;
                if ($this->start((int)$site, 'auto')) $result['started']++;
            } catch (\Throwable $e) { $result['failed']++; Log::error('报价图片自动采集未启动', ['site_id' => (int)$site, 'reason' => $this->safeMessage($e->getMessage())]); }
        }
        return $result;
    }

    private function current(array $state, string $id): bool { return ($state['status']['run_id'] ?? '') === $id && QuoteImageSyncPolicy::active($state['status']); }
    private function itemResult(array &$state, int $index, string $status, string $message): void
    {
        $run = &$state['status'];
        if (($run['items'][$index]['status'] ?? '') !== 'running') return;
        $run['items'][$index]['status'] = $status;
        $run['items'][$index]['message'] = $message;
        $run['done']++;
        $run[$status === 'updated' ? 'updated' : ($status === 'unchanged' ? 'unchanged' : 'failed')]++;
        $run['heartbeat_at'] = $this->now();
        if ($run['done'] >= $run['total']) {
            $run['status'] = $run['failed'] ? ($run['failed'] === $run['total'] ? 'failed' : 'partial') : 'success';
            $run['message'] = $run['failed'] ? '采集结束，失败项保留原报价，请查看原因后重试' : '采集完成，客户可查看最新报价';
            $this->finish($state);
        }
    }
    private function finish(array &$state): void
    {
        $run = &$state['status'];
        $run['finished_at'] = $this->now();
        $summary = array_intersect_key($run, array_flip(['run_id', 'status', 'type', 'message', 'total', 'done', 'updated', 'unchanged', 'failed', 'started_at', 'finished_at']));
        $state['recent_runs'] = array_slice(array_merge([$summary], array_values(array_filter($state['recent_runs'], static fn(array $row): bool => ($row['run_id'] ?? '') !== $run['run_id']))), 0, 10);
    }
    private function push(int $site, string $runId): void
    {
        try { if (($this->enqueue)($site, $runId) === false) throw new CommonException('队列投递失败'); }
        catch (\Throwable $e) {
            $this->repo->locked($site, 'state', function () use ($site, $runId): void {
                $state = $this->state($site);
                if (!$this->current($state, $runId)) return;
                $state['status']['status'] = 'failed';
                $state['status']['message'] = '队列投递失败，未完成栏目保留原报价；请检查Redis和消费者后重试';
                $this->finish($state);
                $this->repo->writeState($site, $state);
            });
            throw new CommonException('队列投递失败，请检查Redis和队列消费者；可在采集记录查看已完成项');
        }
    }
    private function safeMessage(string $message): string
    {
        $message = preg_replace('~https?://[^\s]+~u', '[来源地址]', $message) ?? '采集失败';
        return preg_match('/^.{0,180}/us', $message, $match) ? $match[0] : '采集失败，请查看系统日志';
    }
}
