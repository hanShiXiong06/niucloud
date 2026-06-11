<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\ErpCounterpartyMember;
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
