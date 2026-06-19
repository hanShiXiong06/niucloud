<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpOutboundOrder;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\model\FinanceReceivable;
use addon\hsx_erp\app\model\FinanceSettlement;
use addon\hsx_erp\app\model\FinanceSettlementLink;
use core\base\BaseAdminService;

/**
 * 按设备的账目对账（导出用）。
 *
 * 一台机器一行：从回收(应付)到销售(应收)的完整链路——单号/金额/时间/各环节操作人，
 * 结算方式(现金/折账)、折账金额与折账原因(与哪笔对冲)、已收/未收、已付/未付。
 * 站在对账人角度：一行看清一台机器的钱怎么来怎么走、谁经手、为什么折账。
 */
class FinanceReconciliationService extends BaseAdminService
{
    private const MAX_ROWS = 2000;

    /**
     * @param array $where counterparty_id, settle_method(''/cash/offset), start_time, end_time, keyword
     * @return array{rows:array, total:int, truncated:bool}
     */
    public function deviceRows(array $where = []): array
    {
        $cpId = (int)($where['counterparty_id'] ?? 0);
        $method = (string)($where['settle_method'] ?? '');
        $payState = (string)($where['pay_state'] ?? '');   // ''/settled/unsettled — 打款(应付)结清状态
        $recvState = (string)($where['recv_state'] ?? ''); // ''/settled/unsettled — 收款(应收)结清状态
        $start = (int)($where['start_time'] ?? 0);
        $end = (int)($where['end_time'] ?? 0);
        $keyword = trim((string)($where['keyword'] ?? ''));

        // 1) 候选设备：来自应付(回收)与应收(销售)
        $payQ = FinancePayable::where([['site_id', '=', $this->site_id]]);
        $rcvQ = FinanceReceivable::where([['site_id', '=', $this->site_id]]);
        if ($cpId > 0) {
            $payQ->where('counterparty_id', '=', $cpId);
            $rcvQ->where('counterparty_id', '=', $cpId);
        }
        if ($start > 0) {
            $payQ->where('occurred_at', '>=', $start);
            $rcvQ->where('occurred_at', '>=', $start);
        }
        if ($end > 0) {
            $payQ->where('occurred_at', '<=', $end);
            $rcvQ->where('occurred_at', '<=', $end);
        }
        $payables = $payQ->select()->toArray();
        $receivables = $rcvQ->select()->toArray();

        $payByDev = [];
        $rcvByDev = [];
        foreach ($payables as $p) {
            $payByDev[(int)$p['source_device_id']] = $p;
        }
        foreach ($receivables as $r) {
            $rcvByDev[(int)$r['source_device_id']] = $r;
        }
        $deviceIds = array_values(array_unique(array_filter(array_merge(array_keys($payByDev), array_keys($rcvByDev)))));
        if (empty($deviceIds)) {
            return ['rows' => [], 'total' => 0, 'truncated' => false];
        }

        // 2) 资产 + 结算 批量
        $assets = [];
        foreach (ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('source_device_id', $deviceIds)
            ->field('source_device_id, asset_no, model, imei, sn, current_cost, current_sale_price, inventory_status')
            ->select()->toArray() as $a) {
            $assets[(int)$a['source_device_id']] = $a;
        }

        // 应付/应收 id → 设备
        $payIds = array_column($payables, 'id');
        $rcvIds = array_column($receivables, 'id');
        $settleByTarget = $this->loadSettlements($payIds, $rcvIds);

        // 往来单位名兜底：按 counterparty_id(对接人 member) 反解
        $cpIds = array_values(array_unique(array_filter(array_merge(
            array_map(static fn($x) => (int)$x['counterparty_id'], $payables),
            array_map(static fn($x) => (int)$x['counterparty_id'], $receivables)
        ))));
        $memberMap = !empty($cpIds) ? FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, $cpIds) : [];
        // 销售人兜底：按销售单号(出库单)取 operator_name(历史数据出库单本身存了)
        $saleNos = array_values(array_unique(array_filter(array_map(static fn($x) => (string)$x['source_no'], $receivables))));
        $saleOpByNo = [];
        if (!empty($saleNos)) {
            foreach (ErpOutboundOrder::where([['site_id', '=', $this->site_id]])->whereIn('outbound_no', $saleNos)
                ->field('outbound_no, operator_name, counterparty_name')->select()->toArray() as $o) {
                $saleOpByNo[(string)$o['outbound_no']] = $o;
            }
        }

