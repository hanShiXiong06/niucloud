<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\FinancePayable;
use core\base\BaseAdminService;

/**
 * 财务-应付列表
 */
class FinancePayableService extends BaseAdminService
{
    /** 给应付/应收明细补设备信息(型号/IMEI), 让付款/收款时知道结的是哪台机 */
    protected function appendDeviceInfo(array &$rows): void
    {
        $devIds = array_values(array_unique(array_filter(array_map(static fn($r) => (int)($r['source_device_id'] ?? 0), $rows))));
        if (empty($devIds)) {
            return;
        }
        $map = [];
        foreach (ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('source_device_id', $devIds)
                     ->field('source_device_id,model,imei')->select()->toArray() as $a) {
            $map[(int)$a['source_device_id']] = $a;
        }
        foreach ($rows as &$r) {
            $a = $map[(int)($r['source_device_id'] ?? 0)] ?? null;
            $r['device_model'] = (string)($a['model'] ?? '');
            $r['device_imei'] = (string)($a['imei'] ?? '');
        }
        unset($r);
    }
    public function getPage(array $where = []): array
    {
        $query = FinancePayable::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['counterparty_id'])) {
            $query->where('counterparty_id', '=', (int)$where['counterparty_id']);
        }
        // 结清状态: settle_state 优先(open=未结清/settled=已结清), 否则用精确 status
        $settleState = (string)($where['settle_state'] ?? '');
        if ($settleState === 'open') {
            $query->whereIn('status', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL]);
        } elseif ($settleState === 'settled') {
            $query->where('status', '=', FinanceDict::STATUS_SETTLED);
        } elseif (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }
        if (!empty($where['source_type'])) {
            $query->where('source_type', '=', (string)$where['source_type']);
        }
        if (!empty($where['keyword'])) {
            $query->where('counterparty_name|source_no', 'like', '%' . trim((string)$where['keyword']) . '%');
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
        if (isset($where['amount_min']) && $where['amount_min'] !== '') {
            $query->where('amount', '>=', (float)$where['amount_min']);
        }
        if (isset($where['amount_max']) && $where['amount_max'] !== '') {
            $query->where('amount', '<=', (float)$where['amount_max']);
        }
        $statusMap = FinanceDict::getStatusMap();
        FinanceCounterpartyBalanceService::applySort($query, $where);
        $list = $query->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page'      => (int)($where['page'] ?? 1),
        ]);
        $data = $list->toArray();
        $memberMap = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, array_column($data['data'], 'counterparty_id'));
        foreach ($data['data'] as &$row) {
            $row['status_text']      = $statusMap[$row['status']] ?? $row['status'];
            $row['source_type_text'] = FinanceDict::sourceTypeText((string)($row['source_type'] ?? ''));
            $row['outstanding']      = round((float)$row['amount'] - (float)$row['settled_amount'], 2);
            $m = $memberMap[(int)($row['counterparty_id'] ?? 0)] ?? null;
            if ($m) {
                if ((string)($row['counterparty_name'] ?? '') === '') {
                    $row['counterparty_name'] = $m['name'];
                }
                $row['counterparty_mobile'] = $m['mobile'];
                $row['entity_id'] = $m['entity_id'];
                $row['entity_name'] = $m['entity_name'];
            }
            if ((string)($row['counterparty_name'] ?? '') === '') {
                $row['counterparty_name'] = '往来#' . ($row['counterparty_id'] ?? 0);
            }
        }
        unset($row);
        return $data;
    }

    /** 某往来单位的待结算应付(供结算选择) */
    public function getOutstandingByCounterparty(int $counterpartyId): array
    {
        $list = FinancePayable::where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['status', 'in', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL]],
        ])->order('occurred_at asc')->select()->toArray();
        $rows = [];
        foreach ($list as $r) {
            $out = round((float)$r['amount'] - (float)$r['settled_amount'], 2);
            if ($out <= 0) { continue; }
            $r['outstanding'] = $out;
            $rows[] = $r;
        }
        $this->appendDeviceInfo($rows);
        return $rows;
    }
}
