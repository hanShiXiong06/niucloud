<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\ErpCounterpartyMember;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\model\FinanceReceivable;
use app\model\member\Member;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpCounterpartyAdminService extends BaseAdminService
{
    public function getPage(array $where = []): array
    {
        $query = ErpCounterparty::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('counterparty_no|name|mobile|contact_name', '%' . $keyword . '%');
        }
        if (($where['role_type'] ?? '') !== '') {
            $query->where('role_type', '=', (string)$where['role_type']);
        }
        if (($where['counterparty_type'] ?? '') !== '') {
            $query->where('counterparty_type', '=', (string)$where['counterparty_type']);
        }
        if (($where['status'] ?? '') !== '') {
            $query->where('status', '=', (int)$where['status']);
        }
        if (!empty($where['mobile'])) {
            $query->whereLike('mobile', '%' . trim((string)$where['mobile']) . '%');
        }
        // 排序(白名单字段)
        $sortMap = ['id' => 'id', 'counterparty_no' => 'counterparty_no', 'name' => 'name', 'create_at' => 'create_at'];
        $sf = $sortMap[(string)($where['sort_field'] ?? '')] ?? 'id';
        $so = strtolower((string)($where['sort_order'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $query->order($sf, $so);
        $page = $this->pageQuery($query);
        $counterpartyIds = array_column($page['data'] ?? [], 'id');
        if (!empty($counterpartyIds)) {
            $counts = ErpCounterpartyMember::where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
            ])->whereIn('counterparty_id', $counterpartyIds)
                ->field('counterparty_id,count(*) member_count')
                ->group('counterparty_id')->select()->toArray();
            $countMap = array_column($counts, 'member_count', 'counterparty_id');
            foreach ($page['data'] as &$item) {
                $item['member_count'] = (int)($countMap[$item['id']] ?? 0);
            }
            unset($item);
        }
        return $page;
    }

    public function options(array $where = []): array
    {
        $query = ErpCounterparty::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
        ]);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('counterparty_no|name|mobile', '%' . $keyword . '%');
        }
        return $query->field('id,counterparty_no,counterparty_type,role_type,name,mobile,contact_name')
            ->order('id desc')->limit(100)->select()->toArray();
    }

    public function save(array $data, int $id = 0): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请填写往来单位名称');
        }
        $counterpartyType = (string)($data['counterparty_type'] ?? 'individual');
        $roleType = (string)($data['role_type'] ?? 'supplier');
        if (!in_array($counterpartyType, ['individual', 'company'], true)) {
            throw new CommonException('往来单位类型不正确');
        }
        if (!in_array($roleType, ['supplier', 'customer', 'both', 'consignor'], true)) {
            throw new CommonException('往来角色不正确');
        }
        $mobile = trim((string)($data['mobile'] ?? ''));
        $now = time();
        $values = [
            'counterparty_type' => $counterpartyType,
            'role_type' => $roleType,
            'name' => $name,
            'mobile' => $mobile,
            'contact_name' => trim((string)($data['contact_name'] ?? '')),
            'tax_no' => trim((string)($data['tax_no'] ?? '')),
            'bank_name' => trim((string)($data['bank_name'] ?? '')),
            'bank_account' => trim((string)($data['bank_account'] ?? '')),
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'remark' => trim((string)($data['remark'] ?? '')),
            'update_at' => $now,
        ];
        Db::startTrans();
        try {
            if ($id > 0) {
                $counterparty = $this->find($id);
                $id = (int)$counterparty->id;   // 传进来可能是会员ID, 用真实主键
                $counterparty->save($values);
            } else {
                $counterparty = ErpCounterparty::create(array_merge($values, [
                    'site_id' => $this->site_id,
                    'counterparty_no' => $this->makeNo(),
                    'source_plugin' => 'hsx_erp',
                    'source_type' => 'manual',
                    'source_id' => random_int(100000000, 2147483647),
                    'create_at' => $now,
                ]));
                $id = (int)$counterparty->id;
            }
            if (array_key_exists('members', $data)) {
                $this->syncMembers($id, (array)$data['members'], $now);
            }
            Db::commit();
            return $id;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 主体详情(供财务中心抽屉): 主体信息 + 对接人(会员) + 该主体财务对账汇总。
     * 财务以 member_id 记账，故主体对账 = 旗下所有对接人(会员)的应收应付合计。
     */
    public function detail(int $id): array
    {
        $cp = $this->find($id);
        $realId = (int)$cp->id;   // 传进来可能是会员ID, 用解析出的真实主体主键查对接人/对账
        $data = $cp->toArray();
        $data['members'] = $this->getMembers($realId);
        $data['finance'] = $this->financeRecon($realId);
        return $data;
    }

    /** 主体财务对账：聚合旗下对接人(会员)的未结应付/应收/净额/可折账 */
    public function financeRecon(int $counterpartyId): array
    {
        $memberIds = ErpCounterpartyMember::where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['status', '=', 1],
        ])->column('member_id');
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        if (empty($memberIds)) {
            return ['payable' => 0, 'receivable' => 0, 'net' => 0, 'offsetable' => 0, 'member_count' => 0];
        }
        $open = ['pending', 'partial'];
        $pBase = function () use ($open, $memberIds) {
            return FinancePayable::where([['site_id', '=', $this->site_id], ['status', 'in', $open]])->whereIn('counterparty_id', $memberIds);
        };
        $rBase = function () use ($open, $memberIds) {
            return FinanceReceivable::where([['site_id', '=', $this->site_id], ['status', 'in', $open]])->whereIn('counterparty_id', $memberIds);
        };
        $payable = round((float)$pBase()->sum('amount') - (float)$pBase()->sum('settled_amount'), 2);
        $receivable = round((float)$rBase()->sum('amount') - (float)$rBase()->sum('settled_amount'), 2);
        return [
            'payable'      => $payable,
            'receivable'   => $receivable,
            'net'          => round($payable - $receivable, 2),
            'offsetable'   => round(min($payable, $receivable), 2),
            'member_count' => count($memberIds),
        ];
    }

    /**
     * 主体级往来流水明细（含已结清），每笔标注归属对接人（谁的账目）
     * 口径：财务表 counterparty_id 即对接人 member_id；主体下可能有多名对接人，统一汇总。
     * @param int $entityId 主体(往来单位)id；>0 则展开其下全部对接人
     * @param int $memberId 对接人 id；当 entityId<=0 时按此人单独查
     */
    public function counterpartyDealings(int $entityId, int $memberId = 0, int $start = 0, int $end = 0): array
    {
        if ($entityId > 0) {
            $memberIds = ErpCounterpartyMember::where([
                ['site_id', '=', $this->site_id],
                ['counterparty_id', '=', $entityId],
                ['status', '=', 1],
            ])->column('member_id');
        } elseif ($memberId > 0) {
            $memberIds = [$memberId];
        } else {
            $memberIds = [];
        }
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));

        $entity = $entityId > 0
            ? ErpCounterparty::where([['id', '=', $entityId]])->field('id,name')->findOrEmpty()->toArray()
            : [];

        $empty = [
            'entity_id'    => $entityId,
            'entity_name'  => (string)($entity['name'] ?? ''),
            'members'      => [],
            'record_count' => 0,
            'totals'       => ['payable_total' => 0, 'receivable_total' => 0, 'payable_outstanding' => 0, 'receivable_outstanding' => 0, 'net_outstanding' => 0],
            'records'      => [],
            'note'         => '未找到对接人，无法查询往来',
        ];
        if (empty($memberIds)) {
            return $empty;
        }

        // 对接人名片（谁的账目）
        $memberRows = Member::where([['site_id', '=', $this->site_id]])
            ->whereIn('member_id', $memberIds)
            ->field('member_id,username,nickname,mobile')->select()->toArray();
        $memberMap = [];
        $memberList = [];
        foreach ($memberRows as $m) {
            $name = (string)($m['nickname'] ?: $m['username'] ?: ('会员#' . $m['member_id']));
            $memberMap[(int)$m['member_id']] = ['name' => $name, 'mobile' => (string)($m['mobile'] ?? '')];
            $memberList[] = ['member_id' => (int)$m['member_id'], 'name' => $name, 'mobile' => (string)($m['mobile'] ?? '')];
        }

        $open = ['pending', 'partial'];
        $records = [];
        $totals = ['payable_total' => 0.0, 'receivable_total' => 0.0, 'payable_outstanding' => 0.0, 'receivable_outstanding' => 0.0];

        $payQ = FinancePayable::where([['site_id', '=', $this->site_id]])->whereIn('counterparty_id', $memberIds);
        $recQ = FinanceReceivable::where([['site_id', '=', $this->site_id]])->whereIn('counterparty_id', $memberIds);
        if ($start > 0) {
            $payQ->where('occurred_at', '>=', $start);
            $recQ->where('occurred_at', '>=', $start);
        }
        if ($end > 0) {
            $payQ->where('occurred_at', '<=', $end);
            $recQ->where('occurred_at', '<=', $end);
        }
        $payRows = $payQ->order('occurred_at desc')->limit(200)->select()->toArray();
        $recRows = $recQ->order('occurred_at desc')->limit(200)->select()->toArray();

        $build = function (array $rows, string $type, string $label) use (&$records, &$totals, $memberMap, $open) {
            foreach ($rows as $r) {
                $amount = (float)($r['amount'] ?? 0);
                $settled = (float)($r['settled_amount'] ?? 0);
                $outstanding = round($amount - $settled, 2);
                $owner = $memberMap[(int)($r['counterparty_id'] ?? 0)] ?? ['name' => '', 'mobile' => ''];
                $totals[$type . '_total'] += $amount;
                $totals[$type . '_outstanding'] += $outstanding;
                $status = (string)($r['status'] ?? '');
                $records[] = [
                    'direction'    => $label,
                    'owner'        => $owner['name'],
                    'owner_mobile' => $owner['mobile'],
                    'amount'       => round($amount, 2),
                    'settled'      => round($settled, 2),
                    'outstanding'  => $outstanding,
                    'status'       => in_array($status, $open, true) ? '未结清' : ($status === 'settled' ? '已结清' : ($status === 'void' ? '已作废' : $status)),
                    'source_no'    => (string)($r['source_no'] ?? ''),
                    'device_id'    => (int)($r['source_device_id'] ?? 0),
                    'remark'       => (string)($r['remark'] ?? ''),
                    'occurred_at'  => !empty($r['occurred_at']) ? date('Y-m-d H:i', (int)$r['occurred_at']) : '',
                ];
            }
        };
        $build($payRows, 'payable', '应付');
        $build($recRows, 'receivable', '应收');

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }
        $totals['net_outstanding'] = round($totals['payable_outstanding'] - $totals['receivable_outstanding'], 2);

        usort($records, fn($a, $b) => strcmp((string)$b['occurred_at'], (string)$a['occurred_at']));

        return [
            'entity_id'    => $entityId,
            'entity_name'  => (string)($entity['name'] ?? ''),
            'range'        => ['start' => $start > 0 ? date('Y-m-d', $start) : '不限', 'end' => $end > 0 ? date('Y-m-d', $end) : '不限'],
            'members'      => $memberList,
            'record_count' => count($records),
            'totals'       => $totals,
            'records'      => $records,
            'note'         => $records ? '' : '该时间段内该主体暂无往来记录',
        ];
    }

    /** 往主体里添加一名对接人(会员)。一个会员只能属于一个主体，已属其他主体则改归本主体。 */
    public function addMember(int $counterpartyId, int $memberId, string $relationRole = 'business', int $isFinanceContact = 0): void
    {
        $counterpartyId = (int)$this->find($counterpartyId)->id;   // 传进来可能是会员ID, 统一解析为真实主体主键
        if ($memberId <= 0) {
            throw new CommonException('请选择会员');
        }
        if (!in_array($relationRole, ['owner', 'finance', 'business'], true)) {
            $relationRole = 'business';
        }
        $exist = Member::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])->count();
        if (!$exist) {
            throw new CommonException('会员不存在或不属于当前站点');
        }
        $now = time();
        $values = [
            'counterparty_id'    => $counterpartyId,
            'relation_role'      => $relationRole,
            'is_finance_contact' => $isFinanceContact === 1 ? 1 : 0,
            'status'             => 1,
            'update_at'          => $now,
        ];
        $relation = ErpCounterpartyMember::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])->findOrEmpty();
        if (!$relation->isEmpty()) {
            $relation->save($values);
            return;
        }
        ErpCounterpartyMember::create(array_merge($values, [
            'site_id' => $this->site_id, 'member_id' => $memberId, 'remark' => '', 'create_at' => $now,
        ]));
    }

    /** 从主体移除一名对接人 */
    public function removeMember(int $counterpartyId, int $memberId): void
    {
        $counterpartyId = (int)$this->find($counterpartyId)->id;
        $relation = ErpCounterpartyMember::where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['member_id', '=', $memberId],
        ])->findOrEmpty();
        if (!$relation->isEmpty()) {
            $relation->save(['status' => 0, 'is_finance_contact' => 0, 'update_at' => time()]);
        }
    }

    /**
     * 出库/录入时"快速建档"：一步建对接人(会员)+主体并关联，返回 member_id 作为财务锚点。
     * 同手机会员复用、同名主体复用，避免重复建档。
     * @param array $data [name, mobile, entity_id?, entity_name?, counterparty_type?, role_type?]
     * @return array{member_id:int,member_name:string,mobile:string,counterparty_id:int,counterparty_name:string}
     */
    public function quickCreateContact(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $mobile = trim((string)($data['mobile'] ?? ''));
        if ($name === '') {
            throw new CommonException('请填写对接人姓名');
        }
        if ($mobile === '') {
            throw new CommonException('请填写对接人手机号(用于建档)');
        }
        // 已有同手机会员则复用，否则走标准会员建档
        $member = Member::where([['site_id', '=', $this->site_id], ['mobile', '=', $mobile]])->findOrEmpty();
        if (!$member->isEmpty()) {
            $memberId = (int)$member->member_id;
        } else {
            $svc = new \app\service\admin\member\MemberService();
            $no = $svc->getMemberNo();
            $memberId = (int)$svc->add([
                'mobile'         => $mobile,
                'member_no'      => $no,
                'init_member_no' => $no,
                'nickname'       => $name,
                'password'       => (string)random_int(100000, 999999),
            ]);
        }
        // 主体：指定 > 同名复用 > 以对接人名新建
        $entityId = (int)($data['entity_id'] ?? 0);
        $entityName = trim((string)($data['entity_name'] ?? ''));
        if ($entityId <= 0) {
            if ($entityName === '') {
                $entityName = $name;
            }
            $exist = ErpCounterparty::where([['site_id', '=', $this->site_id], ['name', '=', $entityName]])->findOrEmpty();
            if (!$exist->isEmpty()) {
                $entityId = (int)$exist->id;
            } else {
                $entityId = $this->save([
                    'name'              => $entityName,
                    'counterparty_type' => (string)($data['counterparty_type'] ?? 'individual'),
                    'role_type'         => (string)($data['role_type'] ?? 'customer'),
                    'mobile'            => $mobile,
                    'contact_name'      => $name,
                ]);
            }
        } else {
            $this->find($entityId);
        }
        $this->addMember($entityId, $memberId, 'business', 0);
        $cp = ErpCounterparty::where([['site_id', '=', $this->site_id], ['id', '=', $entityId]])->findOrEmpty();
        return [
            'member_id'         => $memberId,
            'member_name'       => $name,
            'mobile'            => $mobile,
            'counterparty_id'   => $entityId,
            'counterparty_name' => (string)($cp->name ?? $entityName),
        ];
    }

    /**
     * 以"人(会员)"为锚解析往来单位：
     *  - 传 member_id：取该会员；已有有效往来主体则直接返回；无主体则自动建一个(默认个人主体)并关联。
     *  - 不传 member_id：按 name+mobile 走 quickCreateContact 新建人+主体。
     * 返回统一含 member_id / counterparty_id，供各处复用(选人即得可记账的往来单位)。
     */
    public function resolveContact(array $data): array
    {
        $memberId = (int)($data['member_id'] ?? 0);
        if ($memberId <= 0) {
            return $this->quickCreateContact($data);
        }
        $member = Member::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])->findOrEmpty();
        if ($member->isEmpty()) {
            throw new CommonException('对接人不存在');
        }
        $name = trim((string)($member->nickname ?: $member->username ?: ''));
        $mobile = (string)$member->mobile;

        // 已有有效往来主体 → 直接返回
        $rel = ErpCounterpartyMember::where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $memberId],
            ['status', '=', 1],
        ])->order('is_finance_contact desc,id asc')->findOrEmpty();
        if (!$rel->isEmpty() && (int)$rel->counterparty_id > 0) {
            $cp = ErpCounterparty::where([['site_id', '=', $this->site_id], ['id', '=', (int)$rel->counterparty_id]])->findOrEmpty();
            if (!$cp->isEmpty()) {
                return [
                    'member_id'         => $memberId,
                    'member_name'       => $name,
                    'mobile'            => $mobile,
                    'counterparty_id'   => (int)$cp->id,
                    'counterparty_name' => (string)$cp->name,
                    'auto_created'      => false,
                ];
            }
        }

        // 无主体 → 自动建个人主体并关联
        $entityName = $name !== '' ? $name : ('对接人' . $memberId);
        $exist = ErpCounterparty::where([['site_id', '=', $this->site_id], ['name', '=', $entityName]])->findOrEmpty();
        if (!$exist->isEmpty()) {
            $entityId = (int)$exist->id;
        } else {
            $entityId = $this->save([
                'name'              => $entityName,
                'counterparty_type' => (string)($data['counterparty_type'] ?? 'individual'),
                'role_type'         => (string)($data['role_type'] ?? 'customer'),
                'mobile'            => $mobile,
                'contact_name'      => $name,
            ]);
        }
        $this->addMember($entityId, $memberId, 'business', 0);
        $cp = ErpCounterparty::where([['site_id', '=', $this->site_id], ['id', '=', $entityId]])->findOrEmpty();
        return [
            'member_id'         => $memberId,
            'member_name'       => $name,
            'mobile'            => $mobile,
            'counterparty_id'   => $entityId,
            'counterparty_name' => (string)($cp->name ?? $entityName),
            'auto_created'      => true,
        ];
    }

    /** 删除主体(解除所有对接人关系；财务以会员记账，不受影响) */
    public function delete(int $id): void
    {
        $cp = $this->find($id);
        $realId = (int)$cp->id;
        Db::startTrans();
        try {
            ErpCounterpartyMember::where([['site_id', '=', $this->site_id], ['counterparty_id', '=', $realId]])
                ->update(['status' => 0, 'is_finance_contact' => 0, 'update_at' => time()]);
            $cp->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function getMembers(int $counterpartyId): array
    {
        $this->find($counterpartyId);
        $relations = ErpCounterpartyMember::where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['status', '=', 1],
        ])->order('is_finance_contact desc,id asc')->select()->toArray();
        if (empty($relations)) {
            return [];
        }
        $members = Member::where([
            ['site_id', '=', $this->site_id],
        ])->whereIn('member_id', array_column($relations, 'member_id'))
            ->field('member_id,member_no,username,nickname,mobile,status')->select()->toArray();
        $memberMap = array_column($members, null, 'member_id');
        foreach ($relations as &$relation) {
            $relation['member'] = $memberMap[$relation['member_id']] ?? [];
        }
        unset($relation);
        return $relations;
    }

    public function memberOptions(string $keyword = ''): array
    {
        $query = Member::where([['site_id', '=', $this->site_id]]);
        if ($keyword !== '') {
            $query->whereLike('member_no|username|nickname|mobile', '%' . trim($keyword) . '%');
        }
        $members = $query->field('member_id,member_no,username,nickname,mobile,status')
            ->order('member_id desc')->limit(50)->select()->toArray();
        if (empty($members)) {
            return [];
        }
        $relations = ErpCounterpartyMember::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
        ])->whereIn('member_id', array_column($members, 'member_id'))->select()->toArray();
        $relationMap = array_column($relations, null, 'member_id');
        $counterpartyIds = array_values(array_unique(array_filter(array_column($relations, 'counterparty_id'))));
        $counterpartyMap = [];
        if (!empty($counterpartyIds)) {
            $counterparties = ErpCounterparty::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', $counterpartyIds)->field('id,name')->select()->toArray();
            $counterpartyMap = array_column($counterparties, 'name', 'id');
        }
        foreach ($members as &$member) {
            $relation = $relationMap[$member['member_id']] ?? [];
            $member['counterparty_id'] = (int)($relation['counterparty_id'] ?? 0);
            $member['counterparty_name'] = (string)($counterpartyMap[$member['counterparty_id']] ?? '');
        }
        unset($member);
        return $members;
    }

    private function find(int $id): ErpCounterparty
    {
        // 先按主键 id 查
        $counterparty = ErpCounterparty::where([['id', '=', $id]])->findOrEmpty();
        // 查不到则兼容: 传进来的可能是会员ID(source_id), 按 source 找其对应主体
        if ($counterparty->isEmpty()) {
            $counterparty = ErpCounterparty::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'member'],
                ['source_id', '=', $id],
            ])->order('id desc')->findOrEmpty();
        }
        if ($counterparty->isEmpty()) {
            throw new CommonException('往来单位不存在');
        }
        // 允许 site_id 为当前站点或 0(历史数据 site_id 未存对), 真·跨别的站点才拦
        $sid = (int)$counterparty->site_id;
        if ($sid !== 0 && $sid !== (int)$this->site_id) {
            throw new CommonException('往来单位不存在');
        }
        return $counterparty;
    }

    private function syncMembers(int $counterpartyId, array $members, int $now): void
    {
        $memberRows = [];
        foreach ($members as $item) {
            $memberId = (int)($item['member_id'] ?? 0);
            if ($memberId > 0) {
                $memberRows[$memberId] = [
                    'member_id' => $memberId,
                    'relation_role' => (string)($item['relation_role'] ?? 'business'),
                    'is_finance_contact' => (int)($item['is_finance_contact'] ?? 0) === 1 ? 1 : 0,
                ];
            }
        }
        if (count(array_filter(array_column($memberRows, 'is_finance_contact'))) > 1) {
            throw new CommonException('一个往来主体只能设置一名主要财务联系人');
        }
        if (!empty($memberRows)) {
            $existingMemberIds = Member::where([
                ['site_id', '=', $this->site_id],
            ])->whereIn('member_id', array_keys($memberRows))->column('member_id');
            if (count($existingMemberIds) !== count($memberRows)) {
                throw new CommonException('部分会员不存在或不属于当前站点');
            }
        }

        $currentRelations = ErpCounterpartyMember::where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['status', '=', 1],
        ])->select();
        foreach ($currentRelations as $relation) {
            if (!isset($memberRows[(int)$relation->member_id])) {
                $relation->save(['status' => 0, 'is_finance_contact' => 0, 'update_at' => $now]);
            }
        }

        foreach ($memberRows as $memberId => $item) {
            if (!in_array($item['relation_role'], ['owner', 'finance', 'business'], true)) {
                throw new CommonException('会员关系角色不正确');
            }
            $relation = ErpCounterpartyMember::where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $memberId],
            ])->findOrEmpty();
            $values = [
                'counterparty_id' => $counterpartyId,
                'relation_role' => $item['relation_role'],
                'is_finance_contact' => $item['is_finance_contact'],
                'status' => 1,
                'update_at' => $now,
            ];
            if (!$relation->isEmpty()) {
                $relation->save($values);
                continue;
            }
            ErpCounterpartyMember::create(array_merge($values, [
                'site_id' => $this->site_id,
                'member_id' => $memberId,
                'remark' => '',
                'create_at' => $now,
            ]));
        }
    }

    private function makeNo(): string
    {
        return 'CP' . date('YmdHis') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT)
            . random_int(100000, 999999);
    }
}
