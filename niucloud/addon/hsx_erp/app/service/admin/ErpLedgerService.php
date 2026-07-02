<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use core\base\BaseAdminService;
use think\facade\Db;

class ErpLedgerService extends BaseAdminService
{
    private static bool $moneyLedgerSchemaEnsured = false;

    public static function makeNo(string $prefix): string
    {
        return strtoupper($prefix) . date('YmdHis') . substr((string)microtime(true), -4) . random_int(100, 999);
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
        $now = time();
        $row = ErpAssetLedger::create([
            'site_id' => $this->site_id,
            'asset_id' => (int)($data['asset_id'] ?? 0),
            'action' => (string)($data['action'] ?? ''),
            'before_status' => (string)($data['before_status'] ?? ''),
            'after_status' => (string)($data['after_status'] ?? ''),
            'source_type' => (string)($data['source_type'] ?? ''),
            'source_id' => (int)($data['source_id'] ?? 0),
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'remark' => (string)($data['remark'] ?? ''),
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }
}
