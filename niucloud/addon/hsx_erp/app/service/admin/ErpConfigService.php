<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\support\ErpListingFormContract;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use think\facade\Log;

class ErpConfigService extends BaseAdminService
{
    public const CONFIG_KEY = 'HSX_ERP_RULES';
    public const SALE_CHANNEL_KEY = 'HSX_ERP_SALE_CHANNELS';
    public const SALE_CHANNEL_OPTION_KEY = 'HSX_ERP_SALE_CHANNEL_OPTIONS';
    public const FINANCE_CATEGORY_KEY = 'HSX_ERP_FINANCE_CATEGORIES';

    public static function forSite(int $siteId): self
    {
        $service = new self();
        $service->site_id = $siteId;
        return $service;
    }

    public function getRules(): array
    {
        $value = (new CoreConfigService())->getConfigValue($this->site_id, self::CONFIG_KEY);
        if (!is_array($value)) {
            $value = [];
        }
        return $this->normalize($value);
    }

    /** 跨插件只读渠道策略；调用方不需要也不允许读取 ERP 私有表。 */
    public function getMarketplaceChannel(string $channelKey = 'phone_shop', ?int $siteId = null): array
    {
        $siteId = $siteId ?? (int)$this->site_id;
        $value = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        $marketplace = (array)((is_array($value) ? $value : [])['marketplace'] ?? []);
        $legacyOwner = in_array((string)($marketplace['recycle_material_owner'] ?? 'erp'), ['erp', 'phone_shop'], true)
            ? (string)$marketplace['recycle_material_owner'] : 'erp';
        $channel = (array)($marketplace['channels'][$channelKey] ?? []);
        if ($channel === []) {
            $channel = $legacyOwner === 'phone_shop'
                ? ['enabled' => 1, 'category_mode' => 'independent', 'spec_mode' => 'independent', 'publish_mode' => 'manual']
                : ['enabled' => 1, 'category_mode' => 'erp', 'spec_mode' => 'erp', 'publish_mode' => 'direct'];
        }
        return [
            'channel_key' => preg_replace('/[^a-zA-Z0-9_\-]/', '', $channelKey) ?: 'phone_shop',
            'enabled' => $this->boolInt($channel['enabled'] ?? 1),
            'category_mode' => in_array((string)($channel['category_mode'] ?? 'erp'), ['erp', 'independent'], true) ? (string)$channel['category_mode'] : 'erp',
            'spec_mode' => in_array((string)($channel['spec_mode'] ?? 'erp'), ['erp', 'independent'], true) ? (string)$channel['spec_mode'] : 'erp',
            'publish_mode' => in_array((string)($channel['publish_mode'] ?? 'direct'), ['direct', 'manual'], true) ? (string)$channel['publish_mode'] : 'direct',
            'erp_is_master' => 1,
            'channel_can_write_erp_master' => 0,
        ];
    }

    public function saveRules(array $data): array
    {
        $config = new CoreConfigService();
        $stored = $config->getConfigValue($this->site_id, self::CONFIG_KEY);
        $merged = array_replace_recursive(is_array($stored) ? $stored : [], $data);
        // 兼容仍只提交旧 recycle_material_owner 的客户端：显式旧值应能覆盖已存的新策略。
        if (array_key_exists('recycle_material_owner', (array)($data['marketplace'] ?? []))
            && !isset($data['marketplace']['channels']['phone_shop'])) {
            unset($merged['marketplace']['channels']['phone_shop']);
        }
        $rules = $this->normalize($merged);
        $config->setConfig($this->site_id, self::CONFIG_KEY, $rules);
        return $rules;
    }

    public function getSaleChannels(): array
    {
        return array_values(array_map(static fn(array $row): string => (string)$row['name'], array_filter(
            $this->getSaleChannelOptions(),
            static fn(array $row): bool => (int)($row['enabled'] ?? 1) === 1
        )));
    }

    public function saveSaleChannels(array $channels): array
    {
        $rows = $this->normalizeSaleChannels($channels);
        (new CoreConfigService())->setConfig($this->site_id, self::SALE_CHANNEL_KEY, $rows);
        (new CoreConfigService())->setConfig($this->site_id, self::SALE_CHANNEL_OPTION_KEY, $this->normalizeSaleChannelOptions($rows));
        return $rows;
    }

    public function getSaleChannelOptions(): array
    {
        $config = new CoreConfigService();
        $value = $config->getConfigValue($this->site_id, self::SALE_CHANNEL_OPTION_KEY);
        return $this->normalizeSaleChannelOptions(array_merge(
            $this->baseSaleChannelOptions(),
            is_array($value) ? $value : [],
            $this->extensionRows('HsxErpSaleChannelOptions', 'channels')
        ));
    }

