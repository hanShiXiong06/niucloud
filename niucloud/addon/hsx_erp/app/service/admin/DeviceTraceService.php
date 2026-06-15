<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetMoveLog;
use addon\hsx_erp\app\model\ErpCapitalLedger;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\model\FinanceReceivable;
use addon\hsx_erp\app\model\FinanceSettlement;
use addon\hsx_erp\app\model\FinanceSettlementLink;
use app\model\sys\SysUser;
use core\base\BaseAdminService;

/**
 * 设备全链路追溯：以一次回收生命周期(回收设备 ↔ ERP资产)为单元，
 * 串起 回收 / 中台 / 财务 三段的状态、日志、操作人、金额。
 */
class DeviceTraceService extends BaseAdminService
{
    /** 搜索 → 生命周期列表(IMEI/SN/资产号/回收单号; 一个IMEI可能多次回收→多行) */
    public function searchList(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }
        // ERP 资产命中
        $assets = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where(function ($q) use ($keyword) {
                $q->whereLike('asset_no', '%' . $keyword . '%')
                    ->whereOr('imei', 'like', '%' . $keyword . '%')
                    ->whereOr('sn', 'like', '%' . $keyword . '%');
            })->order('id desc')->limit(50)->select()->toArray();
        // 回收命中(经事件)
        $recList = (array)($this->callRecycle(['mode' => 'search', 'keyword' => $keyword])['list'] ?? []);

