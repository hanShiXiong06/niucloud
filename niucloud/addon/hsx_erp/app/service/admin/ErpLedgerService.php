<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use addon\hsx_erp\app\support\ErpMoney;
use core\base\BaseAdminService;

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
        return (int)$row->id;
    }

}
