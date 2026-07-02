<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPartyMember;
use addon\hsx_erp\app\service\admin\ErpLedgerService;
use app\model\member\Member;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Db;

class ErpCounterparty extends BaseAdminController
{
    private static bool $partyMemberTableEnsured = false;

    public function options()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['role_type', ''],
        ]);
        $keyword = trim((string)$params['keyword']);
        $query = ErpParty::where([['site_id', '=', $this->siteId()], ['status', '=', 1]]);
        if ($keyword !== '') {
            $query->whereLike('party_name|contact_name|contact_mobile|m_no', '%' . $keyword . '%');
        }
        if (!empty($params['role_type']) && (string)$params['role_type'] !== 'all') {
            $query->where('party_type', '=', $this->normalizeType((string)$params['role_type']));
        }
        $rows = $query->field('id,party_no,party_name,party_type,contact_name,contact_mobile,m_no')
            ->order('id desc')
            ->limit(20)
            ->select()
            ->toArray();

        return success(array_map(fn($row) => $this->formatRow($row), $rows));
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
        $type = $this->normalizeType((string)$params['role_type']);
        $result = $this->quickCreateContact($name, $mobile, $type, trim((string)$params['m_no']));
        return success($result);
    }

    public function memberOptions()
    {
        $this->ensurePartyMemberTable();
        $keyword = trim((string)$this->request->param('keyword', ''));
        $query = Member::where([['site_id', '=', $this->siteId()]]);
        if ($keyword !== '') {
            $query->whereLike('member_no|username|nickname|mobile', '%' . $keyword . '%');
        }
        $members = $query->field('member_id,member_no,username,nickname,mobile,status')
            ->order('member_id desc')
            ->limit(50)
            ->select()
            ->toArray();
        if (empty($members)) {
            return success([]);
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
                ->field('id,party_name,m_no')
                ->select()
                ->toArray();
            $partyMap = array_column($parties, null, 'id');
        }
        foreach ($members as &$member) {
            $relation = $relationMap[$member['member_id']] ?? [];
            $partyId = (int)($relation['party_id'] ?? 0);
            $party = $partyMap[$partyId] ?? [];
            $member['party_id'] = $partyId;
            $member['counterparty_id'] = $partyId;
            $member['party_name'] = (string)($party['party_name'] ?? '');
            $member['counterparty_name'] = (string)($party['party_name'] ?? '');
            $member['m_no'] = (string)($party['m_no'] ?? '');
        }
        unset($member);
        return success($members);
    }

    public function resolveContact()
    {
        $this->ensurePartyMemberTable();
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
                ''
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
                return success($this->formatContact($member->toArray(), $party->toArray(), false));
            }
        }
        $name = trim((string)($member->nickname ?: $member->username ?: ('对接人' . $memberId)));
        $party = $this->ensureParty($name, (string)$member->mobile, $this->normalizeType((string)$params['role_type']), '');
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

    private function quickCreateContact(string $name, string $mobile, string $type, string $mNo): array
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
        $party = $this->ensureParty($name, $mobile, $type, $mNo);
        $this->bindPartyMember((int)$party->id, (int)$member->member_id);
        return $this->formatContact($member->toArray(), $party->toArray(), true);
    }

    private function ensureParty(string $name, string $mobile, string $type, string $mNo): ErpParty
    {
        $party = ErpParty::where([['site_id', '=', $this->siteId()], ['party_name', '=', $name]])->findOrEmpty();
        if (!$party->isEmpty()) {
            return $party;
        }
        $now = time();
        return ErpParty::create([
            'site_id' => $this->siteId(),
            'party_no' => ErpLedgerService::makeNo('PT'),
            'party_name' => $name,
            'party_type' => $type,
            'contact_name' => $name,
            'contact_mobile' => $mobile,
            'm_no' => $mNo,
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function bindPartyMember(int $partyId, int $memberId): void
    {
        $this->ensurePartyMemberTable();
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

    private function ensurePartyMemberTable(): void
    {
        if (self::$partyMemberTableEnsured) {
            return;
        }
        self::$partyMemberTableEnsured = true;
        $table = (new ErpPartyMember())->getTable();
        Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
            `party_id` int NOT NULL DEFAULT 0 COMMENT '往来主体ID',
            `member_id` int NOT NULL DEFAULT 0 COMMENT '对接人会员ID',
            `relation_role` varchar(20) NOT NULL DEFAULT 'business' COMMENT 'owner/finance/business',
            `is_finance_contact` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否主要财务联系人',
            `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1有效/0失效',
            `remark` varchar(255) NOT NULL DEFAULT '',
            `create_at` int NOT NULL DEFAULT 0,
            `update_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_site_member` (`site_id`,`member_id`),
            KEY `idx_party` (`site_id`,`party_id`,`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-往来主体对接人'");
    }

    private function formatContact(array $member, array $party, bool $autoCreated): array
    {
        $memberName = (string)($member['nickname'] ?: $member['username'] ?: ('会员#' . ($member['member_id'] ?? 0)));
        return [
            'member_id' => (int)($member['member_id'] ?? 0),
            'member_name' => $memberName,
            'mobile' => (string)($member['mobile'] ?? ''),
            'party_id' => (int)($party['id'] ?? 0),
            'party_name' => (string)($party['party_name'] ?? ''),
            'counterparty_id' => (int)($party['id'] ?? 0),
            'counterparty_name' => (string)($party['party_name'] ?? ''),
            'm_no' => (string)($party['m_no'] ?? ''),
            'auto_created' => $autoCreated,
        ];
    }

    private function formatRow(array $row): array
    {
        $id = (int)$row['id'];
        return [
            'id' => $id,
            'member_id' => $id,
            'party_id' => $id,
            'party_no' => (string)($row['party_no'] ?? ''),
            'party_name' => (string)($row['party_name'] ?? ''),
            'name' => (string)($row['party_name'] ?? ''),
            'party_type' => (string)($row['party_type'] ?? 'customer'),
            'contact_name' => (string)($row['contact_name'] ?? ''),
            'contact_mobile' => (string)($row['contact_mobile'] ?? ''),
            'mobile' => (string)($row['contact_mobile'] ?? ''),
            'm_no' => (string)($row['m_no'] ?? ''),
        ];
    }
}
