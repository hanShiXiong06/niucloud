<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\model\FinanceReceivable;
use addon\hsx_erp\app\model\FinanceSettlement;
use addon\hsx_erp\app\model\FinanceSettlementLink;
use addon\hsx_erp\app\service\admin\ErpCapitalAccountService;
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
    public function preview($counterparty, array $payableIds, array $receivableIds): array
    {
        return $this->plan($counterparty, $payableIds, $receivableIds)['summary'];
    }

    /** 取一组对接人(主体)未结的应付/应收清单, 供主体级折账勾选 */
    public function outstandingByMembers(array $memberIds): array
    {
        $cpIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        if (empty($cpIds)) {
            return ['payables' => [], 'receivables' => []];
        }
        $open = [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL];
        $build = function ($model) use ($cpIds, $open) {
            $list = $model->where([['site_id', '=', $this->site_id]])
                ->whereIn('counterparty_id', $cpIds)
                ->whereIn('status', $open)
                ->order('occurred_at asc')->select()->toArray();
            $rows = [];
            foreach ($list as $r) {
                $out = round((float)$r['amount'] - (float)$r['settled_amount'], 2);
                if ($out <= 0) { continue; }
                $r['outstanding'] = $out;
                $rows[] = $r;
            }
            return $rows;
        };
        return ['payables' => $build(new FinancePayable()), 'receivables' => $build(new FinanceReceivable())];
    }

    /** 结算记录(已结清历史)，支持往来单位/关键词/时间筛选 + 分页（逐条结算） */
    public function getPage(array $where = []): array
    {
        $query = FinanceSettlement::where([['site_id', '=', $this->site_id]])->order('id desc');
        $this->applySettlementFilters($query, $where);
        $page = $this->pageQuery($query);
        if (!empty($page['data']) && is_array($page['data'])) {
            $this->enrichSettlementRows($page['data']);
        }
        return $page;
    }

    /**
     * 结算记录「按设备/账单分组」分页：同一台机器的多次结算永远合并为一行（跨页也不拆），
     * 单次结算直接平铺，多次结算返回父行 + children 明细。分页单位是"设备/账单"而非"每条结算"。
     */
    public function getGroupedByDevice(array $where = []): array
    {
        $page  = max(1, (int)($where['page'] ?? 1));
        $limit = max(1, (int)($where['limit'] ?? 15));

        // 1) 命中的全部结算 id + 时间（轻量）
        $q = FinanceSettlement::where([['site_id', '=', $this->site_id]]);
        $this->applySettlementFilters($q, $where);
        $idRows = $q->field('id,occurred_at')->order('id desc')->select()->toArray();
        if (empty($idRows)) {
            return ['data' => [], 'total' => 0, 'current_page' => $page, 'last_page' => 0, 'per_page' => $limit];
        }
        $tsMap = [];
        foreach ($idRows as $r) {
            $tsMap[(int)$r['id']] = (int)$r['occurred_at'];
        }
        $allIds = array_keys($tsMap);

        // 2) 每条结算 → 主设备 + 来源单
        $devOf = $this->settlementPrimaryDevice($allIds);

        // 3) 按"主单据 source_no"分组（同一销售单/同一单据的多笔结算合并；无单据的各自成组）
        //    注意：不能按 device_id 分组——同一台设备既有"进货(回收应付)"又有"卖货(销售应收)"的结算，
        //    它们 source_no 不同(R… vs CK…)，按设备会把买进与卖出两笔无关结算错误合并成一条。
        $groups = [];
        foreach ($allIds as $sid) {
            $did = (int)($devOf[$sid]['device_id'] ?? 0);
            $srcNo = trim((string)($devOf[$sid]['source_no'] ?? ''));
            $key = $srcNo !== '' ? 'src_' . $srcNo : 's_' . $sid;
            if (!isset($groups[$key])) {
                $groups[$key] = ['device_id' => $did, 'source_no' => $srcNo, 'sids' => [], 'ts' => 0];
            }
            $groups[$key]['sids'][] = $sid;
            $groups[$key]['ts'] = max($groups[$key]['ts'], $tsMap[$sid] ?? 0);
        }
        $groupList = array_values($groups);
        usort($groupList, static fn($a, $b) => $b['ts'] <=> $a['ts']);
        $total = count($groupList);
        $pageGroups = array_slice($groupList, ($page - 1) * $limit, $limit);

        // 4) 取本页涉及的结算完整行并富化
        $pageSids = [];
        foreach ($pageGroups as $g) {
            $pageSids = array_merge($pageSids, $g['sids']);
        }
        $rowMap = [];
        if (!empty($pageSids)) {
            $rows = FinanceSettlement::where([['site_id', '=', $this->site_id]])->whereIn('id', $pageSids)->select()->toArray();
            $this->enrichSettlementRows($rows);
            foreach ($rows as $r) {
                $rowMap[(int)$r['id']] = $r;
            }
        }

        // 5) 组装：单条平铺；多条父行 + children
        $data = [];
        foreach ($pageGroups as $g) {
            $list = [];
            foreach ($g['sids'] as $sid) {
                if (isset($rowMap[$sid])) {
                    $list[] = $rowMap[$sid];
                }
            }
            if (count($list) <= 1) {
                if (!empty($list)) {
                    $one = $list[0];
                    $one['row_key'] = 'one_' . $one['id'];
                    $data[] = $one;
                }
                continue;
            }
            usort($list, static fn($a, $b) => (int)$b['occurred_at'] <=> (int)$a['occurred_at']);
            $sum = static fn($f) => array_sum(array_map(static fn($r) => (float)($r[$f] ?? 0), $list));
            $t0 = $list[0]['targets'][0] ?? [];
            $children = [];
            foreach ($list as $r) {
                $r['row_key'] = 'cld_' . $r['id'];
                $children[] = $r;
            }
            $data[] = [
                'row_key'            => 'grp_' . $g['device_id'] . '_' . $g['source_no'],
                '_group'             => true,
                'count'              => count($list),
                'settlement_no'      => '共 ' . count($list) . ' 笔结算',
                'is_entity'          => $list[0]['is_entity'] ?? false,
                'entity_id'          => $list[0]['entity_id'] ?? 0,
                'entity_name'        => $list[0]['entity_name'] ?? '',
                'counterparty_name'  => $list[0]['counterparty_name'] ?? '',
                'counterparty_mobile' => $list[0]['counterparty_mobile'] ?? '',
                'targets'            => $t0 ? [['model' => $t0['model'] ?? '', 'source_no' => $t0['source_no'] ?? '', 'amount' => round($sum('cash_amount') + $sum('offset_amount'), 2)]] : [],
                'payable_total'      => round($sum('payable_total'), 2),
                'receivable_total'   => round($sum('receivable_total'), 2),
                'offset_amount'      => round($sum('offset_amount'), 2),
                'cash_amount'        => round($sum('cash_amount'), 2),
                'cash_direction'     => $list[0]['cash_direction'] ?? '',
                'operator_name'      => '',
                'method'             => '',
                'account_name'       => '',
                'occurred_at'        => (int)$g['ts'],
                'children'           => $children,
            ];
        }
        return ['data' => $data, 'total' => $total, 'current_page' => $page, 'last_page' => (int)ceil($total / $limit), 'per_page' => $limit];
    }

    /** 结算列表的筛选条件（getPage 与分组分页共用） */
    private function applySettlementFilters($query, array $where): void
    {
        if (!empty($where['counterparty_id'])) {
            $query->where('counterparty_id', '=', (int)$where['counterparty_id']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $cpIds = FinanceCounterpartyBalanceService::counterpartyIdsByKeyword($this->site_id, $kw);
            $query->where(function ($q) use ($kw, $cpIds) {
                $q->whereLike('settlement_no|counterparty_name', '%' . $kw . '%');
                if (!empty($cpIds)) {
                    $q->whereOr('counterparty_id', 'in', $cpIds);
                }
            });
        }
        if (!empty($where['operator'])) {
            $query->whereLike('operator_name', '%' . trim((string)$where['operator']) . '%');
        }
        // IMEI 检索: 设备串号 → 命中应付/应收 → 经核销关系反查结算单 id
        if (!empty($where['imei'])) {
            $sids = $this->settlementIdsByImei(trim((string)$where['imei']));
            $query->whereIn('id', !empty($sids) ? $sids : [-1]);
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
    }

    /** 按 IMEI 反查命中的结算单 id: 设备串号 → source_device_id → 应付/应收 id → 核销关系 settlement_id */
    private function settlementIdsByImei(string $imei): array
    {
        if ($imei === '') {
            return [];
        }
        $devIds = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereLike('imei', '%' . $imei . '%')->column('source_device_id');
        $devIds = array_values(array_unique(array_filter(array_map('intval', $devIds))));
        if (empty($devIds)) {
            return [];
        }
        $payIds = FinancePayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('source_device_id', $devIds)->column('id');
        $recIds = FinanceReceivable::where([['site_id', '=', $this->site_id]])
            ->whereIn('source_device_id', $devIds)->column('id');
        $payIds = array_values(array_filter(array_map('intval', $payIds)));
        $recIds = array_values(array_filter(array_map('intval', $recIds)));
        if (empty($payIds) && empty($recIds)) {
            return [];
        }
        $linkQuery = FinanceSettlementLink::where([['site_id', '=', $this->site_id]]);
        $linkQuery->where(function ($q) use ($payIds, $recIds) {
            $has = false;
            if (!empty($payIds)) {
                $q->where(function ($w) use ($payIds) {
                    $w->where('target_type', '=', 'payable')->whereIn('target_id', $payIds);
                });
                $has = true;
            }
            if (!empty($recIds)) {
                $method = $has ? 'whereOr' : 'where';
                $q->{$method}(function ($w) use ($recIds) {
                    $w->where('target_type', '=', 'receivable')->whereIn('target_id', $recIds);
                });
            }
        });
        $sids = $linkQuery->column('settlement_id');
        return array_values(array_unique(array_filter(array_map('intval', $sids))));
    }

    /** 取每条结算的"主设备"(第一个核销目标的 source_device_id) + 来源单号 */
    private function settlementPrimaryDevice(array $sids): array
    {
        if (empty($sids)) {
            return [];
        }
        $links = FinanceSettlementLink::where([['site_id', '=', $this->site_id]])
            ->whereIn('settlement_id', $sids)
            ->field('settlement_id,target_type,target_id')->order('id asc')->select()->toArray();
        if (empty($links)) {
            return [];
        }
        $payIds = [];
        $recIds = [];
        foreach ($links as $l) {
            if ((string)$l['target_type'] === 'payable') {
                $payIds[] = (int)$l['target_id'];
            } else {
                $recIds[] = (int)$l['target_id'];
            }
        }
        $finMap = [];
        if (!empty($payIds)) {
            foreach (FinancePayable::where([['site_id', '=', $this->site_id]])->whereIn('id', array_unique($payIds))
                         ->field('id,source_no,source_device_id')->select()->toArray() as $f) {
                $finMap['payable_' . (int)$f['id']] = $f;
            }
        }
        if (!empty($recIds)) {
            foreach (FinanceReceivable::where([['site_id', '=', $this->site_id]])->whereIn('id', array_unique($recIds))
                         ->field('id,source_no,source_device_id')->select()->toArray() as $f) {
                $finMap['receivable_' . (int)$f['id']] = $f;
            }
        }
        $out = [];
        foreach ($links as $l) {
            $sid = (int)$l['settlement_id'];
            if (isset($out[$sid])) {
                continue; // 取第一个目标作为主设备
            }
            $f = $finMap[(string)$l['target_type'] . '_' . (int)$l['target_id']] ?? null;
            if (!$f) {
                continue;
            }
            $out[$sid] = ['device_id' => (int)($f['source_device_id'] ?? 0), 'source_no' => (string)($f['source_no'] ?? '')];
        }
        return $out;
    }

    /** 富化结算行：户头 + 主体/对接人 + 结算对象设备 */
    private function enrichSettlementRows(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        $memberMap = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, array_column($rows, 'counterparty_id'));
        $entityNameMap = [];
        $allCpIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'counterparty_id')))));
        if (!empty($allCpIds)) {
            foreach (\addon\hsx_erp\app\model\ErpCounterparty::where([['site_id', '=', $this->site_id]])->whereIn('id', $allCpIds)->field('id,name')->select()->toArray() as $e) {
                $entityNameMap[(int)$e['id']] = (string)$e['name'];
            }
        }
        $acctMap = [];
        $nos = array_values(array_filter(array_column($rows, 'settlement_no')));
        if (!empty($nos)) {
            $ledgers = \addon\hsx_erp\app\model\ErpCapitalLedger::where([
                ['site_id', '=', $this->site_id], ['biz_type', '=', 'settlement'],
            ])->whereIn('source_no', $nos)->field('source_no,account_name')->select()->toArray();
            foreach ($ledgers as $lg) {
                $acctMap[(string)$lg['source_no']] = (string)$lg['account_name'];
            }
        }
        foreach ($rows as &$row) {
            $acct = (string)($row['account_name'] ?? '');
            if ($acct === '') {
                $acct = (string)($acctMap[(string)($row['settlement_no'] ?? '')] ?? '');
            }
            if ($acct === '' && (string)($row['method'] ?? '') !== 'offset' && (float)($row['cash_amount'] ?? 0) != 0.0) {
                $dir = ((string)($row['cash_direction'] ?? '') === 'collect') ? 'in' : 'out';
                $oc = (int)($row['occurred_at'] ?? 0);
                $near = \addon\hsx_erp\app\model\ErpCapitalLedger::where([
                    ['site_id', '=', $this->site_id],
                    ['counterparty_id', '=', (int)($row['counterparty_id'] ?? 0)],
                    ['direction', '=', $dir],
                ])->where('occurred_at', '>=', $oc - 10)->where('occurred_at', '<=', $oc + 10)
                    ->order('id desc')->value('account_name');
                $acct = (string)($near ?: '');
            }
            $row['account_name'] = $acct;
            $cpid = (int)($row['counterparty_id'] ?? 0);
            $row['is_entity'] = false;
            $m = $memberMap[$cpid] ?? null;
            if ($m) {
                if ((string)($row['counterparty_name'] ?? '') === '') {
                    $row['counterparty_name'] = $m['name'];
                }
                $row['counterparty_mobile'] = $m['mobile'];
                $row['entity_id'] = $m['entity_id'];
                $row['entity_name'] = $m['entity_name'];
            } elseif (isset($entityNameMap[$cpid])) {
                $row['is_entity'] = true;
                $row['entity_id'] = $cpid;
                $row['entity_name'] = $entityNameMap[$cpid];
                if ((string)($row['counterparty_name'] ?? '') === '') {
                    $row['counterparty_name'] = $entityNameMap[$cpid];
                }
            }
            if ((string)($row['counterparty_name'] ?? '') === '') {
                $row['counterparty_name'] = '往来#' . $cpid;
            }
        }
        unset($row);
        $this->appendSettlementTargets($rows);
    }

    /**
     * 给每条结算单补「结算对象」摘要：关联的设备型号 + 来源单号。
     * 这样列表里能看出多条结算其实指向同一台机器/同一笔账。
     */
    private function appendSettlementTargets(array &$rows): void
    {
        $sids = array_values(array_filter(array_map(static fn($r) => (int)($r['id'] ?? 0), $rows)));
        if (empty($sids)) {
            return;
        }
        $links = FinanceSettlementLink::where([['site_id', '=', $this->site_id]])
            ->whereIn('settlement_id', $sids)
            ->field('settlement_id,target_type,target_id,applied_amount')->select()->toArray();
        if (empty($links)) {
            foreach ($rows as &$r) { $r['targets'] = []; }
            unset($r);
            return;
        }
        $payIds = array_values(array_unique(array_filter(array_map(static fn($l) => (string)$l['target_type'] === 'payable' ? (int)$l['target_id'] : 0, $links))));
        $recIds = array_values(array_unique(array_filter(array_map(static fn($l) => (string)$l['target_type'] === 'receivable' ? (int)$l['target_id'] : 0, $links))));
        $finMap = [];
        if (!empty($payIds)) {
            foreach (FinancePayable::where([['site_id', '=', $this->site_id]])->whereIn('id', $payIds)
                         ->field('id,source_no,source_device_id')->select()->toArray() as $f) {
                $finMap['payable_' . (int)$f['id']] = $f;
            }
        }
        if (!empty($recIds)) {
            foreach (FinanceReceivable::where([['site_id', '=', $this->site_id]])->whereIn('id', $recIds)
                         ->field('id,source_no,source_device_id')->select()->toArray() as $f) {
                $finMap['receivable_' . (int)$f['id']] = $f;
            }
        }
        $deviceIds = array_values(array_unique(array_filter(array_map(static fn($f) => (int)($f['source_device_id'] ?? 0), $finMap))));
        $deviceMap = [];
        if (!empty($deviceIds)) {
            foreach (\addon\hsx_erp\app\model\ErpAsset::where([['site_id', '=', $this->site_id]])
                         ->whereIn('source_device_id', $deviceIds)->field('source_device_id,model,imei,capacity,color')->select()->toArray() as $a) {
                $deviceMap[(int)$a['source_device_id']] = $a;
            }
        }
        $identityMap = DeviceIdentityService::map($this->site_id, $deviceIds);
        $bySettlement = [];
        foreach ($links as $l) {
            $key = (string)$l['target_type'] . '_' . (int)$l['target_id'];
            $f = $finMap[$key] ?? null;
            if (!$f) {
                continue;
            }
            $did = (int)($f['source_device_id'] ?? 0);
            $dev = $deviceMap[$did] ?? null;
            $target = [
                'model'      => (string)($dev['model'] ?? ''),
                'imei'       => (string)($dev['imei'] ?? ''),
                'source_no'  => (string)($f['source_no'] ?? ''),
                'amount'     => round((float)$l['applied_amount'], 2),
                'device_id'  => $did,
                'device_model'    => (string)($dev['model'] ?? ''),
                'device_imei'     => (string)($dev['imei'] ?? ''),
                'device_capacity' => (string)($dev['capacity'] ?? ''),
                'device_color'    => (string)($dev['color'] ?? ''),
            ];
            DeviceIdentityService::attachToRow($target, $identityMap[$did] ?? null);
            $bySettlement[(int)$l['settlement_id']][] = $target;
        }
        foreach ($rows as &$r) {
            $r['targets'] = $bySettlement[(int)($r['id'] ?? 0)] ?? [];
        }
        unset($r);
    }

    /**
     * 结算单核销明细: 这次结算把哪些应付、哪些应收核销了, 各自折账多少/现金多少, 关联到人和设备。
     * 用于"点开结算记录看是哪笔折哪笔、谁给谁多少钱"。
     */
    public function getDetail(int $id): array
    {
        $st = FinanceSettlement::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($st->isEmpty()) {
            throw new CommonException('结算单不存在');
        }
        $head = $st->toArray();
        // 主体名(主体级结算)
        $cpid = (int)($head['counterparty_id'] ?? 0);
        $entity = \addon\hsx_erp\app\model\ErpCounterparty::where([['site_id', '=', $this->site_id], ['id', '=', $cpid]])->value('name');
        $head['entity_name'] = (string)($entity ?: '');

        $links = FinanceSettlementLink::where([['site_id', '=', $this->site_id], ['settlement_id', '=', $id]])->select()->toArray();
        $payIds = [];
        $recIds = [];
        foreach ($links as $l) {
            if ((string)$l['target_type'] === FinanceDict::TARGET_PAYABLE) { $payIds[] = (int)$l['target_id']; }
            else { $recIds[] = (int)$l['target_id']; }
        }
        $payMap = !empty($payIds) ? array_column(FinancePayable::where([['site_id', '=', $this->site_id]])->whereIn('id', $payIds)->select()->toArray(), null, 'id') : [];
        $recMap = !empty($recIds) ? array_column(FinanceReceivable::where([['site_id', '=', $this->site_id]])->whereIn('id', $recIds)->select()->toArray(), null, 'id') : [];

        // 对接人(会员)名 + 设备型号/IMEI
        $cpIds = array_merge(array_column($payMap, 'counterparty_id'), array_column($recMap, 'counterparty_id'));
        $memberMap = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, $cpIds);
        $deviceIds = array_values(array_filter(array_merge(array_column($payMap, 'source_device_id'), array_column($recMap, 'source_device_id'))));
        $deviceMap = [];
        if (!empty($deviceIds)) {
            foreach (\addon\hsx_erp\app\model\ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('source_device_id', $deviceIds)->field('source_device_id,model,imei,capacity,color,asset_no')->select()->toArray() as $a) {
                $deviceMap[(int)$a['source_device_id']] = $a;
            }
        }
        $identityMap = DeviceIdentityService::map($this->site_id, $deviceIds);

        $mk = function (array $l, ?array $src) use ($memberMap, $deviceMap, $identityMap) {
            if (!$src) { return null; }
            $m = $memberMap[(int)$src['counterparty_id']] ?? null;
            $did = (int)($src['source_device_id'] ?? 0);
            $dev = $deviceMap[$did] ?? null;
            $row = [
                'counterparty_name' => $m['name'] ?? (string)($src['counterparty_name'] ?? ''),
                'counterparty_mobile' => $m['mobile'] ?? '',
                'source_type_text'  => FinanceDict::sourceTypeText((string)($src['source_type'] ?? '')),
                'source_no'         => (string)($src['source_no'] ?? ''),
                'device_model'      => (string)($dev['model'] ?? ''),
                'device_imei'       => (string)($dev['imei'] ?? ''),
                'device_capacity'   => (string)($dev['capacity'] ?? ''),
                'device_color'      => (string)($dev['color'] ?? ''),
                'asset_no'          => (string)($dev['asset_no'] ?? ''),
                'amount'            => round((float)($src['amount'] ?? 0), 2),
                'applied_amount'    => round((float)($l['applied_amount'] ?? 0), 2),
                'offset_part'       => round((float)($l['offset_part'] ?? 0), 2),
                'cash_part'         => round((float)($l['pay_part'] ?? 0), 2),
                'remark'            => (string)($src['remark'] ?? ''),
            ];
            DeviceIdentityService::attachToRow($row, $identityMap[$did] ?? null);
            return $row;
        };
        $payables = [];
        $receivables = [];
        foreach ($links as $l) {
            $tid = (int)$l['target_id'];
            if ((string)$l['target_type'] === FinanceDict::TARGET_PAYABLE) {
                $row = $mk($l, $payMap[$tid] ?? null);
                if ($row) { $payables[] = $row; }
            } else {
                $row = $mk($l, $recMap[$tid] ?? null);
                if ($row) { $receivables[] = $row; }
            }
        }
        return ['settlement' => $head, 'payables' => $payables, 'receivables' => $receivables];
    }

    /**
     * 按往来单位自动结算其全部未结往来(给回收"打款即折账"用)。
     * 自动取该单位所有待结应付+应收, 折账冲抵, 余下走现金净额。
     * 返回 summary 含 cash_amount/cash_direction, 回收据此知道实际要打多少现金。
     */
    public function settleAllByCounterparty(int $counterpartyId, array $options = []): array
    {
        $payableIds    = $this->allOutstandingIds(new FinancePayable(), $counterpartyId);
        $receivableIds = $this->allOutstandingIds(new FinanceReceivable(), $counterpartyId);
        if (empty($payableIds) && empty($receivableIds)) {
            throw new CommonException('该往来单位没有待结算的应付或应收');
        }
        return $this->settle($counterpartyId, $payableIds, $receivableIds, $options);
    }

    /**
     * 按来源设备精确核销应付（给回收"打款即核销"用）。
     * 只结这些设备对应的待结应付（不折应收、现金净付），与打款的资金账户扣减一一对应，
     * 避免 settleAllByCounterparty 把该客户其它未付订单一并清掉导致账实不符。
     * 幂等：已结清的设备不会再被选中。
     * @param array $deviceIds 回收设备ID（= 应付的 source_device_id）
     * @return array ['settled'=>int 结清应付笔数, 'results'=>array]
     */
    public function settleByDeviceIds(array $deviceIds, array $options = []): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            return ['settled' => 0, 'results' => []];
        }
        $rows = FinancePayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('source_device_id', $deviceIds)
            ->whereIn('status', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL])
            ->whereRaw('amount - settled_amount > 0')
            ->field('id,counterparty_id')
            ->select()
            ->toArray();
        if (empty($rows)) {
            return ['settled' => 0, 'results' => []];
        }
        // 按往来单位分组逐个结算（settle 要求同一往来单位）
        $byCp = [];
        foreach ($rows as $r) {
            $cpId = (int)$r['counterparty_id'];
            if ($cpId > 0) {
                $byCp[$cpId][] = (int)$r['id'];
            }
        }
        $results = [];
        $settled = 0;
        foreach ($byCp as $cpId => $payableIds) {
            $results[] = $this->settle($cpId, $payableIds, [], $options);
            $settled += count($payableIds);
        }
        return ['settled' => $settled, 'results' => $results];
    }

    /**
     * 单笔应付的「部分/全额现金付款」(给 ERP 入库"已付"当场结用)。
     * 与 settle() 不同: settle 把所选应付整笔结清; 这里按金额部分核销, 支持订金。
     * 现金从指定户头出账(余额预检 + 扣减 + 资金流水), 操作人留痕。同一事务: 余额不足整笔回滚。
     * @param int   $payableId 应付ID
     * @param float $payAmount 本次付款金额(超过未结额时自动按未结额封顶)
     * @param int   $accountId 付款户头
     * @param array $options   可含 remark
     * @return array ['settlement_id','settlement_no','paid','full']
     */
    public function payCashByPayable(int $payableId, float $payAmount, int $accountId, array $options = []): array
    {
        $payAmount = round($payAmount, 2);
        if ($payableId <= 0 || $payAmount <= 0) {
            throw new CommonException('付款参数不正确');
        }
        if ($accountId <= 0) {
            throw new CommonException('请选择付款户头');
        }
        $payable = FinancePayable::where([['site_id', '=', $this->site_id], ['id', '=', $payableId]])->findOrEmpty();
        if ($payable->isEmpty()) {
            throw new CommonException('应付单不存在');
        }
        $outstanding = round((float)$payable->amount - (float)$payable->settled_amount, 2);
        if ($outstanding <= 0) {
            throw new CommonException('该应付已结清');
        }
        if ($payAmount > $outstanding) {
            $payAmount = $outstanding; // 不超付
        }
        $acc = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $accountId]])->findOrEmpty();
        if ($acc->isEmpty()) {
            throw new CommonException('付款户头不存在');
        }
        if (round((float)$acc->balance, 2) < $payAmount) {
            throw new CommonException(sprintf('账户「%s」余额不足：当前 %.2f，需付出 %.2f，请改用其他户头', (string)$acc->account_name, (float)$acc->balance, $payAmount));
        }

        $cpId = (int)$payable->counterparty_id;
        $cpName = (string)$payable->counterparty_name;
        $now = time();
        $no = 'JS' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $eventId = 'finance_pay_' . $no;
        $newSettled = round((float)$payable->settled_amount + $payAmount, 2);
        $fullSettled = $newSettled + 0.001 >= round((float)$payable->amount, 2);
        $settlementId = 0;

        Db::transaction(function () use ($payable, $payAmount, $accountId, $acc, $cpId, $cpName, $now, $no, $eventId, $newSettled, $fullSettled, $options, &$settlementId) {
            $settlement = FinanceSettlement::create([
                'site_id'            => $this->site_id,
                'settlement_no'      => $no,
                'counterparty_id'    => $cpId,
                'counterparty_name'  => $cpName,
                'capital_account_id' => $accountId,
                'account_name'       => (string)$acc->account_name,
                'method'             => FinanceDict::METHOD_CASH,
                'payable_total'      => $payAmount,
                'receivable_total'   => 0,
                'offset_amount'      => 0,
                'cash_amount'        => $payAmount,
                'cash_direction'     => FinanceDict::CASH_PAY,
                'status'             => 'completed',
                'operator_uid'       => (int)$this->uid,
                'operator_name'      => (string)$this->username,
                'event_id'           => $eventId,
                'occurred_at'        => $now,
                'remark'             => (string)($options['remark'] ?? '入库已付'),
                'create_time'        => $now,
                'update_time'        => $now,
            ]);
            $settlementId = (int)$settlement->id;

            FinanceSettlementLink::create([
                'site_id'        => $this->site_id,
                'settlement_id'  => $settlementId,
                'target_type'    => FinanceDict::TARGET_PAYABLE,
                'target_id'      => (int)$payable->id,
                'applied_amount' => $payAmount,
                'pay_part'       => $payAmount,
                'offset_part'    => 0,
                'create_time'    => $now,
            ]);

            FinancePayable::where([['site_id', '=', $this->site_id], ['id', '=', (int)$payable->id]])->update([
                'settled_amount' => $newSettled,
                'status'         => $fullSettled ? FinanceDict::STATUS_SETTLED : FinanceDict::STATUS_PARTIAL,
                'update_time'    => $now,
            ]);

            // 现金出账(余额不足 recordEntry 抛异常 → 整笔回滚)
            (new ErpCapitalAccountService())->recordEntry([
                'account_id'        => $accountId,
                'direction'         => 'out',
                'amount'            => $payAmount,
                'biz_type'          => 'settlement',
                'counterparty_id'   => $cpId,
                'counterparty_name' => $cpName,
                'source_type'       => 'settlement',
                'source_no'         => $no,
                'source_id'         => $settlementId,
                'remark'            => '入库已付现金付出',
            ]);
        });

        return ['settlement_id' => $settlementId, 'settlement_no' => $no, 'paid' => $payAmount, 'full' => $fullSettled];
    }

    private function allOutstandingIds($model, int $counterpartyId): array
    {
        $rows = $model->where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['status', 'in', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL]],
        ])->whereRaw('amount - settled_amount > 0')->column('id');
        return array_map('intval', $rows);
    }

    /**
     * 执行结算
     * @param int|array $counterparty 单个对接人ID, 或一组对接人ID(主体级折账, 跨人冲抵)
     * @param array $options 可含 anchor_id/anchor_name(结算单归属与名称, 主体级时传主体ID与主体名)
     */
    public function settle($counterparty, array $payableIds, array $receivableIds, array $options = []): array
    {
        $cpIds = is_array($counterparty)
            ? array_values(array_unique(array_filter(array_map('intval', $counterparty))))
            : [(int)$counterparty];
        $plan = $this->plan($cpIds, $payableIds, $receivableIds);
        $summary = $plan['summary'];
        if ($summary['payable_total'] <= 0 && $summary['receivable_total'] <= 0) {
            throw new CommonException('没有可结算的应付或应收');
        }
        // 现金出账(付款)预检: 先确认所选户头余额够, 不够直接提示换户头(避免标记结清后才发现钱不够)
        $preRecordCash = ($options['record_cash'] ?? true) !== false;
        $preAccountId = (int)($options['capital_account_id'] ?? 0);
        $preCash = round((float)($summary['cash_amount'] ?? 0), 2);
        if ($preRecordCash && $preAccountId > 0 && $preCash > 0 && (string)($summary['cash_direction'] ?? '') === FinanceDict::CASH_PAY) {
            $acc = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $preAccountId]])->findOrEmpty();
            if (!$acc->isEmpty() && round((float)$acc->balance, 2) < $preCash) {
                throw new CommonException(sprintf('账户「%s」余额不足：当前 %.2f，需付出 %.2f，请改用其他户头', (string)$acc->account_name, (float)$acc->balance, $preCash));
            }
        }
        // 结算单归属锚点与名称: 主体级折账传主体ID/主体名; 否则用首个对接人
        $anchorId = (int)($options['anchor_id'] ?? ($cpIds[0] ?? 0));
        $anchorName = (string)($options['anchor_name'] ?? ($summary['counterparty_name'] ?? ''));

        $now = time();
        $no = 'JS' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $eventId = 'finance_settle_' . $no;
        $settlementId = 0;

        // 结算所用资金户头(用于展示/对账): 解析名称. 列若未迁移会被框架自动忽略, 不影响.
        $optAccountId = (int)($options['capital_account_id'] ?? 0);
        $optAccountName = '';
        if ($optAccountId > 0) {
            $optAccountName = (string)(ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $optAccountId]])->value('account_name') ?: '');
        }

        $recordCash = ($options['record_cash'] ?? true) !== false;
        $cashAmount = round((float)($summary['cash_amount'] ?? 0), 2);
        Db::transaction(function () use ($plan, $summary, $anchorId, $anchorName, $options, $optAccountId, $optAccountName, $recordCash, $cashAmount, $now, $no, $eventId, &$settlementId) {
            $settlement = FinanceSettlement::create([
                'site_id'          => $this->site_id,
                'settlement_no'    => $no,
                'counterparty_id'  => $anchorId,
                'counterparty_name'=> $anchorName,
                'capital_account_id' => $optAccountId,
                'account_name'     => $optAccountName,
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

            // 现金记账放在同一事务内: 余额不足 recordEntry 会抛异常 → 整笔结算回滚(不会出现"已结清但没扣钱")。
            // record_cash=false: 现金已由外部(如回收打款)扣账, 此处只核销不再二次记流水。
            if ($recordCash && $optAccountId > 0 && $cashAmount > 0) {
                (new ErpCapitalAccountService())->recordEntry([
                    'account_id'        => $optAccountId,
                    'direction'         => ($summary['cash_direction'] ?? '') === 'pay' ? 'out' : 'in',
                    'amount'            => $cashAmount,
                    'biz_type'          => 'settlement',
                    'counterparty_id'   => $anchorId,
                    'counterparty_name' => $anchorName,
                    'source_type'       => 'settlement',
                    'source_no'         => $no,
                    'source_id'         => $settlementId,
                    'remark'            => '结算现金' . ((($summary['cash_direction'] ?? '') === 'pay') ? '付出' : '收取'),
                ]);
            }
        });

        // 发结算完成事件(故障隔离, 不回抛): 业务/ERP 订阅以更新各自展示
        $this->emitSettlementCompleted($settlementId, $no, $anchorId, $summary, $plan, $eventId, $now);

        // 同行销售应收被收齐 → 把对应设备从「销售锁定」翻成「已售/下架」(所有收款入口统一闭环)
        $this->markPeerSaleSoldByReceivables($plan['receivable_alloc'] ?? []);

        return ['settlement_id' => $settlementId, 'settlement_no' => $no, 'summary' => $summary];
    }

    /**
     * 同行销售应收被收齐后, 把对应设备从「销售锁定(LOCKED)」翻成「已售/下架(OUTBOUND)」。
     * 覆盖所有收款入口(财务中心收款 / 出库回填收款 / 卖同行待办), 故障隔离不回抛。
     * @param array $receivableAlloc plan 的 receivable_alloc(含被本次核销的应收 id)
     */
    private function markPeerSaleSoldByReceivables(array $receivableAlloc): void
    {
        try {
            $rids = array_values(array_filter(array_map(static fn($a) => (int)($a['id'] ?? 0), $receivableAlloc)));
            if (empty($rids)) {
                return;
            }
            // 只取已收齐(无未结额)的销售应收(同行/商城)对应的设备
            $recs = FinanceReceivable::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', $rids)
                ->whereIn('source_type', ['erp_peer_sale', 'erp_mall_sale'])
                ->whereRaw('amount - settled_amount <= 0.001')
                ->field('source_device_id,source_no,counterparty_id,amount')
                ->select()->toArray();
            $deviceIds = array_values(array_unique(array_filter(array_map(static fn($r) => (int)($r['source_device_id'] ?? 0), $recs))));
            if (empty($deviceIds)) {
                return;
            }
            $now = time();
            ErpAsset::where([['site_id', '=', $this->site_id], ['inventory_status', '=', ErpDict::INVENTORY_LOCKED]])
                ->whereIn('source_device_id', $deviceIds)
                ->update(['inventory_status' => ErpDict::INVENTORY_OUTBOUND, 'stock_out_at' => $now, 'update_at' => $now]);

            // 通知商城闭环:把对应商城商品下架 + ERP 托管展示单标记完成。
            // 覆盖所有收款入口(财务中心收款 / 出库管理收款 / 卖同行待办),故障隔离不回抛。
            $assetMap = [];
            foreach (ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('source_device_id', $deviceIds)
                         ->field('id,source_device_id')->select()->toArray() as $a) {
                $assetMap[(int)$a['source_device_id']] = (int)$a['id'];
            }
            foreach ($recs as $r) {
                $did = (int)($r['source_device_id'] ?? 0);
                $assetId = $assetMap[$did] ?? 0;
                if ($assetId <= 0) {
                    continue;
                }
                try {
                    event('ErpDomainEvent', [
                        'event_name'   => 'erp.asset.sold.v1',
                        'event_id'     => 'erp_sold_settle_' . $assetId . '_' . $now,
                        'site_id'      => (int)$this->site_id,
                        'aggregate_id' => $assetId,
                        'payload'      => [
                            'asset_id'         => $assetId,
                            'source_device_id' => $did,
                            'outbound_no'      => (string)($r['source_no'] ?? ''),
                            'reason'           => 'peer_sale',
                            'member_id'        => (int)($r['counterparty_id'] ?? 0),
                            'sale_price'       => round((float)($r['amount'] ?? 0), 2),
                            'settle_mode'      => 'now',
                            'result_status'    => 'sold',     // 收齐成交 → 商城下架 + 展示单完成
                            'build_mall_order' => false,       // 只下架/收口, 不重复建单
                        ],
                        'operator'     => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[erp] 收齐通知商城下架/完成失败: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[erp] 同行收齐翻已售失败: ' . $e->getMessage());
        }
    }

    /**
     * 计算结算方案(折账分配)
     * @return array{summary:array, payable_alloc:array, receivable_alloc:array}
     */
    /**
     * 预付折抵专用:用指定应收(如采购预付)"部分核销"指定应付。
     * 与 settle() 不同——只折抵 min(应付未结, 应收未结)，按分配额部分核销，
     * 多出的应收仍留作余额(不抹平、不走现金)。返回实际折抵额。
     */
    public function offsetPrepay(int $payableId, array $receivableIds, array $options = []): float
    {
        $payable = FinancePayable::where([['site_id', '=', $this->site_id], ['id', '=', $payableId]])->findOrEmpty();
        if ($payable->isEmpty()) {
            return 0.0;
        }
        $payOut = round((float)$payable->amount - (float)$payable->settled_amount, 2);
        if ($payOut <= 0) {
            return 0.0;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $receivableIds))));
        if (empty($ids)) {
            return 0.0;
        }
        $recs = FinanceReceivable::where([
            ['site_id', '=', $this->site_id],
            ['id', 'in', $ids],
            ['status', 'in', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL]],
        ])->order('occurred_at asc')->order('id asc')->select();
        $recOut = [];
        $pool = 0.0;
        foreach ($recs as $r) {
            $o = round((float)$r->amount - (float)$r->settled_amount, 2);
            if ($o <= 0) {
                continue;
            }
            $recOut[] = ['id' => (int)$r->id, 'out' => $o];
            $pool = round($pool + $o, 2);
        }
        $offset = round(min($payOut, $pool), 2);
        if ($offset <= 0) {
            return 0.0;
        }

        $cpId = (int)$payable->counterparty_id;
        $cpName = (string)$payable->counterparty_name;
        $now = time();
        $no = 'JS' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $eventId = 'finance_offset_' . $no;

        Db::transaction(function () use ($payableId, $recOut, $offset, $cpId, $cpName, $now, $no, $eventId, $options) {
            $settlement = FinanceSettlement::create([
                'site_id'            => $this->site_id,
                'settlement_no'      => $no,
                'counterparty_id'    => $cpId,
                'counterparty_name'  => $cpName,
                'capital_account_id' => 0,
                'account_name'       => '',
                'method'             => FinanceDict::METHOD_OFFSET,
                'payable_total'      => $offset,
                'receivable_total'   => $offset,
                'offset_amount'      => $offset,
                'cash_amount'        => 0,
                'cash_direction'     => FinanceDict::CASH_NONE,
                'status'             => 'completed',
                'operator_uid'       => (int)$this->uid,
                'operator_name'      => (string)$this->username,
                'event_id'           => $eventId,
                'occurred_at'        => $now,
                'remark'             => (string)($options['remark'] ?? '预付折抵应付'),
                'create_time'        => $now,
                'update_time'        => $now,
            ]);
            $sid = (int)$settlement->id;
            // 应付:部分核销 offset
            $this->applyPartial(new FinancePayable(), $payableId, $offset, $sid, FinanceDict::TARGET_PAYABLE, $now);
            // 应收:FIFO 分摊 offset(多出的预付仍留作应收余额)
            $left = $offset;
            foreach ($recOut as $ro) {
                if ($left <= 0) {
                    break;
                }
                $take = round(min($left, $ro['out']), 2);
                $left = round($left - $take, 2);
                $this->applyPartial(new FinanceReceivable(), $ro['id'], $take, $sid, FinanceDict::TARGET_RECEIVABLE, $now);
            }
        });
        return $offset;
    }

    /** 按分配额"部分核销"一条应付/应收: settled_amount 累加, 满额→已结清, 否则→部分结算, 并写核销链接 */
    private function applyPartial($model, int $id, float $amount, int $settlementId, string $targetType, int $now): void
    {
        $row = $model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty() || $amount <= 0) {
            return;
        }
        $newSettled = round((float)$row->settled_amount + $amount, 2);
        $full = $newSettled + 0.001 >= round((float)$row->amount, 2);
        $model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->update([
            'settled_amount' => $newSettled,
            'status'         => $full ? FinanceDict::STATUS_SETTLED : FinanceDict::STATUS_PARTIAL,
            'update_time'    => $now,
        ]);
        $this->writeLink($settlementId, $targetType, ['id' => $id, 'applied' => $amount, 'offset_part' => $amount, 'cash_part' => 0.0], $now);
    }

    private function plan($counterparty, array $payableIds, array $receivableIds): array
    {
        $cpIds = is_array($counterparty)
            ? array_values(array_unique(array_filter(array_map('intval', $counterparty))))
            : [(int)$counterparty];
        if (empty($cpIds)) {
            throw new CommonException('请选择往来单位');
        }
        $payables    = $this->loadOutstanding(new FinancePayable(), $cpIds, $payableIds);
        $receivables = $this->loadOutstanding(new FinanceReceivable(), $cpIds, $receivableIds);

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
                'counterparty_id'   => $cpIds[0] ?? 0,
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

    private function loadOutstanding($model, $counterparty, array $ids): array
    {
        $cpIds = is_array($counterparty)
            ? array_values(array_unique(array_filter(array_map('intval', $counterparty))))
            : [(int)$counterparty];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids) || empty($cpIds)) {
            return [];
        }
        $list = $model->where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', 'in', $cpIds],
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

    private function emitSettlementCompleted(int $settlementId, string $settlementNo, int $cpId, array $summary, array $plan, string $eventId, int $now): void
    {
        try {
            // 回查被核销应付的来源(回收设备/订单), 供回收端回写打款状态+备注折账单号
            $payIds = array_map(static fn($a) => (int)$a['id'], $plan['payable_alloc']);
            $paySrc = [];
            if (!empty($payIds)) {
                foreach (FinancePayable::where([['site_id', '=', $this->site_id]])->whereIn('id', $payIds)
                    ->field('id,source_type,source_no,source_device_id')->select()->toArray() as $r) {
                    $paySrc[(int)$r['id']] = $r;
                }
            }
            $linked = [];
            foreach ($plan['payable_alloc'] as $a) {
                $s = $paySrc[(int)$a['id']] ?? [];
                $linked[] = [
                    'type' => 'payable', 'id' => $a['id'], 'applied' => $a['applied'], 'offset' => $a['offset_part'], 'cash' => $a['cash_part'],
                    'source_type' => (string)($s['source_type'] ?? ''),
                    'source_no' => (string)($s['source_no'] ?? ''),
                    'source_device_id' => (int)($s['source_device_id'] ?? 0),
                ];
            }
            foreach ($plan['receivable_alloc'] as $a) {
                $linked[] = ['type' => 'receivable', 'id' => $a['id'], 'applied' => $a['applied'], 'offset' => $a['offset_part'], 'cash' => $a['cash_part']];
            }
            $payload = [
                'event'           => FinanceDict::EVENT_SETTLEMENT_DONE,
                'event_id'        => $eventId,
                'site_id'         => $this->site_id,
                'settlement_id'   => $settlementId,
                'settlement_no'   => $settlementNo,
                'counterparty_id' => $cpId,
                'method'          => $summary['method'],
                'offset_amount'   => $summary['offset_amount'],
                'cash_amount'     => $summary['cash_amount'],
                'cash_direction'  => $summary['cash_direction'],
                'linked'          => $linked,
                'operator'        => (string)$this->username,
                'occurred_at'     => $now,
            ];
            Log::info('[erp_finance] 触发结算完成事件 单号=' . $settlementNo . ' 应付来源=' . json_encode(array_filter($linked, static fn($x) => ($x['type'] ?? '') === 'payable'), JSON_UNESCAPED_UNICODE));
            Event::trigger('FinanceSettlementCompleted', $payload);
        } catch (\Throwable $e) {
            Log::warning('[erp_finance] 结算完成事件分发失败: ' . $e->getMessage());
        }
    }
}
