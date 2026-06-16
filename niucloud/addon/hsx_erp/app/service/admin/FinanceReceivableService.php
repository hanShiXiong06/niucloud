<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\FinanceReceivable;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 财务-应收列表
 */
class FinanceReceivableService extends BaseAdminService
{
    /** 给应收明细补设备信息(型号/IMEI) */
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
        $query = FinanceReceivable::where([['site_id', '=', $this->site_id]]);
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

    /** 某往来单位的待结算应收(供结算选择) */
    public function getOutstandingByCounterparty(int $counterpartyId): array
    {
        $list = FinanceReceivable::where([
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

    /**
     * 某供应商(member锚)的可用采购预付余额 + 明细。
     * 供入库建档时展示"可用预付¥X"并选择抵扣本台应付。
     */
    public function prepayBalance(int $memberId): array
    {
        if ($memberId <= 0) {
            return ['available' => 0.0, 'items' => []];
        }
        $list = FinanceReceivable::where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $memberId],
            ['source_type', '=', 'prepay'],
            ['status', 'in', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL]],
        ])->order('occurred_at asc')->select()->toArray();
        $available = 0.0;
        $items = [];
        foreach ($list as $r) {
            $out = round((float)$r['amount'] - (float)$r['settled_amount'], 2);
            if ($out <= 0) {
                continue;
            }
            $available += $out;
            $items[] = [
                'id'          => (int)$r['id'],
                'amount'      => round((float)$r['amount'], 2),
                'outstanding' => $out,
                'occurred_at' => (int)$r['occurred_at'],
                'remark'      => (string)$r['remark'],
            ];
        }
        return ['available' => round($available, 2), 'items' => $items];
    }

    /**
     * 采购预付挂账:钱付了、货还没到。
     *  1) 从资金账户现金出账(余额不足会抛);
     *  2) 给该往来单位生成一笔"采购预付"应收(=对方欠我货)→ 形成预付往来余额;
     *     货到手工建档生成应付后,用"一键结算/折账"自动与之相抵,无需再付现金。
     *
     * @param array $data counterparty_id(=会员锚) / amount / account_id / counterparty_name? / remark?
     */
    public function prepay(array $data): array
    {
        $counterpartyId = (int)($data['counterparty_id'] ?? 0);
        $amount = round((float)($data['amount'] ?? 0), 2);
        $accountId = (int)($data['account_id'] ?? 0);
        $remark = trim((string)($data['remark'] ?? ''));
        if ($counterpartyId <= 0) {
            throw new CommonException('请选择往来单位');
        }
        if ($amount <= 0) {
            throw new CommonException('预付金额必须大于0');
        }
        if ($accountId <= 0) {
            throw new CommonException('请选择付款资金账户');
        }

        $name = trim((string)($data['counterparty_name'] ?? ''));
        if ($name === '') {
            $map = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, [$counterpartyId]);
            $name = (string)($map[$counterpartyId]['name'] ?? ('往来#' . $counterpartyId));
        }

        // 1) 资金账户出账(余额不足直接抛)
        $ledgerId = (new ErpCapitalAccountService())->recordEntry([
            'account_id'        => $accountId,
            'direction'         => 'out',
            'amount'            => $amount,
            'biz_type'          => 'prepay',
            'counterparty_id'   => $counterpartyId,
            'counterparty_name' => $name,
            'source_type'       => 'prepay',
            'remark'            => $remark !== '' ? ('采购预付 ' . $remark) : '采购预付',
        ]);

        // 2) 生成"采购预付"应收 → 货到应付时折账相抵
        $eventId = 'erp-prepay-' . $this->site_id . '-' . date('YmdHis') . '-' . random_int(100000, 999999);
        event('FinanceReceivableCreated', [
            'site_id'           => $this->site_id,
            'event_id'          => $eventId,
            'amount'            => $amount,
            'counterparty_id'   => $counterpartyId,
            'counterparty_name' => $name,
            'source_type'       => 'prepay',
            'source_no'         => 'CAP#' . $ledgerId,
            'occurred_at'       => time(),
            'remark'            => $remark !== '' ? ('采购预付：' . $remark) : '采购预付(货到自动相抵应付)',
        ]);

        return [
            'counterparty_id'   => $counterpartyId,
            'counterparty_name' => $name,
            'amount'            => $amount,
            'capital_ledger_id' => $ledgerId,
        ];
    }
}
