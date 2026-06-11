<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\support\ErpMoney;
use core\base\BaseAdminService;
use core\exception\CommonException;

class ErpReconciliationService extends BaseAdminService
{
    public function checkAsset(int $assetId): array
    {
        $asset = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $assetId],
        ])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('ERP资产不存在');
        }

        $ledgerCost = ErpMoney::normalize(ErpCostLedger::where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
        ])->sum('amount_delta') ?: 0);
        $snapshotCost = ErpMoney::normalize($asset->current_cost);
        $lastStockLedger = ErpStockLedger::where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
        ])->order('occurred_at desc,id desc')->findOrEmpty();
        $ledgerStatus = $lastStockLedger->isEmpty() ? '' : (string)$lastStockLedger->after_status;
        $snapshotStatus = (string)$asset->inventory_status;

        $costMatched = ErpMoney::compare($ledgerCost, $snapshotCost) === 0;
        $statusMatched = $ledgerStatus === ''
            ? $snapshotStatus === 'pending_in' || $snapshotStatus === 'inbound_rejected'
            : $ledgerStatus === $snapshotStatus;

        return [
            'asset_id' => (int)$asset->id,
            'asset_no' => (string)$asset->asset_no,
            'imei' => (string)$asset->imei,
            'snapshot_cost' => $snapshotCost,
            'ledger_cost' => $ledgerCost,
            'cost_difference' => ErpMoney::subtract($snapshotCost, $ledgerCost),
            'snapshot_status' => $snapshotStatus,
            'ledger_status' => $ledgerStatus,
            'cost_matched' => $costMatched,
            'status_matched' => $statusMatched,
            'matched' => $costMatched && $statusMatched,
        ];
    }

    public function scan(array $where = []): array
    {
        $limit = max(1, min(500, (int)($where['limit'] ?? 100)));
        $query = ErpAsset::where([['site_id', '=', $this->site_id]])->order('id desc');
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('asset_no|imei|sn|model', '%' . $keyword . '%');
        }
        $assets = $query->limit($limit)->column('id');
        $rows = [];
        $differenceCount = 0;
        foreach ($assets as $assetId) {
            $row = $this->checkAsset((int)$assetId);
            if (!$row['matched']) {
                $differenceCount++;
            }
            if (empty($where['differences_only']) || !$row['matched']) {
                $rows[] = $row;
            }
        }
        return [
            'checked_count' => count($assets),
            'difference_count' => $differenceCount,
            'rows' => $rows,
        ];
    }
}
