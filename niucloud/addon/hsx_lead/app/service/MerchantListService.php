<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\provider\ProviderRegistry;
use addon\hsx_lead\app\service\cooperation\{CapabilityService,ConfigService,EntityMembershipService,Rules,Store};
use addon\hsx_lead\app\support\{Cipher,ConnectionAttention,Data,InventoryAvailability,SyncStatus};
use think\facade\Db;

/** Request-owned administrator list read model. Detail/edit contracts live in MerchantService. */
final class MerchantListService
{
    private ?array $config = null;
    private bool $authorized = false;
    public function __construct(private Context $context)
    {
    }

    /** Input rows must come from this site's already-filtered merchant query. */
    public function present(array $merchants): array
    {
        if (!$this->authorized) {
            $this->context->manager();
            $this->authorized = true;
        }
        if (!$merchants) return [];
        $site = $this->context->site;
        $this->config = $this->config ?? (new ConfigService($this->context))->get();
        $config = $this->config;
        $ids = [];
        $memberIds = [];
        $areaIds = [];
        foreach ($merchants as $merchant) {
            // A summary helper is not an alternate route around tenant scoping.
            if ((int) ($merchant['site_id'] ?? 0) !== $site) throw new \core\exception\CommonException('商户不属于当前站点');
            $ids[] = (int) $merchant['id'];
            $memberIds[] = (int) $merchant['member_id'];
            foreach (['province_id', 'city_id', 'district_id'] as $key) $areaIds[] = (int) $merchant[$key];
        }
        $members = [];
        foreach (Db::name('member')->where('site_id', $site)->whereIn('member_id', array_unique($memberIds))
            ->field('member_id,nickname,status,is_del,delete_time')->select()->toArray() as $member) $members[(int) $member['member_id']] = $member;
        $areas = Db::name('sys_area')->whereIn('id', array_unique($areaIds))->column('name', 'id');
        $partners = [];
        foreach (Store::query('partner', $site)->whereIn('merchant_id', $ids)->field('merchant_id,blocked_at')->select()->toArray() as $partner) $partners[(int) $partner['merchant_id']] = $partner;
        $connections = Data::query('connection', $site)->whereIn('merchant_id', $ids)
            ->field('id,merchant_id,provider,enabled,sync_interval_minutes,config_version,last_sync_status,last_sync_at,active_run_id,published_run_id,next_sync_at,credentials_cipher')->select()->toArray();
        $sync = SyncStatus::connections($site, $connections);
        $policy = (new ProviderChannelService($site))->summaries(array_column($connections, 'provider'));
        $connectionMap = [];
        $registry = new ProviderRegistry();
        foreach ($connections as $connection) {
            $connectionMap[(int) $connection['merchant_id']] = $this->connection($connection, $registry, $policy, $sync[(int) $connection['id']] ?? []);
        }
        $inventory = $this->inventoryCounts($ids, $config);
        $capabilities = (new CapabilityService($this->context))->summaries($ids, $config);
        // Fresh authoritative receipt/refund/exit facts are batch loaded. Never
        // replace these with partner membership_until or a cross-request cache.
        $memberships = EntityMembershipService::states($site, $ids, $config);
        $rows = [];
        foreach ($merchants as $merchant) {
            $id = (int) $merchant['id'];
            $partner = $partners[$id] ?? ['blocked_at' => 0];
            $conn = $connectionMap[$id] ?? self::emptyConnection();
            $membership = $memberships[$id];
            $rights = Rules::capabilities($partner, $config, $membership);
            $available = !empty($config['enabled']) && (int) $partner['blocked_at'] === 0 && (int) $merchant['status'] === 1;
            $row = array_intersect_key($merchant, array_flip([
                        'id',
                        'name',
                        'contact_name',
                        'mobile',
                        'province_id',
                        'city_id',
                        'district_id',
                        'address',
                        'member_id',
                        'status',
                        'create_time',
                        'update_time'
                    ]));
            $names = [];
            foreach (['province', 'city', 'district'] as $key) {
                $name = (string) ($areas[(int) $merchant[$key.'_id']] ?? '');
                $row[$key.'_name'] = $name;
                if ($name !== '')$names[] = $name;
            }
            $row['region_text'] = implode(' / ', $names);
            $row['status'] = (int) $row['status'];
            $member = $members[(int) $merchant['member_id']] ?? null;
            $row['member_name'] = (string) ($member['nickname'] ?? '');
            foreach ([
                    'provider',
                    'provider_name',
                    'connection_id',
                    'auth_renewal',
                    'next_sync_at',
                    'channel_enabled',
                    'channel_message',
                    'last_sync_status',
                    'last_sync_at',
                    'last_sync_message',
                    'published_run_id',
                    'sync_error_code',
                    'sync_attention',
                    'authorization_attention'
                ] as $key) $row[$key] = $conn[$key];
            $row['sync_interval_minutes'] = $conn['interval_minutes'];
            $row['sync_enabled'] = $conn['enabled'];
            $row['last_run_id'] = $conn['active_run_id'];
            $row += $capabilities[$id];
            $count = (int) ($inventory[$id] ?? 0);
            $capabilityCount = $row['business_capability_count'];
            $row += ['membership_rank' => !empty($membership['active']) ? (int) $membership['rank'] : 0, 'display_rank' => !empty($membership['active']) ? (int) $membership['display_rank'] : 0,
                'blocked_at' => (int) $partner['blocked_at'], 'membership' => $membership, 'membership_active' => (bool) $membership['active'],
                'membership_tier' => $membership['level_code'], 'membership_expires_at' => (int) $membership['expires_at'],
                'all_models' => $rights['all_models'], 'can_collaborate' => $available && ($rights['can_publish'] || $rights['can_accept']),
                'can_publish' => $available && $rights['can_publish'], 'can_accept' => $available && $rights['can_accept'],
                'can_view_inventory' => $available && $rights['can_view_inventory'], 'capabilities' => $rights, 'inventory_count' => $count,
                'capability_source' => $rights['all_models'] ? 'all_models' : ($count > 0 ? ($capabilityCount ? 'mixed' : 'erp') : ($capabilityCount ? 'manual' : 'none'))];
            [$row['ready_state'],$row['ready_reason']] = MerchantService::readiness($row, $conn, $member);
            $rows[] = $row;
        }
        return $rows;
    }

