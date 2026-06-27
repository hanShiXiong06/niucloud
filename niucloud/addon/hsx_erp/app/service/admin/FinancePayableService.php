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
    /** 应付明细的筛选项:实际出现过的业务类型 + 经手人(供前端下拉) */
    public function filterOptions(): array
    {
        $types = FinancePayable::where([['site_id', '=', $this->site_id]])
            ->where('source_type', '<>', '')->distinct(true)->column('source_type');
        $sourceTypes = [];
        foreach (array_values(array_unique($types)) as $t) {
            if ($t === '' || $t === null) continue;
            $sourceTypes[] = ['value' => (string)$t, 'text' => FinanceDict::sourceTypeText((string)$t)];
        }
        usort($sourceTypes, static fn($a, $b) => strcmp($a['text'], $b['text']));
        return [
            'source_types' => $sourceTypes,
            'operators'    => FinanceCounterpartyBalanceService::operatorOptions($this->site_id),
        ];
    }

    /** 给应付/应收明细补设备信息(型号/IMEI), 让付款/收款时知道结的是哪台机 */
    protected function appendDeviceInfo(array &$rows): void
    {
        $devIds = array_values(array_unique(array_filter(array_map(static fn($r) => (int)($r['source_device_id'] ?? 0), $rows))));
        if (empty($devIds)) {
            return;
        }
        $map = [];
        foreach (ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('source_device_id', $devIds)
                     ->field('source_device_id,model,imei,capacity,color')->select()->toArray() as $a) {
            $map[(int)$a['source_device_id']] = $a;
        }
        $identityMap = DeviceIdentityService::map($this->site_id, $devIds);
        foreach ($rows as &$r) {
            $did = (int)($r['source_device_id'] ?? 0);
            $a = $map[$did] ?? null;
            $r['device_model'] = (string)($a['model'] ?? '');
            $r['device_imei'] = (string)($a['imei'] ?? '');
            $r['device_capacity'] = (string)($a['capacity'] ?? '');
            $r['device_color'] = (string)($a['color'] ?? '');
            // 设备身份三件套(名称+小标题+串号);无回收质检时由 ERP 型号/容量/颜色/IMEI 回退
            DeviceIdentityService::attachToRow($r, $identityMap[$did] ?? null);
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
        if (!empty($where['operator'])) {
            $devIds = FinanceCounterpartyBalanceService::deviceIdsByOperator($this->site_id, (string)$where['operator']);
            if (empty($devIds)) {
                $query->where('id', '=', -1); // 该经手人无关联设备 → 置空
            } else {
                $query->whereIn('source_device_id', $devIds);
            }
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $cpIds = FinanceCounterpartyBalanceService::counterpartyIdsByKeyword($this->site_id, $kw);
            $query->where(function ($q) use ($kw, $cpIds) {
                $q->whereLike('counterparty_name', '%' . $kw . '%')->whereOr('source_no', 'like', '%' . $kw . '%');
                if (!empty($cpIds)) {
                    $q->whereOr('counterparty_id', 'in', $cpIds);
                }
            });
        }
        // IMEI 检索: 按设备串号反查回收/采购设备 → 限定 source_device_id
        if (!empty($where['imei'])) {
            $imei = trim((string)$where['imei']);
            $devIds = ErpAsset::where([['site_id', '=', $this->site_id]])
                ->whereLike('imei', '%' . $imei . '%')->column('source_device_id');
            $devIds = array_values(array_unique(array_filter(array_map('intval', $devIds))));
            if (empty($devIds)) {
                $query->where('id', '=', -1);
            } else {
                $query->whereIn('source_device_id', $devIds);
            }
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
        // 经手人（回收/采购应付 → 定价/入库人）
        if (!empty($data['data']) && is_array($data['data'])) {
            FinanceCounterpartyBalanceService::attachOperators($this->site_id, $data['data']);
        }
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