        $items = [];
        foreach ($recList as $r) {
            $items['d' . (int)$r['device_id']] = [
                'device_id'     => (int)$r['device_id'],
                'asset_id'      => (int)($r['erp_asset_id'] ?? 0),
                'imei'          => (string)$r['imei'],
                'sn'            => (string)$r['sn'],
                'model'         => (string)$r['model'],
                'asset_no'      => '',
                'order_no'      => (string)$r['order_no'],
                'recycle_time'  => (int)$r['recycle_time'],
                'recycle_price' => round((float)$r['recycle_price'], 2),
                'sale_price'    => round((float)$r['sale_price'], 2),
                'customer_name' => (string)$r['customer_name'],
                'buyer_name'    => '',
                'stage'         => (int)$r['downstream_stage'],
                'inventory_status' => '',
            ];
        }
        $buyerMap = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, array_column($assets, 'counterparty_id'));
        foreach ($assets as $a) {
            $did = (int)$a['source_device_id'];
            $key = $did > 0 ? 'd' . $did : 'a' . (int)$a['id'];
            if (!isset($items[$key])) {
                $items[$key] = [
                    'device_id' => $did, 'asset_id' => 0, 'imei' => (string)$a['imei'], 'sn' => (string)$a['sn'],
                    'model' => (string)$a['model'], 'asset_no' => '', 'order_no' => '',
                    'recycle_time' => (int)$a['stock_in_at'], 'recycle_price' => round((float)$a['purchase_cost'], 2),
                    'sale_price' => 0, 'customer_name' => '', 'buyer_name' => '', 'stage' => 0, 'inventory_status' => '',
                ];
            }
            $items[$key]['asset_id'] = (int)$a['id'];
            $items[$key]['asset_no'] = (string)$a['asset_no'];
            $items[$key]['inventory_status'] = (string)$a['inventory_status'];
            if ((float)$items[$key]['sale_price'] <= 0) {
                $items[$key]['sale_price'] = round((float)$a['current_sale_price'], 2);
            }
            $bm = $buyerMap[(int)$a['counterparty_id']] ?? null;
            if ($bm && $items[$key]['buyer_name'] === '') {
                $items[$key]['buyer_name'] = $bm['entity_name'] ?: $bm['name'];
            }
        }
        $rows = array_values($items);
        foreach ($rows as &$r) {
            $r['status_text'] = $this->stageText((int)$r['stage'], (string)$r['inventory_status']);
        }
        unset($r);
        usort($rows, static fn($a, $b) => $b['recycle_time'] <=> $a['recycle_time']);
        return $rows;
    }

    /** 某生命周期详情：概览(含金额小计) + 时间线(关键节点+细节) */
    public function detail(int $assetId, int $deviceId): array
    {
        $asset = null;
        if ($assetId > 0) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->findOrEmpty();
        } elseif ($deviceId > 0) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['source_device_id', '=', $deviceId]])->order('id desc')->findOrEmpty();
        }
        $asset = ($asset && !$asset->isEmpty()) ? $asset->toArray() : null;
        if ($asset) {
            $assetId = (int)$asset['id'];
            if ($deviceId <= 0) {
                $deviceId = (int)$asset['source_device_id'];
            }
        }

        // 回收段
        $rec = $deviceId > 0 ? (array)$this->callRecycle(['mode' => 'trace', 'device_id' => $deviceId]) : [];
        $recSummary = (array)($rec['summary'] ?? []);
        $events = (array)($rec['events'] ?? []);

        // 中台段 + 财务段(基于资产)
        $cost = ['refurbish' => 0.0, 'other' => 0.0];
        if ($assetId > 0) {
            $this->collectErpEvents($assetId, $events, $cost);
            $this->collectFinanceEvents($assetId, $deviceId, $asset, $events);
        }

        // 排序 + 操作人补名
        $this->resolveOperators($events);
        usort($events, static fn($a, $b) => ((int)$a['time'] <=> (int)$b['time']));

        // 概览金额
        $buyer = '';
        if ($asset) {
            $bm = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, [(int)$asset['counterparty_id']])[(int)$asset['counterparty_id']] ?? null;
            $buyer = $bm ? ($bm['entity_name'] ?: $bm['name']) : '';
        }
        $recyclePrice = round((float)($recSummary['recycle_price'] ?? ($asset['purchase_cost'] ?? 0)), 2);
        $currentCost = round((float)($asset['current_cost'] ?? $recyclePrice), 2);
        $salePrice = round((float)($asset['current_sale_price'] ?? 0), 2);
        // 收款情况(应收)
        $recv = $this->receivableStat($assetId, $deviceId);
        $overview = [
            'imei'            => (string)($asset['imei'] ?? ''),
            'sn'              => (string)($asset['sn'] ?? ''),
            'model'           => (string)($asset['model'] ?? ''),
            'asset_no'        => (string)($asset['asset_no'] ?? ''),
            'order_no'        => (string)($recSummary['order_no'] ?? ''),
            'inventory_status'=> (string)($asset['inventory_status'] ?? ''),
            'customer_name'   => (string)($recSummary['customer_name'] ?? ''),   // 从谁收的
            'buyer_name'      => $buyer,                                          // 卖给了谁
            'recycle_price'   => $recyclePrice,
            'refurbish_cost'  => round($cost['refurbish'], 2),
            'other_cost'      => round($cost['other'], 2),
            'current_cost'    => $currentCost,
            'sale_price'      => $salePrice,
            'profit'          => round($salePrice - $currentCost, 2),
            'received'        => $recv['received'],
            'unreceived'      => $recv['unreceived'],
        ];
        return ['overview' => $overview, 'events' => array_values($events)];
    }

    /** 中台段: 调拨/移动日志 + 成本台账 */
    private function collectErpEvents(int $assetId, array &$events, array &$cost): void
    {
        $whName = [];
        foreach (ErpWarehouse::where([['site_id', '=', $this->site_id]])->field('id,warehouse_name')->select()->toArray() as $w) {
            $whName[(int)$w['id']] = (string)$w['warehouse_name'];
        }
        foreach (ErpAssetMoveLog::where([['site_id', '=', $this->site_id], ['asset_id', '=', $assetId]])->order('id asc')->select()->toArray() as $m) {
            $from = $whName[(int)$m['from_warehouse_id']] ?? '';
            $to = $whName[(int)$m['to_warehouse_id']] ?? '';
            $events[] = [
                'time' => (int)$m['create_at'], 'stage' => '中台', 'title' => '调拨/移库',
                'detail' => trim(($from !== '' ? $from : '—') . ' → ' . ($to !== '' ? $to : '—') . ' ' . (string)$m['remark']),
                'operator_name' => (string)$m['operator_name'], 'operator_uid' => (int)$m['operator_uid'],
                'amount' => 0, 'no' => '', 'key' => true,
            ];
        }
        foreach (ErpCostLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $assetId]])->order('id asc')->select()->toArray() as $c) {
            $delta = round((float)$c['amount_delta'], 2);
            $type = (string)$c['cost_type'];
            if (stripos($type, 'refurb') !== false || strpos($type, '整备') !== false) {
                $cost['refurbish'] += $delta;
            } elseif ($delta != 0.0 && stripos($type, 'purchase') === false && strpos($type, '回收') === false && strpos($type, 'inbound') === false) {
                $cost['other'] += $delta;
            }
            $events[] = [
                'time' => (int)$c['occurred_at'], 'stage' => '中台', 'title' => '成本变动',
                'detail' => $type . ' ' . ($delta >= 0 ? '+' : '') . $delta . ' ' . (string)$c['remark'],
                'operator_name' => (string)$c['operator_name'], 'operator_uid' => (int)$c['operator_id'],
                'amount' => $delta, 'no' => (string)$c['ledger_no'], 'key' => false,
            ];
        }
    }

    /** 财务段: 应付/应收生成 + 结算/折账 + 资金流水 */
    private function collectFinanceEvents(int $assetId, int $deviceId, ?array $asset, array &$events): void
    {
        $payables = $deviceId > 0 ? FinancePayable::where([['site_id', '=', $this->site_id], ['source_device_id', '=', $deviceId]])->select()->toArray() : [];
        $receivables = $deviceId > 0 ? FinanceReceivable::where([['site_id', '=', $this->site_id], ['source_device_id', '=', $deviceId]])->select()->toArray() : [];
        $finIds = ['payable' => [], 'receivable' => []];
        foreach ($payables as $p) {
            $finIds['payable'][] = (int)$p['id'];
            $events[] = [
                'time' => (int)$p['occurred_at'], 'stage' => '财务', 'title' => '生成应付(我欠)',
                'detail' => FinanceDict::sourceTypeText((string)$p['source_type']) . ' ¥' . round((float)$p['amount'], 2),
                'operator_name' => '', 'operator_uid' => 0, 'amount' => round((float)$p['amount'], 2),
                'no' => (string)$p['source_no'], 'key' => true,
            ];
        }
        foreach ($receivables as $r) {
            $finIds['receivable'][] = (int)$r['id'];
            $events[] = [
                'time' => (int)$r['occurred_at'], 'stage' => '财务', 'title' => '生成应收(欠我)',
                'detail' => FinanceDict::sourceTypeText((string)$r['source_type']) . ' ¥' . round((float)$r['amount'], 2),
                'operator_name' => '', 'operator_uid' => 0, 'amount' => round((float)$r['amount'], 2),
                'no' => (string)$r['source_no'], 'key' => true,
            ];
        }
        // 结算(通过核销关联)
        $settlementIds = [];
        foreach (['payable', 'receivable'] as $tt) {
            if (empty($finIds[$tt])) {
                continue;
            }
            $sids = FinanceSettlementLink::where([['site_id', '=', $this->site_id], ['target_type', '=', $tt]])
                ->whereIn('target_id', $finIds[$tt])->column('settlement_id');
            foreach ($sids as $sid) {
                $settlementIds[(int)$sid] = true;
            }
        }
        if (!empty($settlementIds)) {
            foreach (FinanceSettlement::where([['site_id', '=', $this->site_id]])->whereIn('id', array_keys($settlementIds))->select()->toArray() as $s) {
                $method = (string)$s['method'];
                $mtext = $method === 'offset' ? '折账' : ($method === 'mixed' ? '折账+现金' : '现金');
                $events[] = [
                    'time' => (int)$s['occurred_at'], 'stage' => '财务', 'title' => '结算/' . $mtext,
                    'detail' => '折账¥' . round((float)$s['offset_amount'], 2) . ' 现金¥' . round((float)$s['cash_amount'], 2) . ' 户头:' . (string)($s['account_name'] ?? ''),
                    'operator_name' => (string)$s['operator_name'], 'operator_uid' => (int)$s['operator_uid'],
                    'amount' => round((float)$s['cash_amount'], 2), 'no' => (string)$s['settlement_no'], 'key' => true,
                ];
            }
        }
        // 资金流水(按来源设备)
        if ($deviceId > 0) {
            foreach (ErpCapitalLedger::where([['site_id', '=', $this->site_id], ['source_id', '=', $deviceId]])->whereIn('source_type', ['recycle_order', 'recycle_device'])->select()->toArray() as $lg) {
                $events[] = [
                    'time' => (int)$lg['occurred_at'], 'stage' => '财务', 'title' => ((string)$lg['direction'] === 'in' ? '收款' : '付款'),
                    'detail' => (string)$lg['account_name'] . ' ¥' . round((float)$lg['amount'], 2) . ' ' . (string)$lg['remark'],
                    'operator_name' => (string)$lg['operator_name'], 'operator_uid' => (int)$lg['operator_uid'],
                    'amount' => round((float)$lg['amount'], 2), 'no' => (string)$lg['ledger_no'], 'key' => false,
                ];
            }
        }
    }

    private function receivableStat(int $assetId, int $deviceId): array
    {
        if ($deviceId <= 0) {
            return ['received' => 0, 'unreceived' => 0];
        }
        $rows = FinanceReceivable::where([['site_id', '=', $this->site_id], ['source_device_id', '=', $deviceId]])->field('amount,settled_amount')->select()->toArray();
        $received = 0.0;
        $unreceived = 0.0;
        foreach ($rows as $r) {
            $received += (float)$r['settled_amount'];
            $unreceived += ((float)$r['amount'] - (float)$r['settled_amount']);
        }
        return ['received' => round($received, 2), 'unreceived' => round(max(0, $unreceived), 2)];
    }

    /** 操作人补名: 有 name 用 name; 否则按 uid 查系统用户 */
    private function resolveOperators(array &$events): void
    {
        $uids = [];
        foreach ($events as $e) {
            if ((string)($e['operator_name'] ?? '') === '' && (int)($e['operator_uid'] ?? 0) > 0) {
                $uids[(int)$e['operator_uid']] = true;
            }
        }
        if (empty($uids)) {
            return;
        }
        $map = [];
        try {
            foreach (SysUser::whereIn('uid', array_keys($uids))->field('uid,username,real_name')->select()->toArray() as $u) {
                $map[(int)$u['uid']] = (string)($u['real_name'] ?: $u['username'] ?: '');
            }
        } catch (\Throwable $e) {
        }
        foreach ($events as &$e) {
            if ((string)($e['operator_name'] ?? '') === '') {
                $e['operator_name'] = $map[(int)($e['operator_uid'] ?? 0)] ?? '';
            }
        }
        unset($e);
    }

    /** 向回收插件取回收段(事件解耦; 回收未装则空) */
    private function callRecycle(array $payload): array
    {
        try {
            $results = (array)event('CollectRecycleDeviceTrace', array_merge(['site_id' => (int)$this->site_id], $payload));
            foreach ($results as $r) {
                if (is_array($r) && (isset($r['list']) || isset($r['events']) || isset($r['summary']))) {
                    return $r;
                }
            }
        } catch (\Throwable $e) {
        }
        return [];
    }

    private function stageText(int $stage, string $inventoryStatus): string
    {
        $map = [0 => '未流转', 10 => '已入库', 20 => '转中台', 30 => '已定价', 40 => '已售/下架'];
        if ($inventoryStatus === 'outbound' || $inventoryStatus === 'locked') {
            return '已售/下架';
        }
        return $map[$stage] ?? ($inventoryStatus !== '' ? $inventoryStatus : '未流转');
    }
}
