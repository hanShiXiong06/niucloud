<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\model\FinanceReceivable;
use core\base\BaseAdminService;

/**
 * 财务-往来单位余额(应付/应收/净额)
 *
 * 折账的入口视图: 一眼看出"我欠某客户多少、某客户欠我多少、可折账多少"。
 * 净额>0 表示我方仍需付现, 净额<0 表示对方仍需付我。
 */
class FinanceCounterpartyBalanceService extends BaseAdminService
{
    public function getBoard(): array
    {
        $open = [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL];

        $payables = FinancePayable::where([['site_id', '=', $this->site_id], ['status', 'in', $open]])
            ->field('counterparty_id, counterparty_name, sum(amount - settled_amount) as total')
            ->group('counterparty_id, counterparty_name')->select()->toArray();
        $receivables = FinanceReceivable::where([['site_id', '=', $this->site_id], ['status', 'in', $open]])
            ->field('counterparty_id, counterparty_name, sum(amount - settled_amount) as total')
            ->group('counterparty_id, counterparty_name')->select()->toArray();

        $map = [];
        foreach ($payables as $p) {
            $id = (int)$p['counterparty_id'];
            $map[$id] = $map[$id] ?? ['counterparty_id' => $id, 'counterparty_name' => $p['counterparty_name'], 'payable' => 0.0, 'receivable' => 0.0];
            $map[$id]['payable'] = round((float)$p['total'], 2);
        }
        foreach ($receivables as $r) {
            $id = (int)$r['counterparty_id'];
            $map[$id] = $map[$id] ?? ['counterparty_id' => $id, 'counterparty_name' => $r['counterparty_name'], 'payable' => 0.0, 'receivable' => 0.0];
            $map[$id]['receivable'] = round((float)$r['total'], 2);
        }

        $rows = [];
        foreach ($map as $row) {
            $row['offsetable'] = round(min($row['payable'], $row['receivable']), 2); // 可折账
            $row['net'] = round($row['payable'] - $row['receivable'], 2);            // 净额(>0我付/<0我收)
            $row['net_direction'] = $row['net'] > 0 ? 'pay' : ($row['net'] < 0 ? 'collect' : 'none');
            $rows[] = $row;
        }
        // 可折账多的排前面, 方便优先处理
        usort($rows, static fn($a, $b) => $b['offsetable'] <=> $a['offsetable']);
        return $rows;
    }

    /**
     * 查单个往来单位的往来账(给回收"打款即折账"用)
     *
     * 站在我方视角:
     *   payable    我欠对方(来自回收)
     *   receivable 对方欠我(来自销售/商城)
     *   net        payable - receivable: >0 我还需净付, <0 对方还需净付我, =0 已平
     *   offsetable min(payable, receivable): 可折账(折让)金额, >0 即可折
     * @param int $counterpartyId 往来单位ID
     * @param int|null $siteId 站点ID(事件上下文显式传入; 为空则取当前请求站点)
     */
    public function getCounterpartyBalance(int $counterpartyId, ?int $siteId = null): array
    {
        $siteId = $siteId ?? (int)$this->site_id;
        $open = [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL];

        $payable = (float)FinancePayable::where([
            ['site_id', '=', $siteId], ['counterparty_id', '=', $counterpartyId], ['status', 'in', $open],
        ])->sum('amount - settled_amount');
        $receivable = (float)FinanceReceivable::where([
            ['site_id', '=', $siteId], ['counterparty_id', '=', $counterpartyId], ['status', 'in', $open],
        ])->sum('amount - settled_amount');

        $payable = round($payable, 2);
        $receivable = round($receivable, 2);
        $offsetable = round(min($payable, $receivable), 2);
        $net = round($payable - $receivable, 2);

        return [
            'counterparty_id' => $counterpartyId,
            'payable'         => $payable,
            'receivable'      => $receivable,
            'offsetable'      => $offsetable,
            'can_offset'      => $offsetable > 0,
            'net'             => $net,
            'net_direction'   => $net > 0 ? 'pay' : ($net < 0 ? 'collect' : 'none'),
        ];
    }
}
