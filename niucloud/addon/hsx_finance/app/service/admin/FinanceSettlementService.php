<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\service\admin;

use addon\hsx_finance\app\dict\FinanceDict;
use addon\hsx_finance\app\model\FinancePayable;
use addon\hsx_finance\app\model\FinanceReceivable;
use addon\hsx_finance\app\model\FinanceSettlement;
use addon\hsx_finance\app\model\FinanceSettlementLink;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Event;
use think\facade\Log;

/**
 * 财务-结算服务(含折账核心)
 *
 * 折账(offset) = 同一往来单位的应收抵应付, 净额结算。
 * 例: 应付客户A 4000(回收), 应收A 5000(销售) → 折账4000, A 再净付我 1000。
 *
 * settle() 入参语义: 选定要一起结算的应付ID集合 + 应收ID集合(同一往来单位),
 * 系统自动算出: 折账金额 = min(应付合计, 应收合计); 余下一侧走现金。
 * 被选中的应付/应收均视为"本次全额结清"。
 */
class FinanceSettlementService extends BaseAdminService
{
    /** 预演: 只算不写, 供前端确认弹窗展示"折账多少、现金付/收多少" */
    public function preview(int $counterpartyId, array $payableIds, array $receivableIds): array
    {
        return $this->plan($counterpartyId, $payableIds, $receivableIds)['summary'];
    }

    /** 执行结算 */
    public function settle(int $counterpartyId, array $payableIds, array $receivableIds, array $options = []): array
    {
        $plan = $this->plan($counterpartyId, $payableIds, $receivableIds);
        $summary = $plan['summary'];
        if ($summary['payable_total'] <= 0 && $summary['receivable_total'] <= 0) {
            throw new CommonException('没有可结算的应付或应收');
        }

        $now = time();
        $no = 'JS' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $eventId = 'finance_settle_' . $no;
        $settlementId = 0;

        Db::transaction(function () use ($plan, $summary, $counterpartyId, $options, $now, $no, $eventId, &$settlementId) {
            $settlement = FinanceSettlement::create([
                'site_id'          => $this->site_id,
                'settlement_no'    => $no,
                'counterparty_id'  => $counterpartyId,
                'counterparty_name'=> $summary['counterparty_name'],
                'method'           => $summary['method'],
                'payable_total'    => $summary['payable_total'],
                'receivable_total' => $summary['receivable_total'],
                'offset_amount'    => $summary['offset_amount'],
                'cash_amount'      => $summary['cash_amount'],
                'cash_direction'   => $summary['cash_direction'],
                'status'           => 'completed',
                'operator_uid'     => (int)$this->uid,
                'operator_name'    => (string)$this->username,
                'event_id'         => $eventId,
                'occurred_at'      => $now,
                'remark'           => (string)($options['remark'] ?? ''),
                'create_time'      => $now,
                'update_time'      => $now,
            ]);
            $settlementId = (int)$settlement->id;

            // 写应付核销
            foreach ($plan['payable_alloc'] as $a) {
                $this->writeLink($settlementId, FinanceDict::TARGET_PAYABLE, $a, $now);
                $this->markSettled(new FinancePayable(), (int)$a['id'], $now);
            }
            // 写应收核销
            foreach ($plan['receivable_alloc'] as $a) {
                $this->writeLink($settlementId, FinanceDict::TARGET_RECEIVABLE, $a, $now);
                $this->markSettled(new FinanceReceivable(), (int)$a['id'], $now);
            }
        });

        // 发结算完成事件(故障隔离, 不回抛): 业务/ERP 订阅以更新各自展示
        $this->emitSettlementCompleted($settlementId, $counterpartyId, $summary, $plan, $eventId, $now);

        return ['settlement_id' => $settlementId, 'settlement_no' => $no, 'summary' => $summary];
    }

