<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use app\service\admin\auth\AuthService;
use app\service\admin\sys\RoleService;
use core\base\BaseAdminService;

/**
 * ERP 字段级可见性。
 *
 * 菜单权限负责“能否进入某项业务”，这里负责同一业务页面内的敏感字段裁剪。
 * 客户端拿不到的字段不能仅依赖 v-if 隐藏，避免销售岗位通过接口看到成本和毛利。
 */
class ErpDataVisibilityService extends BaseAdminService
{
    private ?array $capabilities = null;

    public function stockCapabilities(): array
    {
        if ($this->capabilities !== null) return $this->capabilities;

        $auth = new AuthService();
        $relation = (array)($auth->getAuthRole((int)$this->site_id) ?? []);
        $isAdmin = AuthService::isSuperAdmin() || (int)($relation['is_admin'] ?? 0) === 1;
        $rules = [];
        if (!$isAdmin) {
            $roleIds = array_values(array_filter(array_map('intval', (array)($relation['role_ids'] ?? []))));
            if ($roleIds !== []) $rules = (new RoleService())->getMenuIdsByRoleIds((int)$this->site_id, $roleIds);
        }
        $hasAny = static fn(array $keys): bool => $isAdmin || array_intersect($keys, $rules) !== [];

        return $this->capabilities = [
            'is_admin' => $isAdmin ? 1 : 0,
            'view_cost' => $hasAny([
                'hsx_erp_stock_adjust_cost', 'hsx_erp_purchase_adjust_cost',
                'hsx_erp_payable', 'hsx_erp_operating_finance',
            ]) ? 1 : 0,
            'adjust_cost' => $hasAny(['hsx_erp_stock_adjust_cost', 'hsx_erp_purchase_adjust_cost']) ? 1 : 0,
            'view_profit' => $hasAny(['hsx_erp_sale_profit_report', 'hsx_erp_operating_finance']) ? 1 : 0,
            'view_supplier' => $hasAny(['hsx_erp_purchase', 'hsx_erp_purchase_info', 'hsx_erp_payable']) ? 1 : 0,
            'view_finance' => $hasAny([
                'hsx_erp_payable', 'hsx_erp_receivable', 'hsx_erp_operating_finance',
            ]) ? 1 : 0,
            'view_team_workload' => $hasAny(['hsx_erp_stock_listing_workload']) ? 1 : 0,
        ];
    }

    public function can(string $capability): bool
    {
        return (int)($this->stockCapabilities()[$capability] ?? 0) === 1;
    }

    /** 列表数据只保留当前岗位真正需要的数据。 */
    public function sanitizeStockRows(array $rows): array
    {
        foreach ($rows as &$row) $row = $this->sanitizeStockRow((array)$row);
        unset($row);
        return $rows;
    }

    public function sanitizeStockDetail(array $asset): array
    {
        $asset = $this->sanitizeStockRow($asset);
        if (!$this->can('view_cost')) {
            unset($asset['cost_summary']);
            if (is_array($asset['return_flow'] ?? null)) {
                $blocked = ($asset['return_flow']['returnable'] ?? true) === false;
                $this->forget($asset['return_flow'], [
                    'supplier_amount', 'purchase_cost', 'refurbish_cost', 'unclassified_cost',
                    'total_cost', 'default_return_amount', 'paid_amount', 'unpaid_amount',
                ]);
                if ($blocked) {
                    $asset['return_flow']['block_reason'] = '当前设备不满足标准采购退货条件，请联系有权限的负责人处理。';
                    $asset['return_flow']['description'] = $asset['return_flow']['block_reason'];
                }
            }
            foreach ((array)($asset['asset_ledgers'] ?? []) as &$ledger) {
                $this->forget($ledger, ['before_total_cost', 'after_total_cost', 'cost_delta']);
            }
            unset($ledger);
        }
        if (!$this->can('view_finance')) unset($asset['account_ledgers']);
        if (!$this->can('view_supplier') && is_array($asset['purchase_order'] ?? null)) {
            $this->forget($asset['purchase_order'], ['party_id', 'party_name', 'contact_name', 'mobile']);
        }
        if (!$this->can('view_finance')) {
            foreach (['purchase_order', 'sale_order'] as $orderKey) {
                if (!is_array($asset[$orderKey] ?? null)) continue;
                $this->forget($asset[$orderKey], [
                    'finance_status', 'settlement_status', 'settlement_type', 'settlement_name',
                    'paid_amount', 'unpaid_amount', 'settled_amount', 'receivable_amount', 'payable_amount',
                ]);
            }
        }
        if (!$this->can('view_profit') && is_array($asset['last_sale_item'] ?? null)) {
            $this->forget($asset['last_sale_item'], ['profit', 'cost_price', 'cost_amount']);
        }
        $asset['capabilities'] = $this->stockCapabilities();
        return $asset;
    }

    public function sanitizeTurnoverSummary(array $summary): array
    {
        if (!$this->can('view_cost')) {
            $this->forget($summary, [
                'total_cost', 'healthy_cost', 'attention_cost', 'warning_cost',
                'critical_cost', 'warning_total_cost',
            ]);
            foreach ((array)($summary['warehouse_risks'] ?? []) as &$row) unset($row['warning_cost']);
            unset($row);
        }
        $summary['capabilities'] = $this->stockCapabilities();
        return $summary;
    }

    public function sanitizeSerialTracePage(array $page): array
    {
        $page['data'] = $this->sanitizeStockRows((array)($page['data'] ?? []));
        $page['capabilities'] = $this->stockCapabilities();
        return $page;
    }

    public function sanitizeSerialTraceDetail(array $detail): array
    {
        $detail['cycles'] = $this->sanitizeStockRows((array)($detail['cycles'] ?? []));
        foreach ((array)($detail['timeline'] ?? []) as &$node) {
            if (!$this->can('view_cost')) {
                $this->forget($node, ['before_total_cost', 'after_total_cost', 'cost_delta']);
            }
            if (!$this->can('view_supplier')) {
                $action = (string)($node['action'] ?? '');
                $sourceType = (string)($node['source_type'] ?? '');
                if (str_contains($action, 'purchase') || str_contains($action, 'cost') || str_contains($sourceType, 'purchase')) {
                    $this->forget($node, ['party_id', 'party_name']);
                }
            }
        }
        unset($node);
        $detail['capabilities'] = $this->stockCapabilities();
        return $detail;
    }

    private function sanitizeStockRow(array $row): array
    {
        if (!$this->can('view_cost')) {
            $this->forget($row, [
                'purchase_cost', 'refurbish_cost', 'adjust_cost', 'total_cost', 'cost_summary',
                'inbound_settlement_amount', 'inbound_settled_amount',
            ]);
        }
        if (!$this->can('view_profit')) {
            $this->forget($row, ['profit', 'outbound_profit']);
        }
        if (!$this->can('view_finance')) {
            $this->forget($row, [
                'inbound_finance_status', 'outbound_finance_status',
                'inbound_settlement_amount', 'inbound_settled_amount',
                'outbound_settlement_amount', 'outbound_settled_amount',
            ]);
        }
        // 代卖设备的物权客户是库存事实，不按供应商敏感字段隐藏。
        $isConsigned = (string)($row['ownership_type'] ?? '') === 'consigned'
            || (string)($row['warehouse_policy']['warehouse_type'] ?? '') === 'consignment';
        if (!$this->can('view_supplier') && !$isConsigned) {
            $this->forget($row, ['party_id', 'party_name', 'inbound_party_name']);
        }
        return $row;
    }

    private function forget(array &$row, array $keys): void
    {
        foreach ($keys as $key) unset($row[$key]);
    }
}
