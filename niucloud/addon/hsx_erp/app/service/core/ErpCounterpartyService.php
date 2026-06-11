<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\ErpCounterpartyMember;
use core\exception\CommonException;

class ErpCounterpartyService
{
    public function resolve(int $siteId, array $data, int $now): ErpCounterparty
    {
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $counterparty = ErpCounterparty::where([
                ['site_id', '=', $siteId],
                ['id', '=', $id],
                ['status', '=', 1],
            ])->findOrEmpty();
            if ($counterparty->isEmpty()) {
                throw new CommonException('往来单位不存在或已停用');
            }
            return $counterparty;
        }

        $sourcePlugin = trim((string)($data['source_plugin'] ?? ''));
        $sourceType = trim((string)($data['source_type'] ?? ''));
        $sourceId = (int)($data['source_id'] ?? 0);
        if ($sourcePlugin === '' || $sourceType === '' || $sourceId <= 0) {
            throw new CommonException('往来单位来源信息不完整');
        }

        if ($sourcePlugin === 'niucloud' && $sourceType === 'member') {
            $relation = ErpCounterpartyMember::where([
                ['site_id', '=', $siteId],
                ['member_id', '=', $sourceId],
                ['status', '=', 1],
            ])->findOrEmpty();
            if (!$relation->isEmpty()) {
                $counterparty = ErpCounterparty::where([
                    ['site_id', '=', $siteId],
                    ['id', '=', (int)$relation->counterparty_id],
                    ['status', '=', 1],
                ])->findOrEmpty();
                if (!$counterparty->isEmpty()) {
                    $incomingRole = (string)($data['role_type'] ?? '');
                    $mergedRole = $this->mergeRole((string)$counterparty->role_type, $incomingRole);
                    if ($mergedRole !== (string)$counterparty->role_type) {
                        $counterparty->save(['role_type' => $mergedRole, 'update_at' => $now]);
                    }
                    return $counterparty;
                }
            }
        }

        $counterparty = ErpCounterparty::where([
            ['site_id', '=', $siteId],
            ['source_plugin', '=', $sourcePlugin],
            ['source_type', '=', $sourceType],
            ['source_id', '=', $sourceId],
        ])->findOrEmpty();
        $incomingName = trim((string)($data['name'] ?? ''));
        $incomingMobile = trim((string)($data['mobile'] ?? ''));
        $name = $incomingName !== '' ? $incomingName : '往来单位#' . $sourceId;
        $values = [
            'counterparty_type' => (string)($data['counterparty_type'] ?? 'individual'),
            'role_type' => (string)($data['role_type'] ?? 'supplier'),
            'name' => $name,
            'mobile' => $incomingMobile,
            'contact_name' => trim((string)($data['contact_name'] ?? $name)),
            'status' => 1,
            'update_at' => $now,
        ];
        if (!$counterparty->isEmpty()) {
            if ($incomingName === '') {
                $values['name'] = (string)$counterparty->name;
                $values['contact_name'] = (string)$counterparty->contact_name;
            }
            if ($incomingMobile === '') {
                $values['mobile'] = (string)$counterparty->mobile;
            }
            $values['role_type'] = $this->mergeRole(
                (string)$counterparty->role_type,
                (string)$values['role_type']
            );
            $counterparty->save($values);
            if ($sourcePlugin === 'niucloud' && $sourceType === 'member') {
                $this->bindMember($siteId, (int)$counterparty->id, $sourceId, $now);
            }
            return $counterparty;
        }

        try {
            $created = ErpCounterparty::create(array_merge($values, [
                'site_id' => $siteId,
                'counterparty_no' => $this->makeNo($siteId),
                'source_plugin' => $sourcePlugin,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'create_at' => $now,
            ]));
            if ($sourcePlugin === 'niucloud' && $sourceType === 'member') {
                $this->bindMember($siteId, (int)$created->id, $sourceId, $now);
            }
            return $created;
        } catch (\Throwable $e) {
            $existing = ErpCounterparty::where([
                ['site_id', '=', $siteId],
                ['source_plugin', '=', $sourcePlugin],
                ['source_type', '=', $sourceType],
                ['source_id', '=', $sourceId],
            ])->findOrEmpty();
            if (!$existing->isEmpty()) {
                if ($sourcePlugin === 'niucloud' && $sourceType === 'member') {
                    $this->bindMember($siteId, (int)$existing->id, $sourceId, $now);
                }
                return $existing;
            }
            throw $e;
        }
    }

    private function bindMember(int $siteId, int $counterpartyId, int $memberId, int $now): void
    {
        $relation = ErpCounterpartyMember::where([
            ['site_id', '=', $siteId],
            ['member_id', '=', $memberId],
        ])->findOrEmpty();
        $values = [
            'counterparty_id' => $counterpartyId,
            'relation_role' => 'business',
            'status' => 1,
            'update_at' => $now,
        ];
        if (!$relation->isEmpty()) {
            $relation->save($values);
            return;
        }
        ErpCounterpartyMember::create(array_merge($values, [
            'site_id' => $siteId,
            'member_id' => $memberId,
            'is_finance_contact' => 0,
            'remark' => '',
            'create_at' => $now,
        ]));
    }

    private function makeNo(int $siteId): string
    {
        return 'CP' . date('YmdHis') . str_pad((string)$siteId, 3, '0', STR_PAD_LEFT)
            . random_int(100000, 999999);
    }

    private function mergeRole(string $current, string $incoming): string
    {
        if ($current === $incoming || $incoming === '') {
            return $current;
        }
        if ($current === 'both' || $incoming === 'both') {
            return 'both';
        }
        if (in_array($current, ['supplier', 'consignor'], true) && $incoming === 'customer') {
            return 'both';
        }
        if ($current === 'customer' && in_array($incoming, ['supplier', 'consignor'], true)) {
            return 'both';
        }
        return $incoming;
    }
}
