<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use addon\hsx_erp\app\support\ErpMoney;
use core\base\BaseAdminService;
use think\facade\Log;

class ErpLedgerService extends BaseAdminService
{
    /** 跨插件事件不能依赖当前 HTTP 上下文，显式固定站点和经办人。 */
    public static function forSite(int $siteId, int $operatorUid = 0, string $operatorName = '系统自动'): self
    {
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $operatorUid;
        $service->username = $operatorName;
        return $service;
    }

    public static function makeNo(string $prefix): string
    {
        [$micro] = explode(' ', microtime());
        $micro = str_pad((string)(int)round((float)$micro * 1000000), 6, '0', STR_PAD_LEFT);
        return strtoupper($prefix) . date('YmdHis') . $micro . random_int(100, 999);
    }

    public function account(array $data): int
    {
        $now = time();
        $row = ErpAccountLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => self::makeNo('AL'),
            'biz_type' => (string)($data['biz_type'] ?? ''),
            'direction' => (string)($data['direction'] ?? ''),
            'amount' => ErpMoney::normalize($data['amount'] ?? 0),
            'balance_after' => ErpMoney::normalize($data['balance_after'] ?? 0),
            'party_id' => (int)($data['party_id'] ?? 0),
            'party_name' => (string)($data['party_name'] ?? ''),
            'asset_id' => (int)($data['asset_id'] ?? 0),
            'source_type' => (string)($data['source_type'] ?? ''),
            'source_id' => (int)($data['source_id'] ?? 0),
            'source_no' => (string)($data['source_no'] ?? ''),
            'operator_uid' => (int)($data['operator_uid'] ?? $this->uid),
            'operator_name' => (string)($data['operator_name'] ?? $this->username),
            'occurred_at' => (int)($data['occurred_at'] ?? $now),
            'remark' => (string)($data['remark'] ?? ''),
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

    public function money(array $data): int
    {
        $now = time();
        $row = ErpMoneyLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => self::makeNo('ML'),
            'settlement_id' => (int)($data['settlement_id'] ?? 0),
            'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
            'capital_account_name' => (string)($data['capital_account_name'] ?? ''),
            'direction' => (string)($data['direction'] ?? 'in'),
            'category_key' => (string)($data['category_key'] ?? ''),
            'category_name' => (string)($data['category_name'] ?? ''),
            'category_statement_group' => (string)($data['category_statement_group'] ?? ''),
            'category_source_plugin' => (string)($data['category_source_plugin'] ?? ''),
            'category_source_key' => (string)($data['category_source_key'] ?? ''),
            'amount' => ErpMoney::normalize($data['amount'] ?? 0),
            'balance_after' => ErpMoney::normalize($data['balance_after'] ?? 0),
            'party_id' => (int)($data['party_id'] ?? 0),
            'party_name' => (string)($data['party_name'] ?? ''),
            'operator_uid' => (int)($data['operator_uid'] ?? $this->uid),
            'operator_name' => (string)($data['operator_name'] ?? $this->username),
            'occurred_at' => (int)($data['occurred_at'] ?? $now),
            'voucher_urls' => is_array($data['voucher_urls'] ?? null) ? json_encode($data['voucher_urls'], JSON_UNESCAPED_UNICODE) : trim((string)($data['voucher_urls'] ?? '')),
            'remark' => (string)($data['remark'] ?? ''),
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

    public function asset(array $data): int
    {
        $now = time();
        $asset = null;
        $assetId = (int)($data['asset_id'] ?? 0);
        if ($assetId > 0) {
            $found = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->findOrEmpty();
            $asset = $found->isEmpty() ? null : $found;
        }
        $afterCost = array_key_exists('after_total_cost', $data)
            ? round((float)$data['after_total_cost'], 2)
            : ($asset ? round((float)$asset->total_cost, 2) : 0.0);
        $beforeCost = array_key_exists('before_total_cost', $data)
            ? round((float)$data['before_total_cost'], 2)
            : $afterCost;
        $row = ErpAssetLedger::create([
            'site_id' => $this->site_id,
            'request_id' => $data['request_id'] ?? null,
            'ledger_no' => (string)($data['ledger_no'] ?? self::makeNo('SL')),
            'asset_id' => $assetId,
            'asset_no' => (string)($data['asset_no'] ?? ($asset ? $asset->asset_no : '')),
            'imei' => (string)($data['imei'] ?? ($asset ? $asset->imei : '')),
            'model' => (string)($data['model'] ?? ($asset ? $asset->model : '')),
            'action' => (string)($data['action'] ?? ''),
            'before_status' => (string)($data['before_status'] ?? ''),
            'after_status' => (string)($data['after_status'] ?? ($asset ? $asset->status : '')),
            'before_warehouse_id' => (int)($data['before_warehouse_id'] ?? ($asset ? $asset->warehouse_id : 0)),
            'before_warehouse_name' => (string)($data['before_warehouse_name'] ?? ($asset ? $asset->warehouse_name : '')),
            'before_location_id' => (int)($data['before_location_id'] ?? ($asset ? $asset->location_id : 0)),
            'before_location_name' => (string)($data['before_location_name'] ?? ($asset ? $asset->location_name : '')),
            'after_warehouse_id' => (int)($data['after_warehouse_id'] ?? ($asset ? $asset->warehouse_id : 0)),
            'after_warehouse_name' => (string)($data['after_warehouse_name'] ?? ($asset ? $asset->warehouse_name : '')),
            'after_location_id' => (int)($data['after_location_id'] ?? ($asset ? $asset->location_id : 0)),
            'after_location_name' => (string)($data['after_location_name'] ?? ($asset ? $asset->location_name : '')),
            'before_total_cost' => $beforeCost,
            'after_total_cost' => $afterCost,
            'cost_delta' => array_key_exists('cost_delta', $data) ? round((float)$data['cost_delta'], 2) : round($afterCost - $beforeCost, 2),
            'party_id' => (int)($data['party_id'] ?? ($asset ? $asset->party_id : 0)),
            'party_name' => (string)($data['party_name'] ?? ($asset ? $asset->party_name : '')),
            'source_type' => (string)($data['source_type'] ?? ''),
            'source_id' => (int)($data['source_id'] ?? 0),
            'source_no' => (string)($data['source_no'] ?? ''),
            'operator_uid' => (int)($data['operator_uid'] ?? $this->uid),
            'operator_name' => (string)($data['operator_name'] ?? $this->username),
            'remark' => (string)($data['remark'] ?? ''),
            'extra_json' => isset($data['extra']) ? json_encode($data['extra'], JSON_UNESCAPED_UNICODE) : (string)($data['extra_json'] ?? ''),
            'occurred_at' => (int)($data['occurred_at'] ?? $now),
            'create_at' => $now,
        ]);
        $this->emitAssetPerformanceFact($row, $asset);
        return (int)$row->id;
    }

    /** 资产流水已经在业务事务内落库，可作为跨插件员工产出的稳定事实来源。 */
    private function emitAssetPerformanceFact(ErpAssetLedger $ledger, ?ErpAsset $asset): void
    {
        $metric = [
            'inbound' => ['key' => 'erp.purchase.inbound', 'name' => '采购入库', 'scope' => 'outcome', 'role' => 'purchaser'],
            'listing_photo_complete' => ['key' => 'erp.asset.photo.completed', 'name' => '设备拍照完成', 'scope' => 'action', 'role' => 'photographer'],
            'listing_price_complete' => ['key' => 'erp.asset.price.completed', 'name' => '商城销售定价', 'scope' => 'action', 'role' => 'listing_pricer'],
            'listing_material_complete' => ['key' => 'erp.asset.material.completed', 'name' => '商城资料完善', 'scope' => 'action', 'role' => 'listing_operator'],
            'listing_publish' => ['key' => 'erp.asset.listed', 'name' => '设备成功上架', 'scope' => 'outcome', 'role' => 'listing_operator'],
            'transfer' => ['key' => 'erp.asset.transferred', 'name' => '库存调拨', 'scope' => 'action', 'role' => 'warehouse'],
        ][(string)$ledger->action] ?? null;
        if (!$metric || (int)$ledger->operator_uid <= 0 || (int)$ledger->asset_id <= 0) return;

        try {
            event('HsxPerformanceFactRecorded', [
                'event_name' => 'performance.fact.recorded.v1',
                'event_version' => 1,
                'site_id' => (int)$ledger->site_id,
                'event_id' => 'hsx_erp:asset_ledger:' . (int)$ledger->id,
                'source_plugin' => 'hsx_erp',
                'business_chain' => 'stock',
                'metric_key' => $metric['key'],
                'metric_name' => $metric['name'],
                'fact_scope' => $metric['scope'],
                'fact_type' => 'original',
                'direction' => 1,
                'employee_uid' => (int)$ledger->operator_uid,
                'employee_name' => (string)$ledger->operator_name,
                'role_key' => $metric['role'],
                'business_type' => 'erp_asset',
                'business_id' => (string)$ledger->asset_id,
                'business_no' => (string)$ledger->asset_no,
                'asset_id' => (int)$ledger->asset_id,
                'imei' => (string)$ledger->imei,
                'quantity' => '1.00',
                'amount' => (string)((string)$ledger->action === 'inbound'
                    ? $ledger->after_total_cost
                    : ((string)$ledger->action === 'listing_price_complete' && $asset ? $asset->retail_price : '0.00')),
                'profit' => '0.00',
                'unit' => 'device',
                'occurred_at' => (int)$ledger->occurred_at,
                'dimensions' => [
                    'model' => (string)$ledger->model,
                    'action' => (string)$ledger->action,
                    'warehouse_name' => (string)$ledger->after_warehouse_name,
                    'location_name' => (string)$ledger->after_location_name,
                ],
                'source_route' => [
                    'app' => 'adminapp',
                    'path' => 'addon/hsx_erp/pages/stock/detail',
                    'query' => ['id' => (int)$ledger->asset_id],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ERP资产产出事实派发失败', [
                'site_id' => (int)$ledger->site_id,
                'asset_ledger_id' => (int)$ledger->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

}