    public function saveSaleChannelOptions(array $channels): array
    {
        $rows = $this->normalizeSaleChannelOptions($channels);
        $storedRows = array_values(array_filter($rows, static fn(array $row): bool => in_array((string)($row['source_plugin'] ?? ''), ['', 'hsx_erp'], true)));
        $config = new CoreConfigService();
        $config->setConfig($this->site_id, self::SALE_CHANNEL_OPTION_KEY, $storedRows);
        $config->setConfig($this->site_id, self::SALE_CHANNEL_KEY, array_column(array_filter($storedRows, static fn(array $row): bool => (int)$row['enabled'] === 1), 'name'));
        return $this->getSaleChannelOptions();
    }

    public function getFinanceCategories(): array
    {
        $value = (new CoreConfigService())->getConfigValue($this->site_id, self::FINANCE_CATEGORY_KEY);
        return $this->normalizeFinanceCategories(array_merge(
            $this->baseFinanceCategories(),
            is_array($value) ? $value : [],
            $this->extensionRows('HsxErpFinanceCategories', 'categories')
        ));
    }

    public function saveFinanceCategories(array $categories): array
    {
        $rows = $this->normalizeFinanceCategories($categories);
        $storedRows = array_values(array_filter($rows, static fn(array $row): bool => in_array((string)($row['source_plugin'] ?? ''), ['', 'hsx_erp'], true)));
        (new CoreConfigService())->setConfig($this->site_id, self::FINANCE_CATEGORY_KEY, $storedRows);
        return $this->getFinanceCategories();
    }

    public function findFinanceCategory(string $key): ?array
    {
        foreach ($this->getFinanceCategories() as $row) {
            if ((string)$row['key'] === trim($key) && (int)$row['enabled'] === 1) return $row;
        }
        return null;
    }

    /**
     * 当前站点可用的业务来源。
     *
     * 业务来源只回答“业务从哪里进入 ERP”，不等同于销售渠道或财务分类。
     * 插件选项由牛云事件加载器按站点套餐装配，ERP 不持久化插件返回值。
     */
    public function getBusinessSourceOptions(): array
    {
        return $this->normalizeBusinessSourceOptions(array_merge(
            $this->baseBusinessSourceOptions(),
            $this->extensionRows('HsxErpBusinessSourceOptions', 'sources')
        ));
    }

    public function findBusinessSource(string $key): ?array
    {
        $key = trim($key);
        foreach ($this->getBusinessSourceOptions() as $row) {
            if ((string)$row['key'] === $key && (int)$row['enabled'] === 1) return $row;
        }
        return null;
    }

