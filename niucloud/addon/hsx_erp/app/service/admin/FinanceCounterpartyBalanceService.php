<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpCounterparty;
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
    /**
     * 按 counterparty_id(=会员member_id) 批量解析会员(对接人)及其所属主体(往来单位)，精确到人。
     * 返回 member_id => [
     *   'name'=>对接人(昵称/用户名), 'mobile'=>手机,
     *   'entity_id'=>所属主体ID(0=未归属), 'entity_name'=>主体名(空=未归属)
     * ]。会员表查不到的不返回。
     */
    public static function resolveMemberMap(int $siteId, array $counterpartyIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $counterpartyIds))));
        if (empty($ids)) {
            return [];
        }
        $map = [];
        try {
            $rows = \app\model\member\Member::where([['site_id', '=', $siteId]])
                ->whereIn('member_id', $ids)
                ->field('member_id,nickname,username,mobile')
                ->select()->toArray();
            foreach ($rows as $m) {
                $map[(int)$m['member_id']] = [
                    'name'        => (string)($m['nickname'] ?: $m['username'] ?: ''),
                    'mobile'      => (string)($m['mobile'] ?? ''),
                    'entity_id'   => 0,
                    'entity_name' => '',
                ];
            }
            // 关联主体(往来单位)：member → erp_counterparty_member → erp_counterparty
            $rels = \addon\hsx_erp\app\model\ErpCounterpartyMember::where([
                ['site_id', '=', $siteId], ['status', '=', 1],
            ])->whereIn('member_id', $ids)->field('member_id,counterparty_id')->select()->toArray();
            if (!empty($rels)) {
                $cpIds = array_values(array_unique(array_filter(array_column($rels, 'counterparty_id'))));
                $cpNameMap = [];
                if (!empty($cpIds)) {
                    // 主体名按 id 取(id 来自本站会员的关系, 已隐含站点); 不卡 site_id, 兼容历史 site_id=0 的主体
                    foreach (ErpCounterparty::whereIn('id', $cpIds)->field('id,name')->select()->toArray() as $cp) {
                        $cpNameMap[(int)$cp['id']] = (string)$cp['name'];
                    }
                }
                foreach ($rels as $r) {
                    $mid = (int)$r['member_id'];
                    if (isset($map[$mid])) {
                        $map[$mid]['entity_id'] = (int)$r['counterparty_id'];
                        $map[$mid]['entity_name'] = (string)($cpNameMap[(int)$r['counterparty_id']] ?? '');
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        return $map;
    }

    /**
     * 给应收/应付明细查询统一加排序。白名单字段, 支持未结额(amount-settled_amount)排序。
     * @param mixed $query ThinkORM 查询对象
     */
    public static function applySort($query, array $where): void
    {
        $field = (string)($where['sort_field'] ?? 'occurred_at');
        $order = strtolower((string)($where['sort_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allow = ['occurred_at', 'amount', 'settled_amount', 'id'];
        if ($field === 'outstanding') {
            $query->orderRaw('(amount - settled_amount) ' . $order)->order('id', 'desc');
            return;
        }
        if (!in_array($field, $allow, true)) {
            $field = 'occurred_at';
        }
        $query->order($field, $order)->order('id', 'desc');
    }

    /**
     * 财务汇总：应收/应付未结合计 + 净额 + 各资金账户余额(+总余额)。供财务中心顶部卡片。
     */
    public function getSummary(): array
    {
        $open = [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL];
        $pWhere = [['site_id', '=', $this->site_id], ['status', 'in', $open]];
        $payableTotal = round((float)FinancePayable::where($pWhere)->sum('amount') - (float)FinancePayable::where($pWhere)->sum('settled_amount'), 2);
        $receivableTotal = round((float)FinanceReceivable::where($pWhere)->sum('amount') - (float)FinanceReceivable::where($pWhere)->sum('settled_amount'), 2);

        $accounts = [];
        $balanceTotal = 0.0;
        try {
            foreach ((new ErpCapitalAccountService())->getAll() as $a) {
                if ((int)($a['status'] ?? 1) !== 1) {
                    continue;
                }
                $bal = round((float)($a['balance'] ?? 0), 2);
                $balanceTotal += $bal;
                $accounts[] = [
                    'id' => (int)$a['id'],
                    'account_name' => (string)($a['account_name'] ?? ''),
                    'account_type_text' => (string)($a['account_type_text'] ?? ''),
                    'balance' => $bal,
                ];
            }
        } catch (\Throwable $e) {
        }

        return [
            'payable_total'    => $payableTotal,        // 应付未结(我欠)
            'receivable_total' => $receivableTotal,     // 应收未结(欠我)
            'net'              => round($payableTotal - $receivableTotal, 2),
            'balance_total'    => round($balanceTotal, 2),
            'accounts'         => $accounts,
        ];
    }

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
            $row['payable'] = round((float)$row['payable'], 2);
            $row['receivable'] = round((float)$row['receivable'], 2);
            $rows[] = $row;
        }
        // 关联会员表，精确到人(名字+手机+所属主体)；名字为空时回填会员名，仍无则用 往来#ID
        $memberMap = self::resolveMemberMap($this->site_id, array_column($rows, 'counterparty_id'));
        foreach ($rows as &$row) {
            $m = $memberMap[(int)$row['counterparty_id']] ?? null;
            $row['counterparty_mobile'] = '';
            $row['entity_id'] = 0;
            $row['entity_name'] = '';
            if ($m) {
                if ((string)($row['counterparty_name'] ?? '') === '') {
                    $row['counterparty_name'] = $m['name'];
                }
                $row['counterparty_mobile'] = $m['mobile'];
                $row['entity_id'] = (int)$m['entity_id'];
                $row['entity_name'] = (string)$m['entity_name'];
            }
            if ((string)($row['counterparty_name'] ?? '') === '') {
                $row['counterparty_name'] = '往来#' . $row['counterparty_id'];
            }
        }
        unset($row);

        // 按主体聚合: 同一主体下多个对接人的应付/应收合并, 可跨人折账; 未归属主体的对接人各自成组。
        $groups = [];
        foreach ($rows as $r) {
            $eid = (int)$r['entity_id'];
            $child = [
                'row_key'             => 'c' . (int)$r['counterparty_id'],
                'is_entity'           => false,
                'is_child'            => true,
                'counterparty_id'     => (int)$r['counterparty_id'],
                'counterparty_name'   => (string)$r['counterparty_name'],
                'counterparty_mobile' => (string)$r['counterparty_mobile'],
                'entity_id'           => $eid,
                'entity_name'         => (string)$r['entity_name'],
                'payable'             => (float)$r['payable'],
                'receivable'          => (float)$r['receivable'],
                'offsetable'          => round(min((float)$r['payable'], (float)$r['receivable']), 2),
                'net'                 => round((float)$r['payable'] - (float)$r['receivable'], 2),
                'member_ids'          => [(int)$r['counterparty_id']],
            ];
            $child['net_direction'] = $child['net'] > 0 ? 'pay' : ($child['net'] < 0 ? 'collect' : 'none');
            if ($eid > 0) {
                $key = 'e' . $eid;
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'row_key'             => $key,
                        'is_entity'           => true,
                        'is_child'            => false,
                        'counterparty_id'     => 0,
                        'counterparty_name'   => (string)$r['entity_name'],
                        'counterparty_mobile' => '',
                        'entity_id'           => $eid,
                        'entity_name'         => (string)$r['entity_name'],
                        'payable'             => 0.0,
                        'receivable'          => 0.0,
                        'member_ids'          => [],
                        'children'            => [],
                    ];
                }
                $groups[$key]['payable'] = round($groups[$key]['payable'] + (float)$r['payable'], 2);
                $groups[$key]['receivable'] = round($groups[$key]['receivable'] + (float)$r['receivable'], 2);
                $groups[$key]['member_ids'][] = (int)$r['counterparty_id'];
                $groups[$key]['children'][] = $child;
            } else {
                // 未归属主体: 单人成组(无下级)
                $groups['m' . (int)$r['counterparty_id']] = $child;
            }
        }

        $result = [];
        foreach ($groups as $g) {
            $g['payable'] = round((float)$g['payable'], 2);
            $g['receivable'] = round((float)$g['receivable'], 2);
            $g['offsetable'] = round(min($g['payable'], $g['receivable']), 2);
            $g['net'] = round($g['payable'] - $g['receivable'], 2);
            $g['net_direction'] = $g['net'] > 0 ? 'pay' : ($g['net'] < 0 ? 'collect' : 'none');
            if (!empty($g['is_entity'])) {
                $g['member_count'] = count($g['member_ids']);
            }
            $result[] = $g;
        }
        // 可折账多的排前面, 方便优先处理
        usort($result, static fn($a, $b) => $b['offsetable'] <=> $a['offsetable']);
        return $result;
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

        $pWhere = [['site_id', '=', $siteId], ['counterparty_id', '=', $counterpartyId], ['status', 'in', $open]];
        $payable = (float)FinancePayable::where($pWhere)->sum('amount') - (float)FinancePayable::where($pWhere)->sum('settled_amount');
        $receivable = (float)FinanceReceivable::where($pWhere)->sum('amount') - (float)FinanceReceivable::where($pWhere)->sum('settled_amount');

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
