<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\listener;

use addon\hsx_member_card\app\service\admin\MemberCardFinanceSyncService;

final class ErpDomainEventListener
{
    public function handle(array $event): array
    {
        if ((string)($event['event_name'] ?? '') !== 'erp.settlement.completed.v1') {
            return ['consumer' => 'hsx_member_card', 'status' => 'skipped', 'reason' => 'unsupported_event'];
        }
        return (new MemberCardFinanceSyncService())->consume($event);
    }
}