    /**
     * 计算结算方案(折账分配)
     * @return array{summary:array, payable_alloc:array, receivable_alloc:array}
     */
    private function plan(int $counterpartyId, array $payableIds, array $receivableIds): array
    {
        if ($counterpartyId <= 0) {
            throw new CommonException('请选择往来单位');
        }
        $payables    = $this->loadOutstanding(new FinancePayable(), $counterpartyId, $payableIds);
        $receivables = $this->loadOutstanding(new FinanceReceivable(), $counterpartyId, $receivableIds);

        $payableTotal    = round(array_sum(array_column($payables, 'outstanding')), 2);
        $receivableTotal = round(array_sum(array_column($receivables, 'outstanding')), 2);
        $offset          = round(min($payableTotal, $receivableTotal), 2);

        $netPayable    = round($payableTotal - $offset, 2);    // 折账后我仍需付出的现金
        $netReceivable = round($receivableTotal - $offset, 2); // 折账后对方仍需付我的现金

        // 现金方向(netPayable / netReceivable 至多一个>0)
        if ($netPayable > 0) {
            $cashAmount = $netPayable;
            $cashDir = FinanceDict::CASH_PAY;
        } elseif ($netReceivable > 0) {
            $cashAmount = $netReceivable;
            $cashDir = FinanceDict::CASH_COLLECT;
        } else {
            $cashAmount = 0.0;
            $cashDir = FinanceDict::CASH_NONE;
        }

        // 结算方式
        if ($offset > 0 && $cashAmount > 0) {
            $method = FinanceDict::METHOD_MIXED;
        } elseif ($offset > 0) {
            $method = FinanceDict::METHOD_OFFSET;
        } else {
            $method = FinanceDict::METHOD_CASH;
        }

        $cpName = $payables[0]['counterparty_name'] ?? ($receivables[0]['counterparty_name'] ?? '');

        return [
            'summary' => [
                'counterparty_id'   => $counterpartyId,
                'counterparty_name' => $cpName,
                'payable_total'     => $payableTotal,
                'receivable_total'  => $receivableTotal,
                'offset_amount'     => $offset,
                'cash_amount'       => $cashAmount,
                'cash_direction'    => $cashDir,
                'method'            => $method,
                'method_text'       => FinanceDict::getMethodMap()[$method] ?? $method,
            ],
            // 折账额按 FIFO(发生时间) 分摊到两侧, 余额即现金部分
            'payable_alloc'    => $this->allocate($payables, $offset),
            'receivable_alloc' => $this->allocate($receivables, $offset),
        ];
    }

    /** 把 offset 资金池按 FIFO 分摊到各条记录, 其余记为现金部分。被选记录均全额核销。 */
    private function allocate(array $rows, float $offsetPool): array
    {
        $alloc = [];
        $pool = $offsetPool;
        foreach ($rows as $r) {
            $out = (float)$r['outstanding'];
            $offsetPart = round(min($pool, $out), 2);
            $pool = round($pool - $offsetPart, 2);
            $cashPart = round($out - $offsetPart, 2);
            $alloc[] = [
                'id'          => (int)$r['id'],
                'applied'     => $out,
                'offset_part' => $offsetPart,
                'cash_part'   => $cashPart,
            ];
        }
        return $alloc;
    }

    private function loadOutstanding($model, int $counterpartyId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }
        $list = $model->where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['id', 'in', $ids],
            ['status', 'in', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL]],
        ])->order('occurred_at asc')->order('id asc')->select()->toArray();

        $rows = [];
        foreach ($list as $r) {
            $out = round((float)$r['amount'] - (float)$r['settled_amount'], 2);
            if ($out <= 0) {
                continue;
            }
            $rows[] = [
                'id'                => (int)$r['id'],
                'counterparty_name' => (string)$r['counterparty_name'],
                'outstanding'       => $out,
            ];
        }
        return $rows;
    }

    private function writeLink(int $settlementId, string $targetType, array $a, int $now): void
    {
        FinanceSettlementLink::create([
            'site_id'        => $this->site_id,
            'settlement_id'  => $settlementId,
            'target_type'    => $targetType,
            'target_id'      => (int)$a['id'],
            'applied_amount' => (float)$a['applied'],
            'pay_part'       => (float)$a['cash_part'],
            'offset_part'    => (float)$a['offset_part'],
            'create_time'    => $now,
        ]);
    }

    private function markSettled($model, int $id, int $now): void
    {
        $row = $model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) {
            return;
        }
        $model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->update([
            'settled_amount' => (float)$row->amount,
            'status'         => FinanceDict::STATUS_SETTLED,
            'update_time'    => $now,
        ]);
    }

    private function emitSettlementCompleted(int $settlementId, int $cpId, array $summary, array $plan, string $eventId, int $now): void
    {
        try {
            $linked = [];
            foreach ($plan['payable_alloc'] as $a) {
                $linked[] = ['type' => 'payable', 'id' => $a['id'], 'applied' => $a['applied'], 'offset' => $a['offset_part'], 'cash' => $a['cash_part']];
            }
            foreach ($plan['receivable_alloc'] as $a) {
                $linked[] = ['type' => 'receivable', 'id' => $a['id'], 'applied' => $a['applied'], 'offset' => $a['offset_part'], 'cash' => $a['cash_part']];
            }
            $payload = [
                'event'           => FinanceDict::EVENT_SETTLEMENT_DONE,
                'event_id'        => $eventId,
                'site_id'         => $this->site_id,
                'settlement_id'   => $settlementId,
                'counterparty_id' => $cpId,
                'method'          => $summary['method'],
                'offset_amount'   => $summary['offset_amount'],
                'cash_amount'     => $summary['cash_amount'],
                'cash_direction'  => $summary['cash_direction'],
                'linked'          => $linked,
                'operator'        => (string)$this->username,
                'occurred_at'     => $now,
            ];
            Event::trigger('FinanceSettlementCompleted', $payload);
        } catch (\Throwable $e) {
            Log::warning('[hsx_finance] 结算完成事件分发失败: ' . $e->getMessage());
        }
    }
}
