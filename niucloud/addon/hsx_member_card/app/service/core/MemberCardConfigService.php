<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\core;

use app\service\core\sys\CoreConfigService;

final class MemberCardConfigService
{
    public const CONFIG_KEY = 'HSX_MEMBER_CARD_CONFIG';

    public function get(int $siteId): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        return $this->normalize(is_array($value) ? $value : []);
    }

    public function save(int $siteId, array $data): array
    {
        $config = $this->normalize(array_replace($this->get($siteId), $data));
        (new CoreConfigService())->setConfig($siteId, self::CONFIG_KEY, $config);
        return $config;
    }

    private function normalize(array $data): array
    {
        $inventoryMode = (string)($data['inventory_mode'] ?? 'none');
        if (!in_array($inventoryMode, ['none', 'auto', 'strict'], true)) $inventoryMode = 'none';
        $accounts = array_key_exists('local_capital_accounts', $data)
            ? $this->normalizeAccounts((array)$data['local_capital_accounts'])
            : $this->defaultAccounts();
        $defaultAccountId = max(0, (int)($data['default_capital_account_id'] ?? 0));
        // 此 ID 也可能属于 ERP，不能拿独立账户列表覆盖它。
        // 当前收款渠道的有效性由管理服务 / 财务网关校验。
        if ($defaultAccountId === 0) {
            $default = current(array_filter(
                $accounts,
                static fn(array $account): bool => (int)$account['status'] === 1 && (int)$account['is_default'] === 1
            ));
            $defaultAccountId = (int)(($default ?: [])['id'] ?? 0);
        }
        return [
            'allow_receivable' => (int)($data['allow_receivable'] ?? 1) === 1 ? 1 : 0,
            'allow_unpaid_redemption' => (int)($data['allow_unpaid_redemption'] ?? 1) === 1 ? 1 : 0,
            'default_capital_account_id' => $defaultAccountId,
            'local_capital_accounts' => $accounts,
            // v0.0.1 固定规则：只展示说明，不允许通过配置关闭。
            'require_mobile_name_confirmation' => 1,
            'default_redeem_times' => 1,
            'finance_retry_limit' => min(20, max(1, (int)($data['finance_retry_limit'] ?? 8))),
            'inventory_mode' => $inventoryMode,
            'inventory_warehouse_id' => max(0, (int)($data['inventory_warehouse_id'] ?? 0)),
            'inventory_location_id' => max(0, (int)($data['inventory_location_id'] ?? 0)),
        ];
    }

    private function normalizeAccounts(array $accounts): array
    {
        $normalized = [];
        $used = [];
        foreach ($accounts as $index => $account) {
            if (!is_array($account)) continue;
            $id = max(1, (int)($account['id'] ?? 0));
            $name = mb_substr(trim((string)($account['name'] ?? '')), 0, 40);
            if ($id <= 0 || $name === '' || isset($used[$id])) continue;
            $used[$id] = true;
            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'type' => in_array((string)($account['type'] ?? ''), ['wechat', 'alipay', 'bank', 'cash', 'other'], true)
                    ? (string)$account['type']
                    : 'other',
                'status' => (int)($account['status'] ?? 1) === 1 ? 1 : 0,
                'is_default' => (int)($account['is_default'] ?? 0) === 1 ? 1 : 0,
                'sort' => max(0, (int)($account['sort'] ?? $index)),
            ];
        }
        usort($normalized, static fn(array $a, array $b): int => [$a['sort'], $a['id']] <=> [$b['sort'], $b['id']]);
        $hasDefault = false;
        foreach ($normalized as &$account) {
            if ($account['status'] === 1 && $account['is_default'] === 1 && !$hasDefault) {
                $hasDefault = true;
            } else {
                $account['is_default'] = 0;
            }
        }
        unset($account);
        if (!$hasDefault) {
            foreach ($normalized as &$account) {
                if ($account['status'] === 1) {
                    $account['is_default'] = 1;
                    break;
                }
            }
            unset($account);
        }
        return array_values($normalized);
    }

    private function defaultAccounts(): array
    {
        return [
            ['id' => 1, 'name' => '微信收款', 'type' => 'wechat', 'status' => 1, 'is_default' => 1, 'sort' => 10],
            ['id' => 2, 'name' => '支付宝收款', 'type' => 'alipay', 'status' => 1, 'is_default' => 0, 'sort' => 20],
            ['id' => 3, 'name' => '银行卡', 'type' => 'bank', 'status' => 1, 'is_default' => 0, 'sort' => 30],
            ['id' => 4, 'name' => '现金', 'type' => 'cash', 'status' => 1, 'is_default' => 0, 'sort' => 40],
        ];
    }
}
