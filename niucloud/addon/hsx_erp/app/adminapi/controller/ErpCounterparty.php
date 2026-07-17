<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPartyMember;
use addon\hsx_erp\app\service\admin\ErpCustomerCreditService;
use addon\hsx_erp\app\service\admin\ErpLedgerService;
use addon\hsx_erp\app\support\ErpPartyMemberNames;
use app\model\member\Member;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Db;

class ErpCounterparty extends BaseAdminController
{

    public function options()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['role_type', ''],
            ['page', 1],
            ['limit', 20],
            ['paginate', 0],
        ]);
        $keyword = trim((string)$params['keyword']);
        $query = ErpParty::where([['site_id', '=', $this->siteId()], ['status', '=', 1]]);
        if ($keyword !== '') {
            $memberPartyIds = ctype_digit($keyword)
                ? ErpPartyMember::where([
                    ['site_id', '=', $this->siteId()],
                    ['member_id', '=', (int)$keyword],
                    ['status', '=', 1],
                ])->column('party_id')
                : [];
            $query->where(function ($subQuery) use ($keyword, $memberPartyIds) {
                $subQuery->whereLike('party_name|contact_name|contact_mobile|m_no', '%' . $keyword . '%');
                if ($memberPartyIds !== []) $subQuery->whereOr('id', 'in', $memberPartyIds);
            });
        }
        if (!empty($params['role_type']) && (string)$params['role_type'] !== 'all') {
            $role = $this->normalizeRole((string)$params['role_type']);
            $query->whereRaw("FIND_IN_SET('" . addslashes($role) . "', role_flags)");
        }
        $page = max(1, (int)$params['page']);
        $limit = max(10, min(50, (int)$params['limit']));
        $query->field('id,party_no,party_name,party_type,role_flags,group_keys,contact_name,contact_mobile,m_no,credit_policy,credit_limit,credit_remark,credit_update_uid,credit_update_name,credit_update_at')->order('id desc');
        if ((int)$params['paginate'] !== 1) {
            $rows = $query->limit(50)->select()->toArray();
            ErpPartyMemberNames::append($this->siteId(), $rows, 'id');
            $profiles = (new ErpCustomerCreditService())->profiles(array_column($rows, 'id'));
            return success(array_map(fn($row) => $this->formatRow($row, $profiles[(int)$row['id']] ?? []), $rows));
        }
        $result = $query->paginate(['list_rows' => $limit, 'page' => $page])->toArray();
        $rows = (array)($result['data'] ?? []);
        ErpPartyMemberNames::append($this->siteId(), $rows, 'id');
        $profiles = (new ErpCustomerCreditService())->profiles(array_column($rows, 'id'));
        $result['data'] = array_map(fn($row) => $this->formatRow($row, $profiles[(int)$row['id']] ?? []), $rows);

        return success($result);
    }

    public function quickContact()
    {
        $params = $this->request->params([
            ['name', ''],
            ['keyword', ''],
            ['mobile', ''],
            ['m_no', ''],
            ['role_type', 'customer'],
        ]);
        $name = trim((string)($params['name'] ?: $params['keyword']));
        if ($name === '') {
            throw new CommonException('请填写往来单位名称');
        }
        $mobile = trim((string)$params['mobile']);
        if ($mobile === '') {
            throw new CommonException('请填写手机号');
        }
        $requestedRole = (string)$params['role_type'];
        $type = $this->normalizeType($requestedRole);
        $result = $this->quickCreateContact($name, $mobile, $type, trim((string)$params['m_no']), $this->normalizeRole($requestedRole));
        return success($result);
    }

    /** 新增不需要登录账号的 ERP 往来主体。 */
    public function quickParty()
    {
        $params = $this->request->params([
            ['name', ''], ['mobile', ''], ['m_no', ''], ['role_type', 'customer'], ['group_keys', []],
        ]);
        $name = trim((string)$params['name']);
        if ($name === '') throw new CommonException('请填写主体名称');
        $party = $this->ensureParty($name, trim((string)$params['mobile']), $this->normalizeType((string)$params['role_type']), trim((string)$params['m_no']), $this->normalizeRole((string)$params['role_type']));
        $groups = array_values(array_unique(array_filter(array_map('trim', (array)$params['group_keys']))));
        if ($groups !== []) $party->save(['group_keys' => implode(',', $groups), 'update_at' => time()]);
        return success($this->formatRow($party->toArray()));
    }

    public function updateParty(int $id)
    {
        $party = ErpParty::where([['site_id', '=', $this->siteId()], ['id', '=', $id]])->findOrEmpty();
        if ($party->isEmpty()) throw new CommonException('往来主体不存在');
        $params = $this->request->params([
            ['party_name', ''], ['contact_name', ''], ['contact_mobile', ''], ['m_no', ''], ['role_flags', []], ['group_keys', []],
        ]);
        $name = trim((string)$params['party_name']);
        if ($name === '') throw new CommonException('主体名称不能为空');
        $allowedRoles = ['purchase_supplier', 'sale_customer', 'recycle_customer', 'refurbish_provider', 'other'];
        $roles = array_values(array_unique(array_intersect($allowedRoles, array_map('trim', (array)$params['role_flags']))));
        if ($roles === []) throw new CommonException('至少选择一个主体身份');
        $groups = array_values(array_unique(array_filter(array_map('trim', (array)$params['group_keys']))));
        $party->save([
            'party_name' => $name, 'contact_name' => trim((string)$params['contact_name']), 'contact_mobile' => trim((string)$params['contact_mobile']),
            'm_no' => trim((string)$params['m_no']), 'role_flags' => implode(',', $roles), 'group_keys' => implode(',', $groups), 'update_at' => time(),
        ]);
        return success($this->formatRow($party->toArray()));
    }

    public function credit(int $id)
    {
        return success((new ErpCustomerCreditService())->profile($id));
    }

    public function updateCredit(int $id)
    {
        $params = $this->request->params([
            ['credit_policy', 'inherit'], ['credit_limit', 0], ['credit_remark', ''],
        ]);
        return success((new ErpCustomerCreditService())->updatePolicy($id, $params));
    }

    public function memberOptions()
    {
        $keyword = trim((string)$this->request->param('keyword', ''));
        $page = max(1, (int)$this->request->param('page', 1));
        $limit = max(10, min(50, (int)$this->request->param('limit', 30)));
        $paginate = (int)$this->request->param('paginate', 0) === 1;
        $roleFilter = trim((string)$this->request->param('role_filter', ''));
        $query = Member::where([['site_id', '=', $this->siteId()]]);
        if ($roleFilter !== '' && $roleFilter !== 'all') {
            $role = $this->normalizeRole($roleFilter);
            $partyIds = ErpParty::where([['site_id', '=', $this->siteId()], ['status', '=', 1]])
                ->whereRaw("FIND_IN_SET('" . addslashes($role) . "', role_flags)")
                ->column('id');
            $memberIds = $partyIds === [] ? [] : ErpPartyMember::where([['site_id', '=', $this->siteId()], ['status', '=', 1]])->whereIn('party_id', $partyIds)->column('member_id');
            if ($memberIds === []) return success($paginate ? ['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0] : []);
            $query->whereIn('member_id', $memberIds);
        }
        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereLike('member_no|username|nickname|mobile', '%' . $keyword . '%');
                if (ctype_digit($keyword)) $subQuery->whereOr('member_id', '=', (int)$keyword);
            });
        }
        $baseQuery = $query->field('member_id,member_no,username,nickname,mobile,status')->order('member_id desc');
        $pageResult = $paginate ? $baseQuery->paginate(['list_rows' => $limit, 'page' => $page])->toArray() : null;
        $members = $paginate ? (array)($pageResult['data'] ?? []) : $baseQuery->limit(50)->select()->toArray();
        if (empty($members)) {
            return success($paginate ? array_merge((array)$pageResult, ['data' => []]) : []);
        }
        $relations = ErpPartyMember::where([
            ['site_id', '=', $this->siteId()],
            ['status', '=', 1],
        ])->whereIn('member_id', array_column($members, 'member_id'))->select()->toArray();
        $relationMap = array_column($relations, null, 'member_id');
        $partyIds = array_values(array_unique(array_filter(array_column($relations, 'party_id'))));
        $partyMap = [];
        if (!empty($partyIds)) {
            $parties = ErpParty::where([['site_id', '=', $this->siteId()]])
                ->whereIn('id', $partyIds)
                ->field('id,party_name,m_no,role_flags,group_keys,contact_name,contact_mobile,credit_policy,credit_limit,credit_remark,credit_update_uid,credit_update_name,credit_update_at')
                ->select()
                ->toArray();
            $partyMap = array_column($parties, null, 'id');
        }
        $creditProfiles = (new ErpCustomerCreditService())->profiles($partyIds);
        foreach ($members as &$member) {
            $relation = $relationMap[$member['member_id']] ?? [];
            $partyId = (int)($relation['party_id'] ?? 0);
            $party = $partyMap[$partyId] ?? [];
            $member['party_id'] = $partyId;
            $member['counterparty_id'] = $partyId;
            $member['party_name'] = (string)($party['party_name'] ?? '');
            $member['counterparty_name'] = (string)($party['party_name'] ?? '');
            $member['m_no'] = (string)($party['m_no'] ?? '');
            $member['role_flags'] = $this->csvValues((string)($party['role_flags'] ?? ''));
            $member['group_keys'] = $this->csvValues((string)($party['group_keys'] ?? ''));
            $member['credit_profile'] = $creditProfiles[$partyId] ?? null;
        }
        unset($member);
        if (!$paginate) return success($members);
        $pageResult['data'] = $members;
        return success($pageResult);
    }

    public function resolveContact()
    {
        $params = $this->request->params([
            ['member_id', 0],
            ['name', ''],
            ['mobile', ''],
            ['role_type', 'customer'],
        ]);
        $memberId = (int)$params['member_id'];
        if ($memberId <= 0) {
            return success($this->quickCreateContact(
                trim((string)$params['name']),
                trim((string)$params['mobile']),
                $this->normalizeType((string)$params['role_type']),
                '',
                $this->normalizeRole((string)$params['role_type'])
            ));
        }
        $member = Member::where([['site_id', '=', $this->siteId()], ['member_id', '=', $memberId]])->findOrEmpty();
        if ($member->isEmpty()) {
            throw new CommonException('对接人不存在');
        }
        $relation = ErpPartyMember::where([
            ['site_id', '=', $this->siteId()],
            ['member_id', '=', $memberId],
            ['status', '=', 1],
        ])->findOrEmpty();
        if (!$relation->isEmpty()) {
            $party = ErpParty::where([['site_id', '=', $this->siteId()], ['id', '=', (int)$relation->party_id]])->findOrEmpty();
            if (!$party->isEmpty()) {
                $this->appendPartyRole($party, $this->normalizeRole((string)$params['role_type']));
                return success($this->formatContact($member->toArray(), $party->toArray(), false));
            }
        }
        $name = trim((string)($member->nickname ?: $member->username ?: ('对接人' . $memberId)));
        $party = $this->ensureParty($name, (string)$member->mobile, $this->normalizeType((string)$params['role_type']), '', $this->normalizeRole((string)$params['role_type']));
        $this->bindPartyMember((int)$party->id, $memberId);
        return success($this->formatContact($member->toArray(), $party->toArray(), true));
    }

    private function siteId(): int
    {
        $siteId = (int)$this->request->siteId();
        if ($siteId <= 0) {
            throw new CommonException('未获取到站点ID，请刷新后台或重新选择站点');
        }
        return $siteId;
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'supplier', 'channel', 'other' => $type,
            'all' => 'other',
            default => 'customer',
        };
    }

    private function normalizeRole(string $type): string
    {
        return match ($type) {
            'supplier', 'purchase_supplier' => 'purchase_supplier',
            'refurbish_provider' => 'refurbish_provider',
            'recycle_customer' => 'recycle_customer',
            'channel' => 'sale_customer',
            'other' => 'other',
            default => 'sale_customer',
        };
    }

    private function quickCreateContact(string $name, string $mobile, string $type, string $mNo, string $role = ''): array
    {
        if ($name === '') {
            throw new CommonException('请填写对接人姓名');
        }
        if ($mobile === '') {
            throw new CommonException('请填写手机号');
        }
        $member = Member::where([['site_id', '=', $this->siteId()], ['mobile', '=', $mobile]])->findOrEmpty();
        if ($member->isEmpty()) {
            $service = new \app\service\admin\member\MemberService();
            $memberNo = $service->getMemberNo();
            $memberId = (int)$service->add([
                'mobile' => $mobile,
                'member_no' => $memberNo,
                'init_member_no' => $memberNo,
                'nickname' => $name,
                'password' => (string)random_int(100000, 999999),
            ]);
            $member = Member::where([['site_id', '=', $this->siteId()], ['member_id', '=', $memberId]])->findOrEmpty();
        }
        $party = $this->ensureParty($name, $mobile, $type, $mNo, $role !== '' ? $role : $this->normalizeRole($type));
        $this->bindPartyMember((int)$party->id, (int)$member->member_id);
        return $this->formatContact($member->toArray(), $party->toArray(), true);
    }

    private function ensureParty(string $name, string $mobile, string $type, string $mNo, string $role = ''): ErpParty
    {
        $party = ErpParty::where([['site_id', '=', $this->siteId()], ['party_name', '=', $name]])->findOrEmpty();
        if (!$party->isEmpty()) {
            $this->appendPartyRole($party, $role !== '' ? $role : $this->normalizeRole($type));
            return $party;
        }
        $now = time();
        return ErpParty::create([
            'site_id' => $this->siteId(),
            'party_no' => ErpLedgerService::makeNo('PT'),
            'party_name' => $name,
            'party_type' => $type,
            'role_flags' => $role !== '' ? $role : $this->normalizeRole($type),
            'group_keys' => '',
            'contact_name' => $name,
            'contact_mobile' => $mobile,
            'm_no' => $mNo,
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function appendPartyRole(ErpParty $party, string $role): void
    {
        if ($role === '') return;
        $roles = $this->csvValues((string)$party->role_flags);
        if (in_array($role, $roles, true)) return;
        $roles[] = $role;
        $party->save(['role_flags' => implode(',', $roles), 'update_at' => time()]);
    }

    private function csvValues(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    private function bindPartyMember(int $partyId, int $memberId): void
    {
        $now = time();
        $relation = ErpPartyMember::where([['site_id', '=', $this->siteId()], ['member_id', '=', $memberId]])->findOrEmpty();
        $values = [
            'party_id' => $partyId,
            'relation_role' => 'business',
            'status' => 1,
            'update_at' => $now,
        ];
        if (!$relation->isEmpty()) {
            $relation->save($values);
            return;
        }
        ErpPartyMember::create(array_merge($values, [
            'site_id' => $this->siteId(),
            'member_id' => $memberId,
            'create_at' => $now,
        ]));
    }

    private function formatContact(array $member, array $party, bool $autoCreated): array
    {
        $memberName = (string)($member['nickname'] ?: $member['username'] ?: ('会员#' . ($member['member_id'] ?? 0)));
        $partyId = (int)($party['id'] ?? 0);
        return [
            'member_id' => (int)($member['member_id'] ?? 0),
            'member_name' => $memberName,
            'mobile' => (string)($member['mobile'] ?? ''),
            'party_id' => $partyId,
            'party_name' => (string)($party['party_name'] ?? ''),
            'counterparty_id' => (int)($party['id'] ?? 0),
            'counterparty_name' => (string)($party['party_name'] ?? ''),
            'm_no' => (string)($party['m_no'] ?? ''),
            'role_flags' => $this->csvValues((string)($party['role_flags'] ?? '')),
            'group_keys' => $this->csvValues((string)($party['group_keys'] ?? '')),
            'credit_profile' => $partyId > 0 ? (new ErpCustomerCreditService())->profile($partyId) : null,
            'auto_created' => $autoCreated,
        ];
    }

    private function formatRow(array $row, array $creditProfile = []): array
    {
        $id = (int)$row['id'];
        if ($creditProfile === [] && $id > 0) $creditProfile = (new ErpCustomerCreditService())->profile($id);
        return [
            'id' => $id,
            'member_id' => $id,
            'party_id' => $id,
            'party_no' => (string)($row['party_no'] ?? ''),
            'party_name' => (string)($row['party_name'] ?? ''),
            'member_name' => (string)($row['member_name'] ?? ''),
            'member_names' => (array)($row['member_names'] ?? []),
            'name' => (string)($row['party_name'] ?? ''),
            'party_type' => (string)($row['party_type'] ?? 'customer'),
            'role_flags' => $this->csvValues((string)($row['role_flags'] ?? '')),
            'group_keys' => $this->csvValues((string)($row['group_keys'] ?? '')),
            'contact_name' => (string)($row['contact_name'] ?? ''),
            'contact_mobile' => (string)($row['contact_mobile'] ?? ''),
            'mobile' => (string)($row['contact_mobile'] ?? ''),
            'm_no' => (string)($row['m_no'] ?? ''),
            'credit_policy' => (string)($row['credit_policy'] ?? 'inherit'),
            'credit_limit' => (float)($row['credit_limit'] ?? 0),
            'credit_remark' => (string)($row['credit_remark'] ?? ''),
            'credit_profile' => $creditProfile,
        ];
    }
}
