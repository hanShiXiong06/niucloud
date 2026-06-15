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
        $query = ErpCounterparty::where([['site_id', '=', $this->site_id]])->order('id desc');
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('counterparty_no|name|mobile|contact_name', '%' . $keyword . '%');
        }
        if (($where['role_type'] ?? '') !== '') {
            $query->where('role_type', '=', (string)$where['role_type']);
        }
        if (($where['status'] ?? '') !== '') {
            $query->where('status', '=', (int)$where['status']);
        }
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
        $cp = $this->find($id)->toArray();
        $cp['members'] = $this->getMembers($id);
        $cp['finance'] = $this->financeRecon($id);
        return $cp;
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

    /** 往主体里添加一名对接人(会员)。一个会员只能属于一个主体，已属其他主体则改归本主体。 */
    public function addMember(int $counterpartyId, int $memberId, string $relationRole = 'business', int $isFinanceContact = 0): void
    {
        $this->find($counterpartyId);
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
        $relation = ErpCounterpartyMember::where([
            ['site_id', '=', $this->site_id],
            ['counterparty_id', '=', $counterpartyId],
            ['member_id', '=', $memberId],
        ])->findOrEmpty();
        if (!$relation->isEmpty()) {
            $relation->save(['status' => 0, 'is_finance_contact' => 0, 'update_at' => time()]);
        }
    }

    /** 删除主体(解除所有对接人关系；财务以会员记账，不受影响) */
    public function delete(int $id): void
    {
        $cp = $this->find($id);
        Db::startTrans();
        try {
            ErpCounterpartyMember::where([['site_id', '=', $this->site_id], ['counterparty_id', '=', $id]])
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
        $counterparty = ErpCounterparty::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($counterparty->isEmpty()) {
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
