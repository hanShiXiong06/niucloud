<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\support\MemberCardHookResult;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 会员卡只依赖库存能力契约，不直接依赖 ERP 表或类。
 */
final class MemberCardInventoryGateway extends BaseAdminService
{
    private ?array $capabilityResponse = null;

    public function capability(): array
    {
        if ($this->capabilityResponse !== null) return $this->capabilityResponse;
        $result = MemberCardHookResult::firstOrNull(event('ErpQuantityInventoryCapabilityRequested', [
            'event_name' => 'erp.quantity_inventory.capability_requested.v1',
            'event_version' => 1,
            'event_id' => 'member-card-inventory-capability:' . (int)$this->site_id . ':' . bin2hex(random_bytes(6)),
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'occurred_at' => time(),
        ]));
        $this->capabilityResponse = $result ?? [
            'available' => 0,
            'provider_name' => '未接入 ERP 数量库存',
            'warehouses' => [],
        ];
        return $this->capabilityResponse;
    }

    public function available(): bool
    {
        return (int)($this->capability()['available'] ?? 0) === 1;
    }

    public function consume(array $data): array
    {
        return MemberCardHookResult::first(event('ErpQuantityInventoryConsumeRequested', array_merge($data, [
            'event_name' => 'erp.quantity_inventory.consume_requested.v1',
            'event_version' => 1,
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'occurred_at' => (int)($data['occurred_at'] ?? time()),
        ])), '数量库存扣减');
    }

    public function adjust(array $data): array
    {
        if (!$this->available()) throw new CommonException('ERP数量库存未启用，不能调整耗材库存');
        return MemberCardHookResult::first(event('ErpQuantityInventoryAdjustRequested', array_merge($data, [
            'event_name' => 'erp.quantity_inventory.adjust_requested.v1',
            'event_version' => 1,
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'occurred_at' => (int)($data['occurred_at'] ?? time()),
        ])), '数量库存调整');
    }

    public function restore(array $data): array
    {
        return MemberCardHookResult::first(event('ErpQuantityInventoryRestoreRequested', array_merge($data, [
            'event_name' => 'erp.quantity_inventory.restore_requested.v1',
            'event_version' => 1,
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'occurred_at' => (int)($data['occurred_at'] ?? time()),
        ])), '数量库存返库');
    }
}
