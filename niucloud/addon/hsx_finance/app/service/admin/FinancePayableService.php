<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\service\admin;

use addon\hsx_finance\app\dict\FinanceDict;
use addon\hsx_finance\app\model\FinancePayable;
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
        if (!empty($where['keyword'])) {
            $query->where('counterparty_name', 'like', '%' . $where['keyword'] . '%');
        }
        $statusMap = FinanceDict::getStatusMap();
        $list = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page'      => (int)($where['page'] ?? 1),
        ]);
        $data = $list->toArray();
        foreach ($data['data'] as &$row) {
            $row['status_text']  = $statusMap[$row['status']] ?? $row['status'];
            $row['outstanding']  = round((float)$row['amount'] - (float)$row['settled_amount'], 2);
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
