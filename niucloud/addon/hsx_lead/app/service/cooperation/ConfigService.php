<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use addon\hsx_lead\app\service\{Context,CatalogConfigStorage};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\{Cache,Db};

final class ConfigService
{
    public const KEY = 'HSX_LEAD_COOPERATION';
    public function __construct(private Context $context)
    {
    }
    public static function defaults(): array
    {
        return ['enabled' => false, 'publishing_required' => true, 'listing_login_required' => false, 'erp_visible' => true,
            'fee_percent' => '10.00', 'fee_minimum' => '9.90', 'fee_confirmed' => false, 'commission_minimum' => '50.00', 'response_minutes' => 30,
            'contact_hours' => 24, 'review_hours' => 48, 'inventory_fresh_hours' => 24, 'demand_model_limit' => 5,
            'businesses' => BusinessService::defaults(), 'membership_levels' => []];
    }
    public function get(bool $lock = false): array
    {
        // Billing rules must be read from the persisted tenant record. The
        // framework's unversioned, permanent cache can be repopulated by a read
        // before a concurrent save transaction commits (or lose its tag index).
        // Large business directories may span multiple existing config rows;
        // the plugin store verifies and reads them in one consistent snapshot.
        $saved = CatalogConfigStorage::read($this->context->site, self::KEY, $lock);
        // General business settings and framework membership definitions are
        // separate sources. Unknown/retired keys are not reintroduced.
        $result = array_replace(self::defaults(), array_intersect_key($saved, self::defaults()));
        // Definitions belong to the framework membership editor, never to this
        // plugin's general configuration or a client-submitted tier array.
        $result['membership_levels'] = MembershipCatalogService::levels($this->context->site, $lock);
        $result['membership_configuration_source'] = 'framework_member_level';
        return $result;
    }
    public function publicConfig(): array
    {
        return $this->get();
    }
    /** Anonymous presentation only. Never expose member, ERP or billing data. */
    public static function publicAccess(int $site): array
    {
        if ($site <= 0) throw new CommonException('请先选择有效站点');
        $value = CatalogConfigStorage::read($site, self::KEY);
        $enabled = in_array($value['enabled'] ?? false, [true, 1, '1'], true);
        $businesses = [];
        $seen = [];
        // Project from this same read. Do not call get()/publicConfig(): those
        // include membership definitions, directory options and billing rules.
        if ($enabled && is_array($value['businesses'] ?? null))foreach (array_slice($value['businesses'], 0, 30) as $row) {
            if (!is_array($row) || !in_array($row['enabled'] ?? false, [true, 1, '1'], true))continue;
            $key = $row['key'] ?? null;
            $name = $row['name'] ?? null;
            $dimension = $row['dimension'] ?? null;
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,15}$/D', $key)
                || in_array($key, ['__proto__', 'prototype', 'constructor'], true) || isset($seen[$key])
                || !is_string($name)
                    || !mb_check_encoding($name, 'UTF-8')
                    || trim($name) === ''
                    || mb_strlen($name) > 100
                    || preg_match('/[<>\x00-\x1F\x7F]/u', $name)
                || !in_array($dimension, ['model', 'brand', 'operator'], true))continue;
            $seen[$key] = true;
            $businesses[] = ['key' => $key, 'name' => trim($name), 'dimension' => $dimension];
        }
        // Keep the historical flag for paired older clients. It is not an
        // authorization policy; new visitor pages never auto-launch login.
        return ['login_required' => in_array($value['listing_login_required'] ?? false, [true, 1, '1'], true),
            'erp_visible' => in_array($value['erp_visible'] ?? true, [true, 1, '1'], true),
            'visitor' => ['enabled' => $enabled, 'businesses' => $businesses]];
    }
    /** Presentation policy, not a destructive disconnect or scheduler switch. */
    public static function erpVisible(int $site): bool
    {
        if ($site <= 0)throw new CommonException('请先选择有效站点');
        $value = CatalogConfigStorage::read($site, self::KEY);
        return in_array($value['erp_visible'] ?? true, [true, 1, '1'], true);
    }
    public static function assertErpVisible(int $site): void
    {
        if (!self::erpVisible($site))throw new CommonException('本站暂未开放库存接入，请返回继续使用门店协作');
    }
    public function save(array $input): array
    {
        Store::permission($this->context, 'config');
        $next = $this->get();
        foreach (['enabled', 'publishing_required', 'fee_confirmed'] as $key) {
            if (!in_array($input[$key] ?? null, [true, false, 0, 1, '0', '1'], true)) throw new CommonException('请明确设置开关：'.$key);
            $next[$key] = in_array($input[$key], [true, 1, '1'], true);
        }
        $access = $input['listing_login_required'] ?? $next['listing_login_required'];
        if (!in_array($access, [true, false, 0, 1, '0', '1'], true)) throw new CommonException('请明确设置列表登录开关');
        $next['listing_login_required'] = in_array($access, [true, 1, '1'], true);
        $visible = $input['erp_visible'] ?? $next['erp_visible'];
        if (!in_array($visible, [true, false, 0, 1, '0', '1'], true))throw new CommonException('请明确设置ERP前台显示开关');
        $next['erp_visible'] = in_array($visible, [true, 1, '1'], true);
        foreach (['fee_percent', 'fee_minimum', 'commission_minimum'] as $key) $next[$key] = Rules::amount(Rules::cents($input[$key] ?? $next[$key]));
        foreach (['fee_minimum', 'commission_minimum'] as $key) if (Rules::cents($next[$key]) > 9999999999) throw new CommonException('配置金额超出框架账户精度范围，请核对：'.$key);
        if (Rules::cents($next['fee_percent']) >= 10000
            || Rules::cents($next['commission_minimum']) < 1
            || Rules::cents($next['fee_minimum']) >= Rules::cents($next['commission_minimum'])) throw new CommonException('成交最低协作费用须大于0，平台维护费须低于最低协作费用');
        if ($next['fee_confirmed']) Rules::split(Rules::cents($next['commission_minimum']), $next);
        foreach ([
                'response_minutes' => [0, 10080],
                'contact_hours' => [0, 720],
                'review_hours' => [0, 2160],
                'inventory_fresh_hours' => [1, 168]
            ] as $key => $range) {
            $v = $input[$key] ?? $next[$key];
            if ((!is_int($v) && !is_string($v))
                || !ctype_digit((string) $v)
                || (int) $v < $range[0]
                || (int) $v > $range[1]) throw new CommonException('请核对时间设置：'.$key);
            $next[$key] = (int) $v;
        }
        $next['demand_model_limit'] = Rules::demandLimit($input['demand_model_limit'] ?? $next['demand_model_limit']);
        // The general settings endpoint does not own catalog writes. Strip
        // copied directory data before validation; merge current data only
        // after obtaining the same site lock as CatalogService CRUD/imports.
        $drafts = $input['businesses'] ?? $next['businesses'];
        if (is_array($drafts))foreach ($drafts as &$draft)if (is_array($draft)) {
            unset($draft['options'],$draft['catalog_configured']);
        }unset($draft);
        $next['businesses'] = BusinessService::validate($drafts);
        if ($next['fee_confirmed']) foreach ($next['businesses'] as $business) if ($business['enabled']) Rules::split(Rules::cents($business['commission_minimum']), BusinessService::feeRules($business, $next));
        Db::transaction(function () use (&$next): void {
            Db::name('site')->where('site_id', $this->context->site)->lock(true)->find();
            $current = array_column($this->get()['businesses'], null, 'key');
            $presets = array_column(BusinessService::defaults(), null, 'key');
            foreach ($next['businesses'] as &$business) {
                $key = $business['key'];
                if (isset($current[$key])) {
                    $business['options'] = $current[$key]['options'] ?? [];
                    if (array_key_exists('catalog_configured', $current[$key]))$business['catalog_configured'] = $current[$key]['catalog_configured'];
                    else unset($business['catalog_configured']);
                } else {
                    // New built-in businesses start from server presets, not
                    // caller-supplied options. Custom businesses start empty.
                    $business['options'] = isset($presets[$key]) && $presets[$key]['dimension'] === $business['dimension'] ? $presets[$key]['options'] : [];
                    unset($business['catalog_configured']);
                }
            }unset($business);
            $next['businesses'] = BusinessService::validate($next['businesses']);
            // Remove only this exact framework entry; directory persistence is
            // plugin-owned and never relies on cached existence checks.
            Cache::delete('site_config_cache'.self::KEY.'_'.$this->context->site);
            $stored = $next;
            unset($stored['membership_levels'],$stored['membership_configuration_source']);
            CatalogConfigStorage::write($this->context->site, self::KEY, $stored);
            $this->context->audit('cooperation_config', 0, 'saved', [
                    'enabled' => $next['enabled'],
                    'fee_confirmed' => $next['fee_confirmed'],
                    'erp_visible' => $next['erp_visible']
                ]);
        });
        // Evict this exact framework entry after commit as well, not every
        // site's configuration. Return read-back, never just echo the request.
        Cache::delete('site_config_cache'.self::KEY.'_'.$this->context->site);
        $saved = $this->get();
        foreach ([
                'enabled',
                'publishing_required',
                'listing_login_required',
                'erp_visible',
                'fee_confirmed',
                'fee_percent',
                'fee_minimum',
                'commission_minimum'
            ] as $key) if ((string) $saved[$key] !== (string) $next[$key]) throw new CommonException('配置已提交，但回读结果已变化，请重新读取并核对，不要反复保存');
        return $saved;
    }
}
