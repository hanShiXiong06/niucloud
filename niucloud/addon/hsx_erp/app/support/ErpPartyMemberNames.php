<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use addon\hsx_erp\app\model\ErpPartyMember;
use app\model\member\Member;

/** 为业务快照补充往来主体当前关联的会员名称。 */
final class ErpPartyMemberNames
{
    public static function append(int $siteId, array &$rows, string $partyIdField = 'party_id'): void
    {
        if ($siteId <= 0 || $rows === []) return;

        foreach ($rows as &$row) {
            $row['member_name'] = (string)($row['member_name'] ?? '');
            $row['member_names'] = is_array($row['member_names'] ?? null) ? $row['member_names'] : [];
        }
        unset($row);

        $partyIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int)($row[$partyIdField] ?? 0),
            $rows
        ))));
        if ($partyIds === []) return;

        $relations = ErpPartyMember::where([
            ['site_id', '=', $siteId],
            ['status', '=', 1],
        ])->whereIn('party_id', $partyIds)
            ->order('id asc')
            ->field('party_id,member_id')
            ->select()
            ->toArray();
        if ($relations === []) return;

        $memberIds = array_values(array_unique(array_filter(array_map(
            static fn(array $relation): int => (int)($relation['member_id'] ?? 0),
            $relations
        ))));
        if ($memberIds === []) return;

        $members = Member::where([['site_id', '=', $siteId]])
            ->whereIn('member_id', $memberIds)
            ->field('member_id,nickname,username')
            ->select()
            ->toArray();
        $memberMap = [];
        foreach ($members as $member) {
            $name = trim((string)($member['nickname'] ?: $member['username'] ?: ''));
            if ($name !== '') $memberMap[(int)$member['member_id']] = $name;
        }

        $namesByParty = [];
        foreach ($relations as $relation) {
            $partyId = (int)($relation['party_id'] ?? 0);
            $name = $memberMap[(int)($relation['member_id'] ?? 0)] ?? '';
            if ($partyId <= 0 || $name === '') continue;
            $namesByParty[$partyId] ??= [];
            if (!in_array($name, $namesByParty[$partyId], true)) $namesByParty[$partyId][] = $name;
        }

        foreach ($rows as &$row) {
            $names = $namesByParty[(int)($row[$partyIdField] ?? 0)] ?? [];
            if ($names === []) continue;
            $row['member_name'] = (string)$names[0];
            $row['member_names'] = $names;
        }
        unset($row);
    }
}