        // 3) 组装
        $rows = [];
        $truncated = false;
        foreach ($deviceIds as $did) {
            if (count($rows) >= self::MAX_ROWS) {
                $truncated = true;
                break;
            }
            $p = $payByDev[$did] ?? null;
            $r = $rcvByDev[$did] ?? null;
            $asset = $assets[$did] ?? [];

            $payInfo = $p ? ($settleByTarget['payable'][(int)$p['id']] ?? null) : null;
            $rcvInfo = $r ? ($settleByTarget['receivable'][(int)$r['id']] ?? null) : null;

            // 结算方式：任一侧参与折账即为折账，否则现金；都无结算为未结
            $isOffset = ($payInfo['offset'] ?? 0) > 0 || ($rcvInfo['offset'] ?? 0) > 0;
            $hasCash = ($payInfo['cash'] ?? 0) > 0 || ($rcvInfo['cash'] ?? 0) > 0;
            $settleMethod = $isOffset ? 'offset' : ($hasCash ? 'cash' : '');
            $settleMethodText = $isOffset ? '折账' : ($hasCash ? '现金' : '未结');

            if ($method === 'cash' && $settleMethod !== 'cash') {
                continue;
            }
            if ($method === 'offset' && $settleMethod !== 'offset') {
                continue;
            }
            // 打款(应付)结清状态筛选
            if ($payState !== '') {
                if (!$p) {
                    continue;
                }
                $po = round((float)$p['amount'] - (float)$p['settled_amount'], 2);
                if ($payState === 'settled' && $po > 0.001) {
                    continue;
                }
                if ($payState === 'unsettled' && $po <= 0.001) {
                    continue;
                }
            }
            // 收款(应收)结清状态筛选
            if ($recvState !== '') {
                if (!$r) {
                    continue;
                }
                $ro = round((float)$r['amount'] - (float)$r['settled_amount'], 2);
                if ($recvState === 'settled' && $ro > 0.001) {
                    continue;
                }
                if ($recvState === 'unsettled' && $ro <= 0.001) {
                    continue;
                }
            }
            if ($keyword !== '') {
                $hay = (string)($asset['model'] ?? '') . ($asset['imei'] ?? '') . ($asset['sn'] ?? '') . ($asset['asset_no'] ?? '')
                    . ($p['source_no'] ?? '') . ($r['source_no'] ?? '');
                if (mb_stripos($hay, $keyword) === false) {
                    continue;
                }
            }

            $recyclePrice = $p ? round((float)$p['amount'], 2) : 0.0;
            $cost = round((float)($asset['current_cost'] ?? 0), 2);
            $salePrice = $r ? round((float)$r['amount'], 2) : round((float)($asset['current_sale_price'] ?? 0), 2);
            $profit = round($salePrice - $cost, 2);

            // 往来单位名兜底
            $cpName = (string)($p['counterparty_name'] ?? '');
            if ($cpName === '') {
                $cpName = (string)($r['counterparty_name'] ?? '');
            }
            if ($cpName === '') {
                $cid = (int)($p['counterparty_id'] ?? $r['counterparty_id'] ?? 0);
                $cpName = (string)($memberMap[$cid]['name'] ?? '');
            }
            // 销售人兜底: ext_json → 出库单 operator
            $saleOp = $r ? $this->extOp($r['ext_json'] ?? '') : '';
            if ($saleOp === '' && $r) {
                $saleOp = (string)($saleOpByNo[(string)$r['source_no']]['operator_name'] ?? '');
            }

            $rows[] = [
                'device_id'        => $did,
                'model'            => (string)($asset['model'] ?? ''),
                'imei'             => (string)($asset['imei'] ?? ''),
                'asset_no'         => (string)($asset['asset_no'] ?? ''),
                // 回收(应付)
                'recycle_no'       => (string)($p['source_no'] ?? ''),
                'recycle_price'    => $recyclePrice,
                'recycle_at'       => $p ? $this->fmt((int)$p['occurred_at']) : '',
                'recycle_operator' => $p ? $this->extOp($p['ext_json'] ?? '') : '',
                'counterparty'     => $cpName,
                'pay_status'       => $p ? $this->settleText((float)$p['amount'], (float)$p['settled_amount']) : '',
                'paid'             => $p ? round((float)$p['settled_amount'], 2) : 0.0,
                'unpaid'           => $p ? round((float)$p['amount'] - (float)$p['settled_amount'], 2) : 0.0,
                'pay_operator'     => $payInfo['operator'] ?? '',   // 打款人
                'pay_at'           => $payInfo['at'] ?? '',
                'pay_account'      => $payInfo['account'] ?? '',
                // 成本 / 销售(应收)
                'cost'             => $cost,
                'sale_no'          => (string)($r['source_no'] ?? ''),
                'sale_price'       => $salePrice,
                'sale_at'          => $r ? $this->fmt((int)$r['occurred_at']) : '',
                'sale_operator'    => $saleOp,  // 销售/出库人(ext_json → 出库单兜底)
                'profit'           => $profit,
                'recv_status'      => $r ? $this->settleText((float)$r['amount'], (float)$r['settled_amount']) : '',
                'received'         => $r ? round((float)$r['settled_amount'], 2) : 0.0,
                'unreceived'       => $r ? round((float)$r['amount'] - (float)$r['settled_amount'], 2) : 0.0,
                'collect_operator' => $rcvInfo['operator'] ?? '',  // 收款人
                'collect_at'       => $rcvInfo['at'] ?? '',
                'collect_account'  => $rcvInfo['account'] ?? '',
                // 结算 / 折账
                'settle_method'    => $settleMethod,
                'settle_method_text' => $settleMethodText,
                'offset_amount'    => round((float)max($payInfo['offset'] ?? 0, $rcvInfo['offset'] ?? 0), 2),
                'offset_reason'    => $this->offsetReason($payInfo, $rcvInfo),
                'status_text'      => $this->statusText((string)($asset['inventory_status'] ?? '')),
            ];
        }

