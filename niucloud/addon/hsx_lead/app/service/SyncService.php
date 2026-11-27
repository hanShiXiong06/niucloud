<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\provider\{ProviderRegistry,ProviderException,ProviderDefinition};
use addon\hsx_lead\app\support\{Data,Cipher,SyncStatus};
use core\exception\CommonException;
use think\facade\Db;

/** Persistent one-page jobs: HTTP happens outside the DB transaction; only complete generations publish. */
final class SyncService
{
    private ProviderRegistry $providers;
    public function __construct(?ProviderRegistry $providers = null)
    {
        $this->providers = $providers ?? new ProviderRegistry();
    }

    public function start(int $site, int $merchantId, int $uid = 0, string $trigger = 'manual'): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $runId = Db::transaction(function () use ($site, $merchantId, $uid, $trigger) {
            $policy = new ProviderChannelService($site);
            $policy->lockSite();
            $merchant = Data::row('merchant', $site, $merchantId);
            if ((int) $merchant['status'] !== 1) throw new CommonException('商户已停用，不能同步');
            $conn = Data::query('connection', $site)->where('merchant_id', $merchantId)->lock(true)->find();
            if (!$conn || empty($conn['credentials_cipher'])) throw new CommonException('请先保存该商户的ERP账号配置');
            $policy->assertEnabled((string) $conn['provider']);
            if ($trigger === 'scheduled' && !(int) $conn['enabled']) throw new CommonException('自动同步已关闭，本轮不创建计划任务');
            if ((int) $conn['active_run_id'] > 0) {
                $active = Data::query('sync_run', $site)->where('id', $conn['active_run_id'])->find();
                if ($active && in_array($active['status'], ['pending', 'running'], true) && (int) $active['config_version'] === (int) $conn['config_version']) {
                    if (!$this->generationExpired($active, $conn)) return (int) $active['id'];
                    $this->expireGeneration($site, $active, $conn);
                }
            }
            $id = (int) Data::query('sync_run', $site)->insertGetId(['site_id' => $site, 'merchant_id' => $merchantId,
                'connection_id' => $conn['id'], 'config_version' => $conn['config_version'], 'trigger_type' => $trigger,
                'status' => 'pending', 'cursor_json' => Data::json(['page' => 1]), 'summary_json' => Data::json(['stores' => [], 'tenants' => []]),
                'create_time' => time(), 'update_time' => time()]);
            Data::query('connection', $site)->where('id', $conn['id'])->update(['active_run_id' => $id, 'last_sync_status' => 'pending',
                'last_sync_message' => '已开始全量同步，正在准备读取库存', 'next_sync_at' => time() + (int) $conn['sync_interval_minutes'] * 60,
                'lease_token' => '', 'lease_until' => 0, 'update_time' => time()]);
            Data::audit($site, $uid, 0, 'sync_run', $id, 'started', ['merchant_id' => $merchantId, 'trigger' => $trigger]);
            return $id;
        });
        return $this->detail($site, $runId);
    }

    public function detail(int $site, int $runId): array
    {
        $run = Data::row('sync_run', $site, $runId);
        $run['run_id'] = $run['id'];
        $connection = Data::row('connection', $site, (int) $run['connection_id']);
        $run = array_replace($run, SyncStatus::progress($run, (int) $connection['published_run_id'] === $runId));
        $summary = Data::decode($run['summary_json'] ?? null);
        if ($run['status'] === 'running' && is_string($summary['note'] ?? null) && $summary['note'] !== '') {
            $run['message'] = mb_substr($summary['note'], 0, 500).'（已取'.$run['page_count'].'页、'.$run['record_count'].'条设备）';
        }
        $run['scheduler_required'] = in_array($run['status'], ['pending', 'running'], true);
        $run['can_advance'] = $run['scheduler_required'] && (int) $connection['active_run_id'] === $runId && (int) $connection['config_version'] === (int) $run['config_version'];
        $provider = (string) $connection['provider'];
        $run['channel_enabled'] = $provider !== '' && (new ProviderChannelService($site))->enabled($provider);
        if (!$run['channel_enabled']) {
            $run['paused_reason'] = '本站已关闭该ERP渠道，采集已暂停；短暂停可继续原分页，超过库存有效窗口会重新完整同步。';
            if (in_array($run['status'], ['pending', 'running'], true)) {
                $run['stored_status'] = $run['status'];
                $run['status'] = 'paused';
                $run['message'] = $run['paused_reason'];
            }
            $run['scheduler_required'] = false;
            $run['can_advance'] = false;
        }
        if ((string) ($run['error_message'] ?? '') !== '') $run['error_message'] = \addon\hsx_lead\app\support\ProviderPublicMessage::forReason((string) ($run['error_code'] ?? ''));
        unset($run['cursor_json'],$run['summary_json']);
        return $run;
    }

    public function advance(int $site, int $runId): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $lease = bin2hex(random_bytes(16));
        $claim = Db::transaction(function () use ($site, $runId, $lease) {
            $policy = new ProviderChannelService($site);
            $policy->lockSite();
            $run = Data::row('sync_run', $site, $runId);
            $conn = Data::row('connection', $site, (int) $run['connection_id'], true);
            $run = Data::row('sync_run', $site, $runId, true);
            if (!in_array($run['status'], ['pending', 'running'], true)) return null;
            if (!$policy->enabled((string) $conn['provider'])) return null;
            if ((int) $conn['active_run_id'] !== $runId || (int) $conn['config_version'] !== (int) $run['config_version']) {
                Data::query('sync_run', $site)->where('id', $runId)->update(['status' => 'cancelled', 'finished_at' => time(), 'update_time' => time()]);
                return null;
            }
            if ((int) $conn['lease_until'] > time() && $conn['lease_token'] !== '') return null;
            if ($this->generationExpired($run, $conn)) {
                $this->expireGeneration($site, $run, $conn);
                return null;
            }
            if ((int) Data::row('merchant', $site, (int) $run['merchant_id'])['status'] !== 1) {
                Data::query('sync_run', $site)->where('id', $runId)->update(['status' => 'cancelled', 'error_message' => '商户已暂停，本轮未发布', 'finished_at' => time(), 'update_time' => time()]);
                Data::query('connection', $site)->where('id', $conn['id'])->update(['active_run_id' => 0, 'lease_token' => '', 'lease_until' => 0,
                    'last_sync_status' => 'cancelled', 'last_sync_message' => '商户已暂停，本轮未发布', 'update_time' => time()]);
                return null;
            }
            Data::query('connection', $site)->where('id', $conn['id'])->update(['lease_token' => $lease, 'lease_until' => time() + 180, 'last_sync_status' => 'running', 'update_time' => time()]);
            Data::query('sync_run', $site)->where('id', $runId)->update(['status' => 'running', 'started_at' => $run['started_at'] ?: time(), 'update_time' => time()]);
            return [$conn, $run];
        });
        if (!$claim) return $this->detail($site, $runId);
        [$conn,$run] = $claim;
        try {
            $policy = new ProviderChannelService($site);
            $policy->assertEnabled((string) $conn['provider']);
            $credentials = Cipher::decrypt((string) $conn['credentials_cipher'], $site, (int) $conn['id']);
            $provider = $this->providers->get((string) $conn['provider']);
            $definition = $this->providers->definition((string) $conn['provider']);
            $scope = ['tenant_id' => $conn['expected_tenant_id'] ?: null, 'store_id' => $conn['store_id'] ?: null, 'page_size' => 100];
            $cursor = Data::decode($run['cursor_json']);
            if ((int) ($cursor['page'] ?? 1) > 100) throw new CommonException('超过100页安全上限，本轮未发布；请联系管理员缩小范围');
            $tenantHint = null;
            if ($definition->loginRequired($credentials, (int) $run['page_count'])) {
                $login = $provider->login($credentials);
                $this->assertAuthenticatedStore($definition, $login, $scope);
                $credentials['access_token'] = $login['access_token'];
                $credentials['expires_at'] = $login['expires_at'] ?? 0;
                $tenantHint = $login['identity']['tenant_id'] ?? null;
            }
            if ($tenantHint !== null) {
                if ($scope['tenant_id'] !== null && (string) $scope['tenant_id'] !== (string) $tenantHint) throw new CommonException('登录账号与已绑定租户不一致，请核对ERP账号');
                $scope['tenant_id'] = $scope['tenant_id'] ?? (string) $tenantHint;
            }
            if (isset($login) && $definition->renewable($credentials)) {
                $this->persistAuthorization($site, $runId, $lease, $conn, $run, $credentials, 'before_page');
            }
            $policy->assertEnabled((string) $conn['provider']);
            try {
                $page = $provider->fetchPage($credentials, $cursor, $scope);
            } catch (ProviderException $error) {
                if (!$definition->renewable($credentials) || $error->reason() !== 'auth_required') throw $error;
                $policy->assertEnabled((string) $conn['provider']);
                // At most one renewal for an expired token; never loop failed passwords.
                $login = $provider->login($credentials);
                $this->assertAuthenticatedStore($definition, $login, $scope);
                $credentials['access_token'] = $login['access_token'];
                $credentials['expires_at'] = $login['expires_at'] ?? 0;
                $renewedTenant = $login['identity']['tenant_id'] ?? null;
                if ($renewedTenant !== null) {
                    if ($scope['tenant_id'] !== null && (string) $scope['tenant_id'] !== (string) $renewedTenant) {
                        throw new CommonException('刷新授权后的账号与绑定租户不一致，本轮未发布');
                    }
                    $scope['tenant_id'] = $scope['tenant_id'] ?? (string) $renewedTenant;
                }
                $this->persistAuthorization($site, $runId, $lease, $conn, $run, $credentials, 'session_expired');
                $policy->assertEnabled((string) $conn['provider']);
                $page = $provider->fetchPage($credentials, $cursor, $scope);
            }
            $summary = Data::decode($run['summary_json']);
            $stores = $summary['stores'] ?? [];
            $tenants = $summary['tenants'] ?? [];
            $rows = [];
            $keys = [];
            foreach ($page['rows'] as $source) {
                $item = $provider->normalize($source, ['connection_id' => (string) $conn['id'], 'tenant_id' => $scope['tenant_id'],
                    'store_id' => $scope['store_id'], 'fetched_at' => gmdate('Y-m-d\TH:i:s\Z')]);
                $stores[] = (string) $item['source_store_id'];
                if ($item['tenant_id'] !== null && $item['tenant_id'] !== '') $tenants[] = (string) $item['tenant_id'];
                $stores = array_values(array_unique($stores));
                $tenants = array_values(array_unique($tenants));
                if (count($tenants) > 1) throw new CommonException('库存响应跨租户，本轮未发布');
                if ($conn['store_id'] === '' && count($stores) > 1) throw new CommonException('账号包含多个来源门店，请在高级设置填写来源门店ID后重新同步');
                $key = hash('sha256', $item['source_key']);
                if (isset($keys[$key]) || Data::query('inventory', $site)->where('connection_id', $conn['id'])->where('sync_run_id', $runId)->where('source_key', $key)->count()) {
                    throw new CommonException('分页出现重复设备，本轮未发布，请稍后重新同步');
                }
                $keys[$key] = true;
                $raw = $item['safe_snapshot'];
                $grade = $definition->grade($raw);
                $sourceStatus = is_array($item['source_status'] ?? null) ? $item['source_status'] : [];
                $sourceStatus['_inventory'] = [
                    'listing_status' => $item['listing_status'] ?? 'unknown',
                    'stock_status' => $item['stock_status'] ?? 'unknown',
                    'sale_state' => $item['sale_state'] ?? 'unknown',
                    'quantity_basis' => $item['quantity_basis'] ?? '',
                ];
                $rows[] = ['site_id' => $site, 'merchant_id' => $conn['merchant_id'], 'connection_id' => $conn['id'], 'sync_run_id' => $runId,
                    'source_key' => $key, 'source_id' => $item['source_id'], 'source_store_id' => $item['source_store_id'], 'tenant_id' => $item['tenant_id'] ?? '',
                    'model' => $item['title'] ?? '', 'brand' => $item['brand'] ?? '', 'memory' => $item['capacity'] ?? '', 'color' => $item['color'] ?? '',
                    'grade' => is_scalar($grade) ? mb_substr((string) $grade, 0, 100) : '', 'price' => $item['retail_price'], 'trade_price' => $item['trade_price'],
                    'imei' => $item['imei'] ?? '', 'cover' => $item['cover'] ?? '', 'images_json' => Data::json($item['images']),
                    'source_status_json' => Data::json($sourceStatus), 'snapshot_json' => Data::json($raw),
                    'availability' => $item['availability'] ?? 'unknown',
                    'quantity' => $item['quantity'] ?? null, 'saleable_quantity' => $item['saleable_quantity'] ?? null,
                    'fetched_at' => time(), 'create_time' => time(), 'update_time' => time()];
            }
            Db::transaction(function () use ($site, $runId, $lease, $conn, $run, $page, $rows, $credentials, $stores, $tenants) {
                $policy = new ProviderChannelService($site);
                $policy->lockSite();
                $current = Data::row('connection', $site, (int) $conn['id'], true);
                if ($current['lease_token'] !== $lease || (int) $current['active_run_id'] !== $runId || (int) $current['config_version'] !== (int) $run['config_version']) {
                    throw new CommonException('配置或执行权已变更，本轮数据未发布');
                }
                $policy->assertEnabled((string) $current['provider']);
                if ($this->generationExpired($run, $current)) {
                    $this->expireGeneration($site, $run, $current);
                    return;
                }
                if ($rows) Data::query('inventory', $site)->insertAll($rows);
                $done = !$page['has_more'];
                Data::query('sync_run', $site)->where('id', $runId)->update(['status' => $done ? 'completed' : 'running',
                    'cursor_json' => Data::json($page['cursor']), 'summary_json' => Data::json(['stores' => $stores, 'tenants' => $tenants,
                        'note' => is_string($page['note'] ?? null) ? mb_substr($page['note'], 0, 500) : '']),
                    'page_count' => (int) $run['page_count'] + 1, 'record_count' => (int) $run['record_count'] + count($rows),
                    'finished_at' => $done ? time() : 0, 'update_time' => time()]);
                $changes = ['lease_token' => '', 'lease_until' => 0, 'credentials_cipher' => Cipher::encrypt($credentials, $site, (int) $conn['id']),
                    'last_sync_status' => $done ? 'completed' : 'running', 'last_sync_message' => $done ? '完整同步已完成，共读取 '.((int) $run['record_count'] + count($rows)).' 条库存' : '已读取 '.((int) $run['record_count'] + count($rows)).' 条库存，正在继续全量同步', 'update_time' => time()];
                if ($done) {
                    $changes += ['published_run_id' => $runId, 'active_run_id' => 0, 'last_sync_at' => time(),
                        'next_sync_at' => time() + (int) $current['sync_interval_minutes'] * 60];
                    if ($current['expected_tenant_id'] === '' && count($tenants) === 1) $changes['expected_tenant_id'] = $tenants[0];
                    if ($current['store_id'] === '' && count($stores) === 1) $changes['store_id'] = $stores[0];
                    Data::audit($site, 0, 0, 'sync_run', $runId, 'published', ['record_count' => (int) $run['record_count'] + count($rows)]);
                }
                Data::query('connection', $site)->where('id', $conn['id'])->update($changes);
            });
        } catch (\Throwable $error) {
            if ($this->pauseIfDisabled($site, $runId, (int) $conn['id'], $lease)) return $this->detail($site, $runId);
            $reason = $error instanceof ProviderException ? $error->reason() : 'sync_failed';
            $message = $error instanceof ProviderException || $error instanceof CommonException ? $error->getMessage() : '同步执行失败，本轮未发布；请管理员核对服务日志与配置';
            $this->fail($site, $runId, (int) $conn['id'], $lease, $reason, $message);
        }
        return $this->detail($site, $runId);
    }

    /** A successful login survives a later inventory network error, never a scope/config change. */
    private function persistAuthorization(int $site, int $runId, string $lease, array $connection, array $run, #[\SensitiveParameter] array $credentials, string $reason): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        Db::transaction(function () use ($site, $runId, $lease, $connection, $run, $credentials, $reason): void {
            $policy = new ProviderChannelService($site);
            $policy->lockSite();
            $current = Data::row('connection', $site, (int) $connection['id'], true);
            if ($current['lease_token'] !== $lease || (int) $current['active_run_id'] !== $runId || (int) $current['config_version'] !== (int) $run['config_version']) {
                throw new CommonException('配置或执行权已变更，新授权未写入；请核对当前接入');
            }
            $policy->assertEnabled((string) $current['provider']);
            Data::query('connection', $site)->where('id', $current['id'])->update([
                'credentials_cipher' => Cipher::encrypt($credentials, $site, (int) $current['id']), 'update_time' => time()]);
            Data::audit($site, 0, 0, 'sync_run', $runId, 'authorization_refreshed', ['provider' => $current['provider'], 'reason' => $reason]);
        });
    }

    /** Policy pause is not a sync failure: retain cursor, active task and published generation. */
    private function pauseIfDisabled(int $site, int $runId, int $connection, string $lease): bool
    {
        return Db::transaction(function () use ($site, $runId, $connection, $lease): bool {
            $policy = new ProviderChannelService($site);
            $policy->lockSite();
            $conn = Data::row('connection', $site, $connection, true);
            if ($policy->enabled((string) $conn['provider'])) return false;
            if ($conn['lease_token'] === $lease && (int) $conn['active_run_id'] === $runId) {
                Data::query('connection', $site)->where('id', $connection)->update(['lease_token' => '', 'lease_until' => 0, 'update_time' => time()]);
            }
            return true;
        });
    }

    private function assertAuthenticatedStore(ProviderDefinition $definition, #[\SensitiveParameter] array $login, array $scope): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!$definition->verifiesIdentity() || ($login['identity']['verified'] ?? false) !== true) return;
        $store = Data::text($login['identity'], 'store_id', 100, true);
        if ((string) ($scope['store_id'] ?? '') !== '' && (string) $scope['store_id'] !== $store) {
            throw new CommonException('登录认证门店与已绑定来源门店不一致，本轮未发布；请核对后重新验证接入');
        }
    }

    /** Never re-date old staged pages as fresh stock after a long pause. */
    private function generationExpired(array $run, array $connection): bool
    {
        if ((int) $run['page_count'] <= 0) return false;
        $started = (int) ($run['started_at'] ?: $run['create_time']);
        return $started <= 0 || $started + max(2 * max(1, (int) $connection['sync_interval_minutes']), 120) * 60 < time();
    }
    /** Caller holds site and connection locks. Keep staged rows and the previous published batch. */
    private function expireGeneration(int $site, array $run, array $connection): void
    {
        $message = '本轮暂存库存已过期，未发布；已保留历史。自动同步会重新完整读取，手动同步请启动新一轮。';
        Data::query('sync_run', $site)->where('id', $run['id'])->update(['status' => 'cancelled', 'error_code' => 'generation_expired', 'error_message' => $message, 'finished_at' => time(), 'update_time' => time()]);
        Data::query('connection', $site)->where('id', $connection['id'])->update(['active_run_id' => 0, 'lease_token' => '', 'lease_until' => 0,
            'last_sync_status' => 'cancelled', 'last_sync_message' => $message, 'next_sync_at' => time(), 'update_time' => time()]);
        Data::audit($site, 0, 0, 'sync_run', (int) $run['id'], 'generation_expired', []);
    }

    private function fail(int $site, int $runId, int $connection, string $lease, string $code, string $message): void
    {
        Db::transaction(function () use ($site, $runId, $connection, $lease, $code, $message) {
            $policy = new ProviderChannelService($site);
            $policy->lockSite();
            $conn = Data::row('connection', $site, $connection, true);
            if ($conn['lease_token'] !== $lease || (int) $conn['active_run_id'] !== $runId) return;
            if (!$policy->enabled((string) $conn['provider'])) {
                Data::query('connection', $site)->where('id', $connection)->update(['lease_token' => '', 'lease_until' => 0, 'update_time' => time()]);
                return;
            }
            Data::query('sync_run', $site)->where('id', $runId)->update(['status' => 'failed', 'error_code' => mb_substr($code, 0, 80),
                'error_message' => mb_substr($message, 0, 1000), 'finished_at' => time(), 'update_time' => time()]);
            Data::query('connection', $site)->where('id', $connection)->update(['active_run_id' => 0, 'lease_token' => '', 'lease_until' => 0,
                'last_sync_status' => 'failed', 'last_sync_message' => mb_substr($message, 0, 1000),
                'next_sync_at' => time() + (int) $conn['sync_interval_minutes'] * 60, 'update_time' => time()]);
            Data::audit($site, 0, 0, 'sync_run', $runId, 'failed', ['reason' => $code]);
        });
    }

    public function tick(int $limit = 5): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $began = microtime(true);
        $results = [];
        $query = Db::name('hsx_lead_connection')->alias('c')->where('c.enabled', 1)->where(function ($q) {
            $q->where('c.active_run_id', '>', 0)->whereOr('c.next_sync_at', '<=', time());
        })->where('c.lease_until', '<=', time());
        ProviderChannelService::filterEnabledConnections($query);
        $connections = $query->field('c.*')->order('c.next_sync_at')->limit(max(1, min(20, $limit)))->select()->toArray();
        foreach ($connections as $conn) {
            if (microtime(true) - $began > 25) break;
            try {
                $site = (int) $conn['site_id'];
                $run = $this->start($site, (int) $conn['merchant_id'], 0, 'scheduled');
                $results[] = $this->advance($site, (int) $run['run_id']);
            } catch (\Throwable $error) {
                // Only safe IDs are returned. A broken connection must not expose credentials or stop other sites.
                $results[] = ['connection_id' => (int) $conn['id'], 'status' => 'failed', 'message' => '配置或任务不可执行，请管理员核对'];
            }
        }
        return $results;
    }
}
