<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

/** 商城能力兼容层；phone_shop 正式提供同名 Hook 后可直接移入商城插件。 */
final class PhoneShopMarketplaceProvider
{
    public function handle(array $params = []): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        if (!PhoneShopBridge::available($siteId)) {
            return ['providers' => []];
        }
        return ['providers' => [[
            'key' => 'phone_shop',
            'name' => '二手机商城',
            'enabled' => 1,
            'supports_direct_listing' => 1,
        ]]];
    }
}