    private function normalize(array $data): array
    {
        $defaults = self::defaults();
        $rules = array_replace_recursive($defaults, $data);
        $rules['finance']['enable_offset'] = $this->boolInt($rules['finance']['enable_offset'] ?? 1);
        $rules['finance']['finance_fact_lock'] = 1;
        $rules['finance']['settlement_requires_account'] = $this->boolInt($rules['finance']['settlement_requires_account'] ?? 1);

        $rules['purchase']['create_payable_on_inbound'] = 1;
        $rules['purchase']['allow_cancel_before_finance_fact'] = $this->boolInt($rules['purchase']['allow_cancel_before_finance_fact'] ?? 1);

        $rules['product_title']['category_mode'] = in_array(($rules['product_title']['category_mode'] ?? 'auto'), ['auto', 'level_1_2', 'level_2_3', 'level_3', 'full'], true)
            ? $rules['product_title']['category_mode']
            : 'auto';
        $rules['product_title']['spec_in_title'] = $this->boolInt($rules['product_title']['spec_in_title'] ?? 1);
        $rules['product_title']['grade_in_title'] = $this->boolInt($rules['product_title']['grade_in_title'] ?? 0);
        $rules['product_title']['separator'] = trim((string)($rules['product_title']['separator'] ?? ' '));
        if ($rules['product_title']['separator'] === '') {
            $rules['product_title']['separator'] = ' ';
        }

        $rules['sale']['create_receivable_on_outbound'] = 1;
        $rules['sale']['allow_cancel_before_finance_fact'] = $this->boolInt($rules['sale']['allow_cancel_before_finance_fact'] ?? 1);
        $rules['sale']['return_to_original_location_on_cancel'] = $this->boolInt($rules['sale']['return_to_original_location_on_cancel'] ?? 1);
        $rules['sale']['enable_peer_pending'] = $this->boolInt($rules['sale']['enable_peer_pending'] ?? 1);
        $rules['sale']['enable_trial_sale'] = $this->boolInt($rules['sale']['enable_trial_sale'] ?? 0);
        $rules['sale']['profit_confirm_mode'] = in_array(($rules['sale']['profit_confirm_mode'] ?? 'settlement'), ['outbound', 'settlement'], true)
            ? $rules['sale']['profit_confirm_mode']
            : 'settlement';
        $creditControl = (array)($rules['sale']['credit_control'] ?? []);
        $rules['sale']['credit_control']['enabled'] = $this->boolInt($creditControl['enabled'] ?? 1);
        $rules['sale']['credit_control']['default_policy'] = in_array((string)($creditControl['default_policy'] ?? 'remind'), ['normal', 'remind', 'cash_only', 'blocked'], true)
            ? (string)$creditControl['default_policy']
            : 'remind';
        $rules['sale']['credit_control']['min_outstanding_amount'] = max(0, round((float)($creditControl['min_outstanding_amount'] ?? 0), 2));
        $rules['sale']['credit_control']['min_outstanding_days'] = max(0, min(3650, (int)($creditControl['min_outstanding_days'] ?? 0)));

        $rules['refurbish']['enabled'] = $this->boolInt($rules['refurbish']['enabled'] ?? 0);
        $rules['refurbish']['default_required'] = $this->boolInt($rules['refurbish']['default_required'] ?? 0);
        $rules['refurbish']['tracking_mode'] = in_array((string)($rules['refurbish']['tracking_mode'] ?? 'simple'), ['simple', 'external'], true)
            ? (string)$rules['refurbish']['tracking_mode']
            : 'simple';
        $rules['refurbish']['daily_reminder_enabled'] = $this->boolInt($rules['refurbish']['daily_reminder_enabled'] ?? 1);
        $rules['refurbish']['daily_reminder_threshold'] = max(1, min(999, (int)($rules['refurbish']['daily_reminder_threshold'] ?? 25)));
        $rules['refurbish']['reminder_dismiss_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($rules['refurbish']['reminder_dismiss_date'] ?? ''))
            ? (string)$rules['refurbish']['reminder_dismiss_date']
            : '';

        $attentionDays = max(1, min(365, (int)($rules['turnover']['attention_days'] ?? 7)));
        $warningDays = max($attentionDays + 1, min(730, (int)($rules['turnover']['warning_days'] ?? 15)));
        $criticalDays = max($warningDays + 1, min(1095, (int)($rules['turnover']['critical_days'] ?? 30)));
        $rules['turnover']['attention_days'] = $attentionDays;
        $rules['turnover']['warning_days'] = $warningDays;
        $rules['turnover']['critical_days'] = $criticalDays;
        $rules['turnover']['reminder_enabled'] = $this->boolInt($rules['turnover']['reminder_enabled'] ?? 1);
        $rules['turnover']['reminder_count_threshold'] = max(1, min(9999, (int)($rules['turnover']['reminder_count_threshold'] ?? 1)));
        $rules['turnover']['reminder_dismiss_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($rules['turnover']['reminder_dismiss_date'] ?? ''))
            ? (string)$rules['turnover']['reminder_dismiss_date']
            : '';

        $purchase = (array)($rules['purchase'] ?? []);
        $rules['purchase']['mobile_entry_mode'] = in_array((string)($purchase['mobile_entry_mode'] ?? 'quick'), ['quick', 'complete', 'collaborative'], true)
            ? (string)$purchase['mobile_entry_mode']
            : 'quick';

        $rules['category_sync']['enabled'] = $this->boolInt($rules['category_sync']['enabled'] ?? 0);
        $rules['category_sync']['initialized'] = $this->boolInt($rules['category_sync']['initialized'] ?? 0);
        $rules['category_sync']['provider'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($rules['category_sync']['provider'] ?? 'phone_shop')) ?: 'phone_shop';
        // 旧字段只保留向后兼容。ERP 已确定为主数据，禁止继续保存 two_way/shop_master。
        $rules['category_sync']['mode'] = (string)($rules['category_sync']['mode'] ?? 'disabled') === 'erp_master'
            ? 'erp_master'
            : 'disabled';
        $rules['category_sync']['last_action'] = in_array((string)($rules['category_sync']['last_action'] ?? ''), ['', 'pull', 'push', 'reconcile', 'bootstrap'], true)
            ? (string)$rules['category_sync']['last_action']
            : '';

        $workspace = (array)($rules['listing_workspace'] ?? []);
        $rules['listing_workspace']['mode'] = in_array((string)($workspace['mode'] ?? 'one_stop'), ['one_stop', 'split', 'photo_price'], true)
            ? (string)$workspace['mode']
            : 'one_stop';
        $rules['listing_workspace']['media_provider'] = in_array((string)($workspace['media_provider'] ?? 'auto'), ['auto', 'erp', 'device_asset'], true)
            ? (string)$workspace['media_provider']
            : 'auto';
        $rules['listing_workspace']['auto_publish'] = $this->boolInt($workspace['auto_publish'] ?? 0);
        $rules['listing_workspace']['fallback_to_erp'] = 1;
        $rules['listing_workspace']['field_rules'] = ErpListingFormContract::normalizeRules(
            (array)($workspace['field_rules'] ?? [])
        );
        // 采购录入不再维护第二套互相冲突的模式。保留旧字段供已部署前端读取，
        // 但值始终由销售资料工作模式派生。
        $rules['purchase']['mobile_entry_mode'] = $rules['listing_workspace']['mode'] === 'one_stop'
            ? 'complete'
            : 'collaborative';

        $owner = in_array((string)($rules['marketplace']['recycle_material_owner'] ?? 'erp'), ['erp', 'phone_shop'], true)
            ? (string)$rules['marketplace']['recycle_material_owner']
            : 'erp';
        $storedChannel = (array)($data['marketplace']['channels']['phone_shop'] ?? []);
        $channel = (array)($rules['marketplace']['channels']['phone_shop'] ?? []);
        // 老站点只有资料负责人开关时，自动翻译成新渠道策略，保证升级后行为不变。
        if ($storedChannel === []) {
            $channel = $owner === 'phone_shop'
                ? ['enabled' => 1, 'category_mode' => 'independent', 'spec_mode' => 'independent', 'publish_mode' => 'manual']
                : ['enabled' => 1, 'category_mode' => 'erp', 'spec_mode' => 'erp', 'publish_mode' => 'direct'];
        }
        $channel['enabled'] = $this->boolInt($channel['enabled'] ?? 1);
        $channel['category_mode'] = in_array((string)($channel['category_mode'] ?? 'erp'), ['erp', 'independent'], true)
            ? (string)$channel['category_mode'] : 'erp';
        $channel['spec_mode'] = in_array((string)($channel['spec_mode'] ?? 'erp'), ['erp', 'independent'], true)
            ? (string)$channel['spec_mode'] : 'erp';
        $channel['publish_mode'] = in_array((string)($channel['publish_mode'] ?? 'direct'), ['direct', 'manual'], true)
            ? (string)$channel['publish_mode'] : 'direct';
        $rules['marketplace']['channels']['phone_shop'] = $channel;
        // 保留旧字段供已经部署的库存/商城页面读取，但它始终由新策略派生，避免两个开关互相打架。
        $rules['marketplace']['recycle_material_owner'] = $channel['publish_mode'] === 'manual' ? 'phone_shop' : 'erp';
        $rules['category_sync']['enabled'] = $channel['enabled'];
        $rules['category_sync']['provider'] = 'phone_shop';
        $rules['category_sync']['mode'] = $channel['category_mode'] === 'erp' ? 'erp_master' : 'disabled';

        $rules['consignment']['enabled'] = $this->boolInt($rules['consignment']['enabled'] ?? 0);
        $rules['consignment']['settle_payable_after_receipt'] = $this->boolInt($rules['consignment']['settle_payable_after_receipt'] ?? 1);
        $rules['consignment']['transfer_to_owned_requires_repurchase'] = 1;

        return $rules;
    }

    private function boolInt(mixed $value): int
    {
        return (int)$value === 1 || $value === true || $value === '1' ? 1 : 0;
    }

    private function normalizeSaleChannels(array $channels): array
    {
        $defaults = ['门店', '同行', '小程序'];
        $rows = empty($channels) ? $defaults : $channels;
        $normalized = [];
        foreach ($rows as $row) {
            $name = is_array($row) ? (string)($row['name'] ?? $row['label'] ?? $row['value'] ?? '') : (string)$row;
            $name = trim($name);
            if ($name === '' || in_array($name, $normalized, true)) {
                continue;
            }
            $normalized[] = mb_substr($name, 0, 30);
        }
        return array_values($normalized);
    }

    private function normalizeSaleChannelOptions(array $channels): array
    {
        $defaults = $this->baseSaleChannelOptions();
        $rows = $channels === [] ? $defaults : $channels;
        $normalized = [];
        foreach ($rows as $index => $row) {
            $data = is_array($row) ? $row : ['name' => (string)$row];
            $name = mb_substr(trim((string)($data['name'] ?? $data['label'] ?? $data['value'] ?? '')), 0, 30);
            if ($name === '') continue;
            $key = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($data['key'] ?? ''));
            if ($key === '') $key = 'manual_' . substr(md5($name), 0, 12);
            $sourcePlugin = mb_substr(trim((string)($data['source_plugin'] ?? '')), 0, 40);
            if (isset($normalized[$key])) {
                $owner = (string)($normalized[$key]['source_plugin'] ?? '');
                if ($owner !== $sourcePlugin) {
                    Log::warning('[hsx_erp] 销售渠道 key 冲突，保留先注册项：site_id=' . $this->site_id . ' key=' . $key . ' owner=' . $owner . ' rejected=' . $sourcePlugin);
                    continue;
                }
            }
            $normalized[$key] = [
                'key' => $key,
                'name' => $name,
                'channel_type' => in_array((string)($data['channel_type'] ?? ''), ['peer', 'store', 'retail', 'platform', 'other'], true) ? (string)$data['channel_type'] : 'other',
                'source_plugin' => $sourcePlugin,
                'source_key' => mb_substr(trim((string)($data['source_key'] ?? '')), 0, 80),
                'enabled' => $this->boolInt($data['enabled'] ?? 1),
                'is_default' => $this->boolInt($data['is_default'] ?? ($index === 0 ? 1 : 0)),
                'sort' => (int)($data['sort'] ?? (100 - $index)),
            ];
        }
        if ($normalized === []) return $defaults;
        $defaultSeen = false;
        foreach ($normalized as &$row) {
            if ((int)$row['enabled'] !== 1 || $defaultSeen) $row['is_default'] = 0;
            elseif ((int)$row['is_default'] === 1) $defaultSeen = true;
        }
        unset($row);
        if (!$defaultSeen) {
            foreach ($normalized as &$row) {
                if ((int)$row['enabled'] === 1) { $row['is_default'] = 1; break; }
            }
            unset($row);
        }
        $rows = array_values($normalized);
        usort($rows, static fn(array $a, array $b): int => (int)$b['sort'] <=> (int)$a['sort']);
        return $rows;
    }

    private function normalizeFinanceCategories(array $categories): array
    {
        $defaults = $this->baseFinanceCategories();
        $rows = $categories === [] ? $defaults : $categories;
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;
            $key = preg_replace('/[^a-zA-Z0-9_.\-]/', '', (string)($row['key'] ?? ''));
            $name = mb_substr(trim((string)($row['name'] ?? '')), 0, 40);
            if ($key === '' || $name === '') continue;
            $direction = (string)($row['direction'] ?? 'expense') === 'income' ? 'income' : 'expense';
            $scope = mb_substr(trim((string)($row['scope'] ?? 'general')), 0, 30);
            $sourcePlugin = mb_substr(trim((string)($row['source_plugin'] ?? '')), 0, 40);
            if ($sourcePlugin !== '' && $sourcePlugin !== 'hsx_erp'
                && !str_starts_with($key, $sourcePlugin . '.')
                && !str_starts_with($key, $sourcePlugin . '_')) {
                Log::warning('[hsx_erp] 收支分类 key 未使用插件命名空间，已忽略：site_id=' . $this->site_id . ' key=' . $key . ' plugin=' . $sourcePlugin);
                continue;
            }
            if (isset($normalized[$key])) {
                $owner = (string)($normalized[$key]['source_plugin'] ?? '');
                if ($owner !== $sourcePlugin) {
                    Log::warning('[hsx_erp] 收支分类 key 冲突，保留先注册项：site_id=' . $this->site_id . ' key=' . $key . ' owner=' . $owner . ' rejected=' . $sourcePlugin);
                    continue;
                }
            }
            $statementGroup = (string)($row['statement_group'] ?? '');
            $allowedStatementGroups = ['revenue', 'revenue_reversal', 'purchase', 'purchase_reversal', 'operating_expense', 'other_income', 'other_expense', 'advance_receipt', 'advance_receipt_reversal'];
            if (!in_array($statementGroup, $allowedStatementGroups, true)) {
                $statementGroup = $scope === 'refurbish'
                    ? 'operating_expense'
                    : ($direction === 'income' ? 'other_income' : 'other_expense');
            }
            $normalized[$key] = [
                'key' => $key, 'name' => $name, 'direction' => $direction,
                'scope' => $scope,
                'statement_group' => $statementGroup,
                'affects_asset_cost' => $this->boolInt($row['affects_asset_cost'] ?? 0),
                'creates_finance' => $this->boolInt($row['creates_finance'] ?? 1),
                'party_required' => $this->boolInt($row['party_required'] ?? 0),
                'source_plugin' => $sourcePlugin,
                'source_key' => mb_substr(trim((string)($row['source_key'] ?? $key)), 0, 80),
                'enabled' => $this->boolInt($row['enabled'] ?? 1),
                'sort' => (int)($row['sort'] ?? (100 - $index)),
            ];
        }
        $rows = array_values($normalized ?: array_column($defaults, null, 'key'));
        usort($rows, static fn(array $a, array $b): int => (int)$b['sort'] <=> (int)$a['sort']);
        return $rows;
    }

