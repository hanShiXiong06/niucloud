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
        // 每个对接人名下未结清的逐笔账目（用于「往来汇总」就地展开，看清这钱对应哪台机器）
        $itemsByCp = $this->openItemsByCounterparty(array_column($rows, 'counterparty_id'));

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
                'children'            => $itemsByCp[(int)$r['counterparty_id']] ?? [],
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
     * 取每个对接人名下「未结清」的逐笔账目（挂到往来汇总下做明细展开）
     * 每笔尽量带出对应设备(货)型号，让人一眼明白这钱是为哪台机器。
     * @param int[] $memberIds
     * @return array<int, array> counterparty_id => items[]
     */
    private function openItemsByCounterparty(array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        if (empty($memberIds)) {
            return [];
        }
        $open = [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL];
        $fields = 'id,counterparty_id,amount,settled_amount,source_type,source_no,source_device_id,remark,occurred_at';
        $pay = FinancePayable::where([['site_id', '=', $this->site_id], ['status', 'in', $open]])
            ->whereIn('counterparty_id', $memberIds)->field($fields)->order('occurred_at desc')->select()->toArray();
        $rec = FinanceReceivable::where([['site_id', '=', $this->site_id], ['status', 'in', $open]])
            ->whereIn('counterparty_id', $memberIds)->field($fields)->order('occurred_at desc')->select()->toArray();

        // 设备（型号 + 资产id，用于找操作人）
        $deviceIds = array_values(array_unique(array_filter(array_merge(
            array_column($pay, 'source_device_id'),
            array_column($rec, 'source_device_id')
        ))));
        $deviceMap = [];
        if (!empty($deviceIds)) {
            foreach (\addon\hsx_erp\app\model\ErpAsset::where([['site_id', '=', $this->site_id]])
                         ->whereIn('source_device_id', $deviceIds)
                         ->field('id,source_device_id,model,imei')->select()->toArray() as $a) {
                $deviceMap[(int)$a['source_device_id']] = $a;
            }
        }

        // 操作事件（按资产聚合，用于解析"谁操作/卖出"）
        $assetIds = array_values(array_unique(array_filter(array_map(fn($d) => (int)($d['id'] ?? 0), $deviceMap))));
        $eventsByAsset = [];
        $needUids = [];
        if (!empty($assetIds)) {
            foreach (\addon\hsx_erp\app\model\ErpOperationEvent::where([['site_id', '=', $this->site_id]])
                         ->whereIn('asset_id', $assetIds)
                         ->field('asset_id,operator_id,operator_name,occurred_at')
                         ->order('occurred_at desc')->select()->toArray() as $e) {
                $eventsByAsset[(int)$e['asset_id']][] = $e;
                if ((string)$e['operator_name'] === '' && (int)$e['operator_id'] > 0) {
                    $needUids[(int)$e['operator_id']] = true;
                }
            }
        }
        // operator_name 为空时按 uid 回填
        $uidNameMap = [];
        if (!empty($needUids)) {
            try {
                foreach (\app\model\sys\SysUser::whereIn('uid', array_keys($needUids))
                             ->field('uid,username,real_name')->select()->toArray() as $u) {
                    $uidNameMap[(int)$u['uid']] = (string)($u['real_name'] ?: $u['username'] ?: '');
                }
            } catch (\Throwable $e) {
            }
        }

        // 取离该笔账发生时间最近的那条操作事件的操作人
        $resolveOperator = function (int $deviceId, int $ts) use ($deviceMap, $eventsByAsset, $uidNameMap): string {
            $assetId = (int)($deviceMap[$deviceId]['id'] ?? 0);
            if ($assetId <= 0 || empty($eventsByAsset[$assetId])) {
                return '';
            }
            $best = '';
            $bestDiff = PHP_INT_MAX;
            foreach ($eventsByAsset[$assetId] as $e) {
                $name = (string)$e['operator_name'] !== '' ? (string)$e['operator_name'] : ($uidNameMap[(int)$e['operator_id']] ?? '');
                if ($name === '') {
                    continue;
                }
                $diff = abs((int)$e['occurred_at'] - $ts);
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $best = $name;
                }
            }
            return $best;
        };

        $byCp = [];
        $append = function (array $rows, string $type, string $label) use (&$byCp, $deviceMap, $resolveOperator) {
            foreach ($rows as $r) {
                $out = round((float)$r['amount'] - (float)$r['settled_amount'], 2);
                if ($out <= 0) {
                    continue;
                }
                $cid = (int)$r['counterparty_id'];
                $did = (int)$r['source_device_id'];
                $dev = $deviceMap[$did] ?? null;
                $sourceText = FinanceDict::sourceTypeText((string)$r['source_type']);
                $title = ($dev && (string)$dev['model'] !== '') ? (string)$dev['model'] : ($sourceText ?: '账目');
                $byCp[$cid][] = [
                    'row_key'          => 'it' . $type . (int)$r['id'],
                    'is_entity'        => false,
                    'is_child'         => false,
                    'is_item'          => true,
                    'direction'        => $label,
                    'title'            => $title,
                    'device_id'        => $did,
                    'imei'             => (string)($dev['imei'] ?? ''),
                    'operator'         => $resolveOperator($did, (int)$r['occurred_at']),
                    'source_type_text' => $sourceText,
                    'source_no'        => (string)$r['source_no'],
                    'remark'           => (string)$r['remark'],
                    'occurred_at'      => (int)$r['occurred_at'],
                    'payable'          => $type === 'pay' ? $out : 0.0,
                    'receivable'       => $type === 'rec' ? $out : 0.0,
                    'offsetable'       => 0.0,
                    'net'              => 0.0,
                    'net_direction'    => 'none',
                ];
            }
        };
        $append($pay, 'pay', '应付');
        $append($rec, 'rec', '应收');
        return $byCp;
    }

    /**
     * 按关键词解析出一批往来 counterparty_id（=对接人 member_id）。
     * 关键词模糊匹配：会员(username/nickname/手机/编号) + 主体(名称/手机/联系人/编号)下的对接人。
     * 用于「应收/应付明细、结算记录」按"主体名/用户名/昵称/手机号"检索（财务记录只存 counterparty_id，需先反解析）。
     * @return int[] member_id 列表
     */
    public static function counterpartyIdsByKeyword(int $siteId, string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }
        $memberIds = [];
        // 1) 直接命中会员
        try {
            $memberIds = array_map('intval', \app\model\member\Member::where([['site_id', '=', $siteId]])
                ->whereLike('username|nickname|mobile|member_no', '%' . $keyword . '%')
                ->limit(1000)->column('member_id'));
        } catch (\Throwable $e) {
        }
        // 2) 命中主体 → 取其下对接人
        try {
            $entityIds = array_values(array_filter(array_map('intval', ErpCounterparty::where([['site_id', '=', $siteId]])
                ->whereLike('name|mobile|contact_name|counterparty_no', '%' . $keyword . '%')
                ->limit(1000)->column('id'))));
            if (!empty($entityIds)) {
                $more = array_map('intval', \addon\hsx_erp\app\model\ErpCounterpartyMember::where([
                    ['site_id', '=', $siteId], ['status', '=', 1],
                ])->whereIn('counterparty_id', $entityIds)->column('member_id'));
                $memberIds = array_merge($memberIds, $more);
            }
        } catch (\Throwable $e) {
        }
        return array_values(array_unique(array_filter($memberIds)));
    }

    /**
     * 按经手人反查"其操作过的设备" source_device_id 列表（用于明细按经手人筛选，DB 层过滤）。
     * 经手人匹配 operation_event.operator_name，或按 SysUser 姓名/账号找到 uid 再匹配 operator_id。
     * @return int[] source_device_id 列表；为空表示无匹配（调用方应据此置空结果）
     */
    public static function deviceIdsByOperator(int $siteId, string $operator): array
    {
        $operator = trim($operator);
        if ($operator === '') {
            return [];
        }
        $uids = [];
        try {
            $uids = array_values(array_filter(array_map('intval',
                \app\model\sys\SysUser::whereLike('real_name|username', '%' . $operator . '%')->column('uid')
            )));
        } catch (\Throwable $e) {
        }

        $query = \addon\hsx_erp\app\model\ErpOperationEvent::where([['site_id', '=', $siteId]]);
        if (!empty($uids)) {
            $query->where(function ($w) use ($operator, $uids) {
                $w->whereLike('operator_name', '%' . $operator . '%')->whereOr('operator_id', 'in', $uids);
            });
        } else {
            $query->whereLike('operator_name', '%' . $operator . '%');
        }
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $query->column('asset_id')))));
        if (empty($assetIds)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval',
            \addon\hsx_erp\app\model\ErpAsset::where([['site_id', '=', $siteId]])
                ->whereIn('id', $assetIds)->column('source_device_id')
        ))));
    }

    /**
     * 给一批财务行批量补「经手人」(operator)。
     * 口径：同设备、发生时间最接近的那条操作事件 —— 回收/采购≈定价人，销售≈销售员。
     * 行需含 source_device_id 与 occurred_at；就地写入 $row['operator']。
     * @param array $rows 引用传入（如分页 data）
     */
    public static function attachOperators(int $siteId, array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        $deviceIds = array_values(array_unique(array_filter(array_map(
            static fn($r) => (int)($r['source_device_id'] ?? 0), $rows
        ))));
        if (empty($deviceIds)) {
            foreach ($rows as &$r) { $r['operator'] = ''; }
            unset($r);
            return;
        }

        // device -> asset id
        $deviceToAsset = [];
        foreach (\addon\hsx_erp\app\model\ErpAsset::where([['site_id', '=', $siteId]])
                     ->whereIn('source_device_id', $deviceIds)
                     ->field('id,source_device_id')->select()->toArray() as $a) {
            $deviceToAsset[(int)$a['source_device_id']] = (int)$a['id'];
        }
        $assetIds = array_values(array_unique(array_filter(array_values($deviceToAsset))));

        // 操作事件（按资产）
        $eventsByAsset = [];
        $needUids = [];
        if (!empty($assetIds)) {
            foreach (\addon\hsx_erp\app\model\ErpOperationEvent::where([['site_id', '=', $siteId]])
                         ->whereIn('asset_id', $assetIds)
                         ->field('asset_id,operator_id,operator_name,occurred_at')
                         ->order('occurred_at desc')->select()->toArray() as $e) {
                $eventsByAsset[(int)$e['asset_id']][] = $e;
                if ((string)$e['operator_name'] === '' && (int)$e['operator_id'] > 0) {
                    $needUids[(int)$e['operator_id']] = true;
                }
            }
        }
        $uidNameMap = [];
        if (!empty($needUids)) {
            try {
                foreach (\app\model\sys\SysUser::whereIn('uid', array_keys($needUids))
                             ->field('uid,username,real_name')->select()->toArray() as $u) {
                    $uidNameMap[(int)$u['uid']] = (string)($u['real_name'] ?: $u['username'] ?: '');
                }
            } catch (\Throwable $e) {
            }
        }

        foreach ($rows as &$r) {
            $assetId = $deviceToAsset[(int)($r['source_device_id'] ?? 0)] ?? 0;
            $ts = (int)($r['occurred_at'] ?? 0);
            $best = '';
            $bestDiff = PHP_INT_MAX;
            foreach (($eventsByAsset[$assetId] ?? []) as $e) {
                $name = (string)$e['operator_name'] !== '' ? (string)$e['operator_name'] : ($uidNameMap[(int)$e['operator_id']] ?? '');
                if ($name === '') {
                    continue;
                }
                $diff = abs((int)$e['occurred_at'] - $ts);
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $best = $name;
                }
            }
            $r['operator'] = $best;
        }
        unset($r);
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
