<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use core\base\BaseAdminService;
use think\facade\Db;

class ErpLedgerService extends BaseAdminService
{
    private static bool $moneyLedgerSchemaEnsured = false;
    private static bool $assetLedgerSchemaEnsured = false;

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
            'amount' => round((float)($data['amount'] ?? 0), 2),
            'balance_after' => round((float)($data['balance_after'] ?? 0), 2),
            'party_id' => (int)($data['party_id'] ?? 0),
            'party_name' => (string)($data['party_name'] ?? ''),
            'asset_id' => (int)($data['asset_id'] ?? 0),
            'source_type' => (string)($data['source_type'] ?? ''),
            'source_id' => (int)($data['source_id'] ?? 0),
            'source_no' => (string)($data['source_no'] ?? ''),
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'occurred_at' => (int)($data['occurred_at'] ?? $now),
            'remark' => (string)($data['remark'] ?? ''),
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

    public function money(array $data): int
    {
        $this->ensureMoneyLedgerSchema();
        $now = time();
        $row = ErpMoneyLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => self::makeNo('ML'),
            'settlement_id' => (int)($data['settlement_id'] ?? 0),
            'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
            'capital_account_name' => (string)($data['capital_account_name'] ?? ''),
            'direction' => (string)($data['direction'] ?? 'in'),
            'amount' => round((float)($data['amount'] ?? 0), 2),
            'balance_after' => round((float)($data['balance_after'] ?? 0), 2),
            'party_id' => (int)($data['party_id'] ?? 0),
            'party_name' => (string)($data['party_name'] ?? ''),
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'occurred_at' => (int)($data['occurred_at'] ?? $now),
            'remark' => (string)($data['remark'] ?? ''),
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

    private function ensureMoneyLedgerSchema(): void
    {
        if (self::$moneyLedgerSchemaEnsured) {
            return;
        }
        self::$moneyLedgerSchemaEnsured = true;
        $table = (new ErpMoneyLedger())->getTable();
        $columns = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'balance_after'");
        if (empty($columns)) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `balance_after` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '记账后余额' AFTER `amount`");
        }
    }

    public function asset(array $data): int
    {
        $this->ensureAssetLedgerSchema();
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
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'remark' => (string)($data['remark'] ?? ''),
            'extra_json' => isset($data['extra']) ? json_encode($data['extra'], JSON_UNESCAPED_UNICODE) : (string)($data['extra_json'] ?? ''),
            'occurred_at' => (int)($data['occurred_at'] ?? $now),
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

    public function ensureAssetLedgerSchema(): void
    {
        if (self::$assetLedgerSchemaEnsured) {
            return;
        }
        self::$assetLedgerSchemaEnsured = true;
        $table = (new ErpAssetLedger())->getTable();
        $columns = [
            'ledger_no' => "`ledger_no` varchar(40) NOT NULL DEFAULT '' COMMENT '流水号' AFTER `site_id`",
            'asset_no' => "`asset_no` varchar(40) NOT NULL DEFAULT '' COMMENT '设备资产号快照' AFTER `asset_id`",
            'imei' => "`imei` varchar(80) NOT NULL DEFAULT '' COMMENT 'IMEI快照' AFTER `asset_no`",
            'model' => "`model` varchar(120) NOT NULL DEFAULT '' COMMENT '型号快照' AFTER `imei`",
            'before_warehouse_id' => "`before_warehouse_id` int NOT NULL DEFAULT 0 AFTER `after_status`",
            'before_warehouse_name' => "`before_warehouse_name` varchar(100) NOT NULL DEFAULT '' AFTER `before_warehouse_id`",
            'before_location_id' => "`before_location_id` int NOT NULL DEFAULT 0 AFTER `before_warehouse_name`",
            'before_location_name' => "`before_location_name` varchar(100) NOT NULL DEFAULT '' AFTER `before_location_id`",
            'after_warehouse_id' => "`after_warehouse_id` int NOT NULL DEFAULT 0 AFTER `before_location_name`",
            'after_warehouse_name' => "`after_warehouse_name` varchar(100) NOT NULL DEFAULT '' AFTER `after_warehouse_id`",
            'after_location_id' => "`after_location_id` int NOT NULL DEFAULT 0 AFTER `after_warehouse_name`",
            'after_location_name' => "`after_location_name` varchar(100) NOT NULL DEFAULT '' AFTER `after_location_id`",
            'before_total_cost' => "`before_total_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '变化前成本' AFTER `after_location_name`",
            'after_total_cost' => "`after_total_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '变化后成本' AFTER `before_total_cost`",
            'cost_delta' => "`cost_delta` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '成本变化' AFTER `after_total_cost`",
            'party_id' => "`party_id` int NOT NULL DEFAULT 0 AFTER `cost_delta`",
            'party_name' => "`party_name` varchar(100) NOT NULL DEFAULT '' AFTER `party_id`",
            'source_no' => "`source_no` varchar(40) NOT NULL DEFAULT '' AFTER `source_id`",
            'extra_json' => "`extra_json` longtext COMMENT '扩展快照' AFTER `remark`",
            'occurred_at' => "`occurred_at` int NOT NULL DEFAULT 0 AFTER `extra_json`",
        ];
        foreach ($columns as $column => $definition) {
            $rows = Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            if (empty($rows)) {
                Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
            }
        }
    }
}
