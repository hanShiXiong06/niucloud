<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
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

    /** 结算记录(已结清历史)，支持往来单位/关键词/时间筛选 + 分页 */
    public function getPage(array $where = []): array
    {
        $query = FinanceSettlement::where([['site_id', '=', $this->site_id]])->order('id desc');
        if (!empty($where['counterparty_id'])) {
            $query->where('counterparty_id', '=', (int)$where['counterparty_id']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->where('settlement_no|counterparty_name', 'like', '%' . $kw . '%');
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
        $page = $this->pageQuery($query);
        // 往来单位精确到人：按 counterparty_id(=会员member_id)关联 member 表回填姓名+手机
        // 注意：必须直接对 $page['data'] 取引用，不能用 `$page['data'] ?? []`(那会复制一份导致改动丢失)
        if (!empty($page['data']) && is_array($page['data'])) {
            $memberMap = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, array_column($page['data'], 'counterparty_id'));
            // 主体级折账的结算单 counterparty_id 存的是主体ID(非会员), 回查主体名
            $entityNameMap = [];
            $allCpIds = array_values(array_unique(array_filter(array_map('intval', array_column($page['data'], 'counterparty_id')))));
            if (!empty($allCpIds)) {
                foreach (\addon\hsx_erp\app\model\ErpCounterparty::where([['site_id', '=', $this->site_id]])->whereIn('id', $allCpIds)->field('id,name')->select()->toArray() as $e) {
                    $entityNameMap[(int)$e['id']] = (string)$e['name'];
                }
            }
            // 现金部分走了哪个资金账户(户头)：结算时记的资金流水 source_no=结算单号、biz_type=settlement，回查账户名
            $acctMap = [];
            $nos = array_values(array_filter(array_column($page['data'], 'settlement_no')));
            if (!empty($nos)) {
                $ledgers = \addon\hsx_erp\app\model\ErpCapitalLedger::where([
                    ['site_id', '=', $this->site_id], ['biz_type', '=', 'settlement'],
                ])->whereIn('source_no', $nos)->field('source_no,account_name')->select()->toArray();
                foreach ($ledgers as $lg) {
                    $acctMap[(string)$lg['source_no']] = (string)$lg['account_name'];
                }
            }
            foreach ($page['data'] as &$row) {
                // 户头解析: 1)结算单已存户头列 2)结算自身记的资金流水 3)同往来单位、结算时刻邻近的资金流水(回收打款等外部已扣账)
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
                    // 单人结算: counterparty_id=会员
                    if ((string)($row['counterparty_name'] ?? '') === '') {
                        $row['counterparty_name'] = $m['name'];
                    }
                    $row['counterparty_mobile'] = $m['mobile'];
                    $row['entity_id'] = $m['entity_id'];
                    $row['entity_name'] = $m['entity_name'];
                } elseif (isset($entityNameMap[$cpid])) {
                    // 主体级折账: counterparty_id=主体ID
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
        }
        return $page;
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
            foreach (\addon\hsx_erp\app\model\ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('source_device_id', $deviceIds)->field('source_device_id,model,imei,asset_no')->select()->toArray() as $a) {
                $deviceMap[(int)$a['source_device_id']] = $a;
            }
        }

        $mk = function (array $l, ?array $src) use ($memberMap, $deviceMap) {
            if (!$src) { return null; }
            $m = $memberMap[(int)$src['counterparty_id']] ?? null;
            $dev = $deviceMap[(int)($src['source_device_id'] ?? 0)] ?? null;
            return [
                'counterparty_name' => $m['name'] ?? (string)($src['counterparty_name'] ?? ''),
                'counterparty_mobile' => $m['mobile'] ?? '',
                'source_type_text'  => FinanceDict::sourceTypeText((string)($src['source_type'] ?? '')),
                'source_no'         => (string)($src['source_no'] ?? ''),
                'device_model'      => (string)($dev['model'] ?? ''),
                'device_imei'       => (string)($dev['imei'] ?? ''),
                'asset_no'          => (string)($dev['asset_no'] ?? ''),
                'amount'            => round((float)($src['amount'] ?? 0), 2),
                'applied_amount'    => round((float)($l['applied_amount'] ?? 0), 2),
                'offset_part'       => round((float)($l['offset_part'] ?? 0), 2),
                'cash_part'         => round((float)($l['pay_part'] ?? 0), 2),
                'remark'            => (string)($src['remark'] ?? ''),
            ];
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

        Db::transaction(function () use ($plan, $summary, $anchorId, $anchorName, $options, $optAccountId, $optAccountName, $now, $no, $eventId, &$settlementId) {
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
        });

        // 现金部分关联资金账户：选了户头且有现金净额时，记一笔资金流水(我付=出账/我收=入账)，让现金真正进出账户。
        // record_cash=false: 现金已由外部(如回收打款)扣账, 此处只做核销/记录户头, 不再二次记流水(避免重复扣账)
        $recordCash = ($options['record_cash'] ?? true) !== false;
        $capitalAccountId = (int)($options['capital_account_id'] ?? 0);
        $cashAmount = round((float)($summary['cash_amount'] ?? 0), 2);
        if ($recordCash && $capitalAccountId > 0 && $cashAmount > 0) {
            try {
                (new ErpCapitalAccountService())->recordEntry([
                    'account_id'        => $capitalAccountId,
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
            } catch (\Throwable $e) {
                Log::warning('[erp_finance] 结算现金记资金流水失败: ' . $e->getMessage());
            }
        }

        // 发结算完成事件(故障隔离, 不回抛): 业务/ERP 订阅以更新各自展示
        $this->emitSettlementCompleted($settlementId, $no, $anchorId, $summary, $plan, $eventId, $now);

        return ['settlement_id' => $settlementId, 'settlement_no' => $no, 'summary' => $summary];
    }

    /**
     * 计算结算方案(折账分配)
     * @return array{summary:array, payable_alloc:array, receivable_alloc:array}
     */
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
