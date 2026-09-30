<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\order;

use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\service\core\order\OfflineOrderSchemaService;

class OfflineOrderTaskValidate
{
    public function handle(array $event = []): array
    {
        if ((string)($event['source_plugin'] ?? '') !== 'phone_shop'
            || (string)($event['source_type'] ?? '') !== 'offline_order') {
            return ['valid' => true];
        }
        OfflineOrderSchemaService::ensure();
        $status = (string)OrderOfflineRecord::where([
            ['site_id', '=', (int)($event['site_id'] ?? 0)],
            ['order_id', '=', (int)($event['source_id'] ?? 0)],
        ])->value('status');
        return in_array($status, ['pending', 'contacted', 'voucher_submitted', 'voucher_rejected'], true)
            ? ['valid' => true]
            : ['valid' => false, 'reason' => '线下订单已处理，无需继续提醒'];
    }
}