    private function normalizeBusinessSourceOptions(array $sources): array
    {
        $normalized = [];
        foreach ($sources as $index => $row) {
            if (!is_array($row)) continue;
            $key = preg_replace('/[^a-zA-Z0-9_.\-]/', '', trim((string)($row['key'] ?? '')));
            $name = mb_substr(trim((string)($row['name'] ?? '')), 0, 40);
            $sourcePlugin = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string)($row['source_plugin'] ?? '')));
            $sourceKey = preg_replace('/[^a-zA-Z0-9_.\-]/', '', trim((string)($row['source_key'] ?? '')));
            $scene = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string)($row['scene'] ?? '')));
            if ($key === '' || $name === '' || $sourcePlugin === '' || $sourceKey === '' || $scene === '') {
                Log::warning('[hsx_erp] 忽略无效业务来源扩展：site_id=' . $this->site_id . ' data=' . json_encode($row, JSON_UNESCAPED_UNICODE));
                continue;
            }
            if ($sourcePlugin !== 'hsx_erp'
                && !str_starts_with($key, $sourcePlugin . '.')
                && !str_starts_with($key, $sourcePlugin . '_')) {
                Log::warning('[hsx_erp] 业务来源 key 未使用插件命名空间，已忽略：site_id=' . $this->site_id . ' key=' . $key . ' plugin=' . $sourcePlugin);
                continue;
            }
            if (isset($normalized[$key])) {
                $owner = (string)($normalized[$key]['source_plugin'] ?? '');
                Log::warning('[hsx_erp] 业务来源 key 冲突，保留先注册项：site_id=' . $this->site_id . ' key=' . $key . ' owner=' . $owner . ' rejected=' . $sourcePlugin);
                continue;
            }
            $normalized[$key] = [
                'key' => $key,
                'name' => $name,
                'direction' => (string)($row['direction'] ?? '') === 'income' ? 'income' : 'expense',
                'scene' => $scene,
                'source_plugin' => $sourcePlugin,
                'source_key' => $sourceKey,
                'enabled' => $this->boolInt($row['enabled'] ?? 1),
                'sort' => (int)($row['sort'] ?? (100 - $index)),
            ];
        }
        $rows = array_values($normalized);
        usort($rows, static fn(array $a, array $b): int => (int)$b['sort'] <=> (int)$a['sort']);
        return $rows;
    }

    private function baseSaleChannelOptions(): array
    {
        return [
            ['key' => 'erp_peer', 'name' => '同行', 'channel_type' => 'peer', 'source_plugin' => 'hsx_erp', 'source_key' => 'erp_outbound', 'enabled' => 1, 'is_default' => 1, 'sort' => 100],
            ['key' => 'erp_store', 'name' => '门店', 'channel_type' => 'store', 'source_plugin' => 'hsx_erp', 'source_key' => 'store', 'enabled' => 1, 'is_default' => 0, 'sort' => 90],
        ];
    }

    private function baseFinanceCategories(): array
    {
        return [
            ['key' => 'sale_revenue', 'name' => '销售收入', 'direction' => 'income', 'scope' => 'sale', 'statement_group' => 'revenue', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'sale_revenue', 'enabled' => 1, 'sort' => 200],
            ['key' => 'inventory_purchase', 'name' => '设备采购支出', 'direction' => 'expense', 'scope' => 'purchase', 'statement_group' => 'purchase', 'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'inventory_purchase', 'enabled' => 1, 'sort' => 190],
            ['key' => 'sale_refund', 'name' => '销售退货退款', 'direction' => 'expense', 'scope' => 'sale_return', 'statement_group' => 'revenue_reversal', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'sale_refund', 'enabled' => 1, 'sort' => 180],
            ['key' => 'purchase_refund', 'name' => '采购退货款收回', 'direction' => 'income', 'scope' => 'purchase_return', 'statement_group' => 'purchase_reversal', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'purchase_refund', 'enabled' => 1, 'sort' => 170],
            ['key' => 'after_sale_compensation', 'name' => '售后补差', 'direction' => 'expense', 'scope' => 'sale_compensation', 'statement_group' => 'revenue_reversal', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'after_sale_compensation', 'enabled' => 1, 'sort' => 160],
            ['key' => 'refurbish_labor', 'name' => '整备人工费', 'direction' => 'expense', 'scope' => 'refurbish', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'refurbish_labor', 'enabled' => 1, 'sort' => 100],
            ['key' => 'refurbish_parts', 'name' => '整备配件费', 'direction' => 'expense', 'scope' => 'refurbish', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'refurbish_parts', 'enabled' => 1, 'sort' => 90],
            ['key' => 'refurbish_repair', 'name' => '外修费用', 'direction' => 'expense', 'scope' => 'refurbish', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'refurbish_repair', 'enabled' => 1, 'sort' => 80],
            ['key' => 'refurbish_inspection', 'name' => '检测费用', 'direction' => 'expense', 'scope' => 'refurbish', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'refurbish_inspection', 'enabled' => 1, 'sort' => 70],
            ['key' => 'refurbish_logistics', 'name' => '整备物流费', 'direction' => 'expense', 'scope' => 'refurbish', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'refurbish_logistics', 'enabled' => 1, 'sort' => 60],
            ['key' => 'refurbish_mixed', 'name' => '综合整备费用', 'direction' => 'expense', 'scope' => 'refurbish', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'refurbish_mixed', 'enabled' => 1, 'sort' => 61],
            ['key' => 'operating_rent', 'name' => '房租物业', 'direction' => 'expense', 'scope' => 'operating', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'operating_rent', 'enabled' => 1, 'sort' => 59],
            ['key' => 'operating_utilities', 'name' => '水电网络', 'direction' => 'expense', 'scope' => 'operating', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'operating_utilities', 'enabled' => 1, 'sort' => 58],
            ['key' => 'operating_office', 'name' => '办公用品', 'direction' => 'expense', 'scope' => 'operating', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'operating_office', 'enabled' => 1, 'sort' => 57],
            ['key' => 'operating_salary', 'name' => '工资劳务', 'direction' => 'expense', 'scope' => 'operating', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'operating_salary', 'enabled' => 1, 'sort' => 56],
            ['key' => 'operating_marketing', 'name' => '推广营销', 'direction' => 'expense', 'scope' => 'operating', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'operating_marketing', 'enabled' => 1, 'sort' => 55],
            ['key' => 'operating_logistics', 'name' => '经营物流', 'direction' => 'expense', 'scope' => 'operating', 'statement_group' => 'operating_expense', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'operating_logistics', 'enabled' => 1, 'sort' => 54],
            ['key' => 'operating_service_income', 'name' => '维修服务收入', 'direction' => 'income', 'scope' => 'operating', 'statement_group' => 'other_income', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1, 'source_plugin' => 'hsx_erp', 'source_key' => 'operating_service_income', 'enabled' => 1, 'sort' => 53],
            ['key' => 'other_expense', 'name' => '其他支出', 'direction' => 'expense', 'scope' => 'general', 'statement_group' => 'other_expense', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 0, 'source_plugin' => 'hsx_erp', 'source_key' => 'other_expense', 'enabled' => 1, 'sort' => 20],
            ['key' => 'other_income', 'name' => '其他收入', 'direction' => 'income', 'scope' => 'general', 'statement_group' => 'other_income', 'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 0, 'source_plugin' => 'hsx_erp', 'source_key' => 'other_income', 'enabled' => 1, 'sort' => 10],
        ];
    }

    private function baseBusinessSourceOptions(): array
    {
        return [
            ['key' => 'hsx_erp.manual_purchase', 'name' => 'ERP采购', 'direction' => 'expense', 'scene' => 'purchase', 'source_plugin' => 'hsx_erp', 'source_key' => 'manual_purchase', 'enabled' => 1, 'sort' => 200],
            ['key' => 'hsx_erp.manual_sale', 'name' => 'ERP销售', 'direction' => 'income', 'scene' => 'sale', 'source_plugin' => 'hsx_erp', 'source_key' => 'manual_sale', 'enabled' => 1, 'sort' => 190],
            ['key' => 'hsx_erp.manual_purchase_return', 'name' => 'ERP采购退货', 'direction' => 'income', 'scene' => 'purchase_return', 'source_plugin' => 'hsx_erp', 'source_key' => 'manual_purchase_return', 'enabled' => 1, 'sort' => 180],
            ['key' => 'hsx_erp.manual_sale_return', 'name' => 'ERP销售退货', 'direction' => 'expense', 'scene' => 'sale_return', 'source_plugin' => 'hsx_erp', 'source_key' => 'manual_sale_return', 'enabled' => 1, 'sort' => 170],
            ['key' => 'hsx_erp.manual_refurbish', 'name' => 'ERP整备', 'direction' => 'expense', 'scene' => 'refurbish', 'source_plugin' => 'hsx_erp', 'source_key' => 'manual_refurbish', 'enabled' => 1, 'sort' => 160],
        ];
    }

    private function extensionRows(string $eventName, string $bucket): array
    {
        try {
            $results = event($eventName, ['site_id' => $this->site_id, 'erp_version' => '0.0.1']);
        } catch (\Throwable $e) {
            Log::warning('[hsx_erp] 动态字典 Hook 加载失败：site_id=' . $this->site_id . ' event=' . $eventName . ' error=' . $e->getMessage());
            return [];
        }
        $rows = [];
        foreach ((array)$results as $result) {
            if (!is_array($result) || $result === []) continue;
            $resultRows = isset($result[$bucket]) && is_array($result[$bucket]) ? $result[$bucket] : $result;
            if (isset($resultRows['key'])) $resultRows = [$resultRows];
            foreach ($resultRows as $row) if (is_array($row)) $rows[] = $row;
        }
        return $rows;
    }

    public static function defaults(): array
    {
        return [
            'finance' => [
                'enable_offset' => 1,
                'finance_fact_lock' => 1,
                'settlement_requires_account' => 1,
            ],
            'purchase' => [
                'create_payable_on_inbound' => 1,
                'allow_cancel_before_finance_fact' => 1,
                // quick：只录采购事实；complete：同页完善销售资料；
                // collaborative：采购事实入库后交由拍摄、定价等任务承接。
                'mobile_entry_mode' => 'quick',
            ],
            'product_title' => [
                'category_mode' => 'auto',
                'spec_in_title' => 1,
                'grade_in_title' => 0,
                'separator' => ' ',
            ],
            'sale' => [
                'create_receivable_on_outbound' => 1,
                'allow_cancel_before_finance_fact' => 1,
                'return_to_original_location_on_cancel' => 1,
                'enable_peer_pending' => 1,
                'enable_trial_sale' => 0,
                'profit_confirm_mode' => 'settlement',
                'credit_control' => [
                    'enabled' => 1,
                    'default_policy' => 'remind',
                    'min_outstanding_amount' => 0,
                    'min_outstanding_days' => 0,
                ],
            ],
            'refurbish' => [
                'enabled' => 1,
                'default_required' => 0,
                'tracking_mode' => 'simple',
                'daily_reminder_enabled' => 1,
                'daily_reminder_threshold' => 25,
                'reminder_dismiss_date' => '',
            ],
            'turnover' => [
                'attention_days' => 7,
                'warning_days' => 15,
                'critical_days' => 30,
                'reminder_enabled' => 1,
                'reminder_count_threshold' => 1,
                'reminder_dismiss_date' => '',
            ],
            'category_sync' => [
                'enabled' => 0,
                'initialized' => 0,
                'provider' => 'phone_shop',
                'mode' => 'disabled',
                'last_action' => '',
            ],
            'listing_workspace' => [
                // one_stop：一个人在同一表单完成；split：拍摄、销售定价分岗；
                // photo_price：拍摄与销售定价由同一岗位连续完成。
                'mode' => 'one_stop',
                // auto：中台可用时使用中台，否则自动降级为 ERP 普通上传。
                'media_provider' => 'auto',
                // 关闭时资料齐全后保留人工确认，避免误上架。
                'auto_publish' => 0,
                'fallback_to_erp' => 1,
                'field_rules' => ErpListingFormContract::defaults(),
            ],
            'marketplace' => [
                // 旧字段由 channels.phone_shop.publish_mode 派生，供旧调用方兼容。
                'recycle_material_owner' => 'erp',
                'channels' => [
                    'phone_shop' => [
                        'enabled' => 1,
                        // erp：商城消费 ERP 投影；independent：商城保留独立数据并建立映射。
                        'category_mode' => 'erp',
                        'spec_mode' => 'erp',
                        // direct：ERP 一键发布；manual：进入商城运营待办。
                        'publish_mode' => 'direct',
                    ],
                ],
            ],
            'consignment' => [
                'enabled' => 0,
                'settle_payable_after_receipt' => 1,
                'transfer_to_owned_requires_repurchase' => 1,
            ],
        ];
    }

    /** 首页整备量提醒：关闭今天或永久关闭此类提醒。 */
    public function dismissRefurbishReminder(string $mode): array
    {
        $rules = $this->getRules();
        if ($mode === 'forever') {
            $rules['refurbish']['daily_reminder_enabled'] = 0;
            $rules['refurbish']['reminder_dismiss_date'] = '';
        } else {
            $rules['refurbish']['reminder_dismiss_date'] = date('Y-m-d');
        }
        return $this->saveRules($rules);
    }

    /** 首页库存周转提醒：关闭今天或永久关闭此类提醒。 */
    public function dismissTurnoverReminder(string $mode): array
    {
        $rules = $this->getRules();
        if ($mode === 'forever') {
            $rules['turnover']['reminder_enabled'] = 0;
            $rules['turnover']['reminder_dismiss_date'] = '';
        } else {
            $rules['turnover']['reminder_dismiss_date'] = date('Y-m-d');
        }
        return $this->saveRules($rules);
    }
}
