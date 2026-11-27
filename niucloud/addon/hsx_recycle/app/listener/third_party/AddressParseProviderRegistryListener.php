<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\third_party;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\address\provider\TencentCloudMarketAddressProvider;

class AddressParseProviderRegistryListener
{
    public function handle(array $params = []): array
    {
        return [
            'providers' => [
                ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS => TencentCloudMarketAddressProvider::class,
            ],
        ];
    }
}
