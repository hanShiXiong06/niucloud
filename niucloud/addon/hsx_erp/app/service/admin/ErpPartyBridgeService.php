<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPartyMember;
use app\model\member\Member;
use core\exception\CommonException;

/** 把站点会员安全解析为 ERP 往来主体，供其它插件消费。 */
class ErpPartyBridgeService extends ErpExternalContractService
{
    public const EVENT_NAME = 'ErpPartyResolveRequested';
    public const CONTRACT_NAME = 'erp.party.resolve_requested.v1';
    public const CONTRACT_VERSION = 1;

    public function consume(array $event): array
    {
        $payload = $this->normalizePayload($event);
        return $this->consumeOnce($payload, self::EVENT_NAME, function (array $request): array {
            return $this->resolveMemberParty($request);
        });
    }

    protected function normalizePayload(array $event): array
    {
        $member = is_array($event['member'] ?? null) ? (array)$event['member'] : [];
        $payload = array_merge(
            $this->normalizeEnvelope($event, self::CONTRACT_NAME, self::CONTRACT_VERSION),
            [
                'event_name' => self::CONTRACT_NAME,
                'event_version' => self::CONTRACT_VERSION,
                'member_id' => max(0, (int)($member['member_id'] ?? $event['member_id'] ?? 0)),
                'role' => $this->normalizeRole((string)($event['role'] ?? 'sale_customer')),
                'remark' => mb_substr(trim((string)($event['remark'] ?? '')), 0, 255),
            ]
        );
        if ((int)$payload['member_id'] <= 0) throw new CommonException('往来主体解析请求缺少member_id');
        return $payload;
    }

    protected function resolveMemberParty(array $payload): array
    {
        $siteId = (int)$payload['site_id'];
        $memberId = (int)$payload['member_id'];
        $member = Member::where([
            ['site_id', '=', $siteId],
            ['member_id', '=', $memberId],
        ])->field('member_id,member_no,username,nickname,mobile')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('会员不存在或不属于当前站点');

        $memberData = $member->toArray();
        $mobile = mb_substr(trim((string)($memberData['mobile'] ?? '')), 0, 30);
        $displayName = $this->memberDisplayName($memberData);
        $relation = ErpPartyMember::where([
            ['site_id', '=', $siteId],
            ['member_id', '=', $memberId],
        ])->lock(true)->findOrEmpty();

        $party = null;
        if (!$relation->isEmpty() && (int)$relation->party_id > 0) {
            $party = ErpParty::where([
                ['site_id', '=', $siteId],
                ['id', '=', (int)$relation->party_id],
            ])->lock(true)->findOrEmpty();
            if ($party->isEmpty()) $party = null;
        }
        if ($party === null && $mobile !== '') {
            $found = ErpParty::where([
                ['site_id', '=', $siteId],
                ['contact_mobile', '=', $mobile],
            ])->order('status desc,id asc')->lock(true)->findOrEmpty();
            if (!$found->isEmpty()) $party = $found;
        }

        $now = time();
        $created = false;
        if ($party === null) {
            $party = ErpParty::create([
                'site_id' => $siteId,
                'party_no' => ErpLedgerService::makeNo('PT'),
                'party_name' => $displayName,
                'party_type' => 'customer',
                'role_flags' => (string)$payload['role'],
                'group_keys' => '',
                'contact_name' => $displayName,
                'contact_mobile' => $mobile,
                'm_no' => mb_substr(trim((string)($memberData['member_no'] ?? '')), 0, 64),
                'remark' => mb_substr('由' . (string)$payload['source_plugin'] . '通过会员关系自动创建', 0, 255),
                'status' => 1,
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $created = true;
        } else {
            $changes = [
                'role_flags' => $this->appendRole((string)$party->role_flags, (string)$payload['role']),
                'update_at' => $now,
            ];
            if (trim((string)$party->party_name) === '') $changes['party_name'] = $displayName;
            if (trim((string)$party->contact_name) === '') $changes['contact_name'] = $displayName;
            if (trim((string)$party->contact_mobile) === '' && $mobile !== '') $changes['contact_mobile'] = $mobile;
            if (trim((string)$party->m_no) === '' && trim((string)($memberData['member_no'] ?? '')) !== '') {
                $changes['m_no'] = mb_substr(trim((string)$memberData['member_no']), 0, 64);
            }
            $party->save($changes);
        }

        if ($relation->isEmpty()) {
            ErpPartyMember::create([
                'site_id' => $siteId,
                'party_id' => (int)$party->id,
                'member_id' => $memberId,
                'relation_role' => 'business',
                'is_finance_contact' => 1,
                'status' => 1,
                'remark' => mb_substr((string)$payload['remark'], 0, 255),
                'create_at' => $now,
                'update_at' => $now,
            ]);
        } else {
            $relation->save([
                'party_id' => (int)$party->id,
                'status' => 1,
                'update_at' => $now,
            ]);
        }

        return [
            'party_id' => (int)$party->id,
            'party_no' => (string)$party->party_no,
            'party_name' => (string)$party->party_name,
            'member_id' => $memberId,
            'member_name' => $displayName,
            'mobile' => $mobile,
            'created' => $created,
        ];
    }

    private function memberDisplayName(array $member): string
    {
        foreach (['nickname', 'username', 'mobile', 'member_no'] as $field) {
            $value = trim((string)($member[$field] ?? ''));
            if ($value !== '') return mb_substr($value, 0, 100);
        }
        return '会员' . (int)($member['member_id'] ?? 0);
    }

    private function normalizeRole(string $role): string
    {
        $role = $this->stableKey($role, 40);
        $allowed = ['purchase_supplier', 'sale_customer', 'recycle_customer', 'refurbish_provider'];
        return in_array($role, $allowed, true) ? $role : 'sale_customer';
    }

    private function appendRole(string $current, string $role): string
    {
        $roles = array_values(array_unique(array_filter(array_map('trim', explode(',', $current)))));
        if (!in_array($role, $roles, true)) $roles[] = $role;
        return mb_substr(implode(',', $roles), 0, 255);
    }
}
