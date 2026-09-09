<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\service\core\MemberCardConfigService;
use core\base\BaseAdminService;

final class MemberCardConfigAdminService extends BaseAdminService
{
    public function info(): array
    {
        $config = (new MemberCardConfigService())->get((int)$this->site_id);
        $gateway = new MemberCardFinanceGateway();
        $config['finance_provider'] = $gateway->usesErp() ? 'erp' : 'local';
        $config['finance_provider_name'] = $gateway->usesErp() ? 'ERP 资金账户' : '会员卡独立收款';
        $config['capital_account_options'] = $gateway->usesErp()
            ? $gateway->capitalAccountOptions()
            : $this->localCapitalAccountOptions((array)($config['local_capital_accounts'] ?? []));
        $config['receivable_available'] = $gateway->usesErp() ? 1 : 0;
        if (!$gateway->usesErp()) $config['allow_receivable'] = 0;
        $enabledAccounts = array_values(array_filter(
            $config['capital_account_options'],
            static fn(array $row): bool => !array_key_exists('status', $row) || (int)$row['status'] === 1
        ));
        $ids = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $enabledAccounts);
        if (!in_array((int)$config['default_capital_account_id'], $ids, true)) {
            $default = current(array_filter($enabledAccounts, static fn(array $row): bool => (int)($row['is_default'] ?? 0) === 1));
            $config['default_capital_account_id'] = (int)(($default ?: ($enabledAccounts[0] ?? []))['id'] ?? 0);
        }
        // 列表标记与实际默认账户保持一致，避免编辑旧默认账户时意外改回。
        $defaultId = (int)$config['default_capital_account_id'];
        foreach ($config['capital_account_options'] as &$account) {
            $account['is_default'] = $defaultId > 0 && (int)$account['id'] === $defaultId ? 1 : 0;
        }
        unset($account);
        $inventory = (new MemberCardInventoryGateway())->capability();
        $config['inventory_available'] = (int)($inventory['available'] ?? 0);
        $config['inventory_provider_name'] = (string)($inventory['provider_name'] ?? '未接入 ERP 数量库存');
        $config['inventory_warehouses'] = (array)($inventory['warehouses'] ?? []);
        if ($config['inventory_available'] !== 1) {
            $config['inventory_mode_effective'] = 'none';
            $config['inventory_warning'] = '未安装或未启用 ERP，当前仅记录会员卡核销，不管理耗材库存。';
        } else {
            $config['inventory_mode_effective'] = (string)$config['inventory_mode'];
            $config['inventory_warning'] = '';
        }
        return $config;
    }

    private function localCapitalAccountOptions(array $accounts): array
    {
        $typeNames = ['wechat' => '微信', 'alipay' => '支付宝', 'bank' => '银行卡', 'cash' => '现金', 'other' => '其他'];
        return array_map(static function (array $row) use ($typeNames): array {
            $type = (string)($row['type'] ?? 'other');
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'type' => $type,
                'type_name' => $typeNames[$type] ?? '其他',
                'status' => (int)($row['status'] ?? 0),
                'is_default' => (int)($row['is_default'] ?? 0),
                'sort' => (int)($row['sort'] ?? 0),
                'provider' => 'local',
            ];
        }, $accounts);
    }

    public function save(array $data): array
    {
        $current = (new MemberCardConfigService())->get((int)$this->site_id);
        $merged = array_replace($current, $data);
        $inventoryMode = (string)($merged['inventory_mode'] ?? 'none');
        if (!in_array($inventoryMode, ['none', 'auto', 'strict'], true)) {
            throw new \core\exception\CommonException('耗材库存模式不正确');
        }
        if ($inventoryMode !== 'none') {
            $inventory = (new MemberCardInventoryGateway())->capability();
            if ((int)($inventory['available'] ?? 0) !== 1) {
                throw new \core\exception\CommonException('自动或严格库存需要先安装并启用 ERP');
            }
            $warehouseId = max(0, (int)($merged['inventory_warehouse_id'] ?? 0));
            $locationId = max(0, (int)($merged['inventory_location_id'] ?? 0));
            $valid = false;
            foreach ((array)($inventory['warehouses'] ?? []) as $warehouse) {
                if ((int)($warehouse['id'] ?? 0) !== $warehouseId) continue;
                foreach ((array)($warehouse['locations'] ?? []) as $location) {
                    if ((int)($location['id'] ?? 0) === $locationId) $valid = true;
                }
            }
            if (!$valid) throw new \core\exception\CommonException('请选择有效的 ERP 耗材仓库和库位');
        }
        $accountId = max(0, (int)($merged['default_capital_account_id'] ?? 0));
        if ($accountId > 0) {
            $ids = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), (new MemberCardFinanceGateway())->capitalAccountOptions());
            if (!in_array($accountId, $ids, true)) throw new \core\exception\CommonException('默认资金账户不存在或已停用');
        }
        (new MemberCardConfigService())->save((int)$this->site_id, $data);
        return $this->info();
    }

    public function saveCapitalAccount(array $data): array
    {
        if ((new MemberCardFinanceGateway())->usesErp()) {
            throw new \core\exception\CommonException('当前已接入 ERP，请在 ERP 资金账户中维护');
        }
        $service = new MemberCardConfigService();
        $config = $service->get((int)$this->site_id);
        $accounts = (array)($config['local_capital_accounts'] ?? []);
        $id = max(0, (int)($data['id'] ?? 0));
        $name = mb_substr(trim((string)($data['name'] ?? '')), 0, 40);
        if ($name === '') throw new \core\exception\CommonException('请填写收款账户名称');
        if ($id <= 0) {
            $maxId = 0;
            foreach ($accounts as $account) $maxId = max($maxId, (int)($account['id'] ?? 0));
            $id = $maxId + 1;
        }
        $row = [
            'id' => $id,
            'name' => $name,
            'type' => (string)($data['type'] ?? 'other'),
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'is_default' => (int)($data['is_default'] ?? 0) === 1 ? 1 : 0,
            'sort' => max(0, (int)($data['sort'] ?? 0)),
        ];
        if ($row['status'] !== 1) $row['is_default'] = 0;
        $found = false;
        foreach ($accounts as $index => $account) {
            if ((int)($account['id'] ?? 0) !== $id) continue;
            $accounts[$index] = $row;
            $found = true;
            break;
        }
        if (!$found) $accounts[] = $row;
        if ($row['is_default'] === 1) {
            foreach ($accounts as &$account) $account['is_default'] = (int)($account['id'] ?? 0) === $id ? 1 : 0;
            unset($account);
            $config['default_capital_account_id'] = $id;
        } elseif ($row['status'] !== 1 && (int)$config['default_capital_account_id'] === $id) {
            $config['default_capital_account_id'] = 0;
        }
        $config['local_capital_accounts'] = $accounts;
        $service->save((int)$this->site_id, $config);
        return $this->info();
    }

    public function deleteCapitalAccount(int $id): array
    {
        if ((new MemberCardFinanceGateway())->usesErp()) {
            throw new \core\exception\CommonException('当前已接入 ERP，请在 ERP 资金账户中维护');
        }
        $service = new MemberCardConfigService();
        $config = $service->get((int)$this->site_id);
        $accounts = array_values(array_filter(
            (array)($config['local_capital_accounts'] ?? []),
            static fn(array $row): bool => (int)($row['id'] ?? 0) !== $id
        ));
        if (count($accounts) === count((array)($config['local_capital_accounts'] ?? []))) {
            throw new \core\exception\CommonException('收款账户不存在');
        }
        $config['local_capital_accounts'] = $accounts;
        if ((int)$config['default_capital_account_id'] === $id) $config['default_capital_account_id'] = 0;
        $service->save((int)$this->site_id, $config);
        return $this->info();
    }
}
