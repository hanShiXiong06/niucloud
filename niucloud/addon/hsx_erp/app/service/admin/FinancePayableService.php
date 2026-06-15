<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\FinancePayable;
use core\base\BaseAdminService;

/**
 * 财务-应付列表
 */
class FinancePayableService extends BaseAdminService
{
    public function getPage(array $where = []): array
    {
        $query = FinancePayable::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['counterparty_id'])) {
            $query->where('counterparty_id', '=', (int)$where['counterparty_id']);
        }
        if (!empty($where['status'])) {
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
        $list = $query->order('id desc')->paginate([
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
        return $rows;
    }
}
