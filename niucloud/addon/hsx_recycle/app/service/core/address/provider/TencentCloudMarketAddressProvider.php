<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\address\provider;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\address\contract\AddressParseProviderInterface;
use addon\hsx_recycle\app\service\core\third_party\CoreThirdPartyService;

/** 保留现网腾讯云配置、签名和调用日志的地址解析适配器。 */
class TencentCloudMarketAddressProvider implements AddressParseProviderInterface
{
    public function key(): string
    {
        return ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS;
    }

    public function name(): string
    {
        return '腾讯云市场地址解析';
    }

    public function healthCheck(int $siteId): bool
    {
        $result = (new CoreThirdPartyService())->testConnection($siteId, ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE);
        return !empty($result['success']);
    }

    public function parse(int $siteId, string $address): array
    {
        $result = (new CoreThirdPartyService())->call(
            ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE,
            'parse',
            ['address' => $address],
            $siteId
        );
        $providerResult = is_array($result['data'] ?? null) ? $result['data'] : [];
        return is_array($providerResult['data'] ?? null) ? $providerResult['data'] : [];
    }
}
