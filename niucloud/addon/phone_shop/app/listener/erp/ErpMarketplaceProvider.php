<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

/** phone_shop 对 ERP 声明自己具备的销售渠道能力。 */
final class ErpMarketplaceProvider
{
    public function handle(array $event = []): array
    {
        if ((int)($event['site_id'] ?? 0) <= 0) return ['providers' => []];
        return ['providers' => [[
            'key' => 'phone_shop',
            'name' => '二手机商城',
            'enabled' => 1,
            'supports_direct_listing' => 1,
            'supports_manual_mapping' => 1,
        ]]];
    }
}