    /** The exact PartnerService inventory eligibility, counted in SQL rather than hydrated. */
    private function inventoryCounts(array $ids, array $config): array
    {
        $query = Db::name('hsx_lead_inventory')->alias('i')->join('hsx_lead_connection c', 'c.id=i.connection_id AND c.site_id=i.site_id AND c.merchant_id=i.merchant_id AND c.published_run_id=i.sync_run_id')
            ->join('hsx_lead_merchant m', 'm.id=i.merchant_id AND m.site_id=i.site_id')->where('i.site_id', $this->context->site)->whereIn('i.merchant_id', $ids)->where('c.enabled', 1);
        InventoryAvailability::apply($query, 'sellable', true);
        $rows = $query->where('i.fetched_at', '>=', time() - (int) $config['inventory_fresh_hours'] * 3600)
            ->field('i.merchant_id,COUNT(*) AS inventory_count')->group('i.merchant_id')->select()->toArray();
        $counts = [];
        foreach ($rows as $row) $counts[(int) $row['merchant_id']] = (int) $row['inventory_count'];
        return $counts;
    }

    private static function emptyConnection(): array
    {
        return ['connected' => false, 'connection_id' => 0, 'provider' => '', 'provider_name' => '', 'enabled' => false, 'interval_minutes' => 60,
            'last_sync_status' => 'idle', 'last_sync_at' => 0, 'last_sync_message' => '尚未接入ERP', 'active_run_id' => 0, 'published_run_id' => 0, 'next_sync_at' => 0,
            'authorization_attention' => false, 'channel_enabled' => true, 'channel_message' => '', 'sync_error_code' => '', 'sync_attention' => null,
            'auth_renewal' => ['automatic' => false, 'ready' => false, 'mode' => '', 'status' => 'credentials_required', 'message' => '尚未接入ERP，请先保存并验证登录资料。']];
    }

    /** Local credential checks only; no login, provider HTTP, recent history or public credentials. */
    private function connection(array $connection, ProviderRegistry $registry, array $policy, array $sync): array
    {
        $definition = $registry->definition((string) $connection['provider']);
        $channel = $policy[$definition->key()];
        $result = array_replace(self::emptyConnection(), ['connected' => true, 'connection_id' => (int) $connection['id'],
            'provider' => $definition->key(), 'provider_name' => $channel['name'], 'enabled' => (bool) $connection['enabled'],
            'interval_minutes' => (int) $connection['sync_interval_minutes'], 'last_sync_at' => (int) $connection['last_sync_at'],
            'published_run_id' => (int) $connection['published_run_id'], 'next_sync_at' => (int) $connection['next_sync_at'],
            'channel_enabled' => $channel['enabled'], 'channel_message' => $channel['enabled'] ? '' : '本站已关闭该ERP渠道，授权及采集暂停，账号与历史库存仍保留。'], $sync);
        $credentials = null;
        $credentialError = false;
        try {
            $credentials = Cipher::decrypt((string) $connection['credentials_cipher'], $this->context->site, (int) $connection['id']);
            $result['auth_renewal'] = $definition->renewalStatus($credentials);
        } catch (\Throwable $error) {
            $credentialError = true;
            $result['auth_renewal'] = [
                'automatic' => false,
                'ready' => false,
                'mode' => '',
                'status' => 'credential_error',
                'message' => '登录资料无法解密，未尝试自动登录；请核对部署密钥后重新验证。'
            ];
        }
        return array_replace($result, ConnectionAttention::project($result, $definition, $credentials, $credentialError));
    }
}