        return ['rows' => $rows, 'total' => count($rows), 'truncated' => $truncated];
    }

    /** 应付/应收 id → 它所在结算(方式/折账/现金/操作人/户头/时间/对冲对象) */
    private function loadSettlements(array $payIds, array $rcvIds): array
    {
        $out = ['payable' => [], 'receivable' => []];
        $payIds = array_values(array_filter(array_map('intval', $payIds)));
        $rcvIds = array_values(array_filter(array_map('intval', $rcvIds)));

        $links = [];
        if (!empty($payIds)) {
            foreach (FinanceSettlementLink::where([['site_id', '=', $this->site_id], ['target_type', '=', 'payable']])
                ->whereIn('target_id', $payIds)->select()->toArray() as $l) {
                $links[] = $l;
            }
        }
        if (!empty($rcvIds)) {
            foreach (FinanceSettlementLink::where([['site_id', '=', $this->site_id], ['target_type', '=', 'receivable']])
                ->whereIn('target_id', $rcvIds)->select()->toArray() as $l) {
                $links[] = $l;
            }
        }
        if (empty($links)) {
            return $out;
        }
        $sids = array_values(array_unique(array_map(static fn($l) => (int)$l['settlement_id'], $links)));
        $settlements = [];
        foreach (FinanceSettlement::where([['site_id', '=', $this->site_id]])->whereIn('id', $sids)->select()->toArray() as $s) {
            $settlements[(int)$s['id']] = $s;
        }
        // 每个结算单内的全部明细(用于折账原因：对冲了哪些单)
        $linksBySettle = [];
        foreach (FinanceSettlementLink::where([['site_id', '=', $this->site_id]])->whereIn('settlement_id', $sids)->select()->toArray() as $l) {
            $linksBySettle[(int)$l['settlement_id']][] = $l;
        }
        // 来源单号映射(让折账原因带可读单号)
        $allTargets = ['payable' => [], 'receivable' => []];
        foreach ($linksBySettle as $ls) {
            foreach ($ls as $l) {
                $allTargets[(string)$l['target_type']][] = (int)$l['target_id'];
            }
        }
        $srcNo = ['payable' => [], 'receivable' => []];
        if (!empty($allTargets['payable'])) {
            foreach (FinancePayable::where([['site_id', '=', $this->site_id]])->whereIn('id', array_unique($allTargets['payable']))->field('id, source_no, amount')->select()->toArray() as $x) {
                $srcNo['payable'][(int)$x['id']] = $x;
            }
        }
        if (!empty($allTargets['receivable'])) {
            foreach (FinanceReceivable::where([['site_id', '=', $this->site_id]])->whereIn('id', array_unique($allTargets['receivable']))->field('id, source_no, amount')->select()->toArray() as $x) {
                $srcNo['receivable'][(int)$x['id']] = $x;
            }
        }

        foreach ($links as $l) {
            $sid = (int)$l['settlement_id'];
            $s = $settlements[$sid] ?? null;
            if (!$s) {
                continue;
            }
            // 对冲对象：同结算单里"另一侧"的单据
            $myType = (string)$l['target_type'];
            $otherType = $myType === 'payable' ? 'receivable' : 'payable';
            $counterDocs = [];
            foreach (($linksBySettle[$sid] ?? []) as $ll) {
                if ((string)$ll['target_type'] === $otherType) {
                    $doc = $srcNo[$otherType][(int)$ll['target_id']] ?? null;
                    if ($doc) {
                        $counterDocs[] = ($otherType === 'payable' ? '应付' : '应收') . (string)$doc['source_no'] . '¥' . round((float)$doc['amount'], 2);
                    }
                }
            }
            $info = [
                'method'   => (string)$s['method'],
                'offset'   => round((float)$s['offset_amount'], 2),
                'cash'     => round((float)$s['cash_amount'], 2),
                'operator' => (string)$s['operator_name'],
                'at'       => $this->fmt((int)$s['occurred_at']),
                'account'  => (string)($s['account_name'] ?? ''),
                'settle_no' => (string)$s['settlement_no'],
                'counter_docs' => $counterDocs,
            ];
            $out[$myType][(int)$l['target_id']] = $info;
        }
        return $out;
    }

    private function offsetReason(?array $payInfo, ?array $rcvInfo): string
    {
        $info = ($payInfo['offset'] ?? 0) > 0 ? $payInfo : (($rcvInfo['offset'] ?? 0) > 0 ? $rcvInfo : null);
        if (!$info) {
            return '';
        }
        $docs = $info['counter_docs'] ?? [];
        $with = !empty($docs) ? ('与 ' . implode('、', $docs) . ' 对冲') : '与同主体应收应付对冲';
        return sprintf('折账¥%.2f（%s，结算单%s）', (float)$info['offset'], $with, (string)$info['settle_no']);
    }

    private function settleText(float $amount, float $settled): string
    {
        $out = round($amount - $settled, 2);
        if ($out <= 0.001) {
            return '已结清';
        }
        return $settled > 0 ? '部分' : '未结';
    }

    private function extOp($extJson): string
    {
        $ext = json_decode((string)$extJson, true);
        return is_array($ext) ? (string)($ext['operator_name'] ?? '') : '';
    }

    private function fmt(int $t): string
    {
        return $t > 946684800 ? date('Y-m-d H:i', $t) : '';
    }

    private function statusText(string $s): string
    {
        return [
            'in_stock' => '在库', 'refurbishing' => '整备中', 'pending_pricing' => '待定价', 'available_for_sale' => '可售',
            'locked' => '销售锁定', 'outbound' => '已售/下架', 'pending_in' => '待入库',
        ][$s] ?? $s;
    }
}
