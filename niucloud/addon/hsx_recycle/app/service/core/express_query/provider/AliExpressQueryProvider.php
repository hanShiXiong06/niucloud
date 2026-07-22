<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express_query\provider;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\express_query\contract\ExpressQueryProviderInterface;
use addon\hsx_recycle\app\service\core\third_party\CoreThirdPartyService;

/** 保留现网配置、日志和计费的阿里快递查询适配器。 */
class AliExpressQueryProvider implements ExpressQueryProviderInterface
{
    public function key(): string
    {
        return ThirdPartyDict::PROVIDER_ALI_EXPRESS;
    }

    public function name(): string
    {
        return '阿里云快递查询';
    }

    public function healthCheck(int $siteId): bool
    {
        $result = (new CoreThirdPartyService())->testConnection($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY);
        return !empty($result['success']);
    }

    public function query(int $siteId, string $expressNo, string $mobile = ''): array
    {
        $result = (new CoreThirdPartyService())->call(
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
            'query',
            ['express_no' => $expressNo, 'mobile' => $mobile],
            $siteId
        );
        $providerResult = is_array($result['data'] ?? null) ? $result['data'] : [];
        return is_array($providerResult['data'] ?? null) ? $providerResult['data'] : [];
    }
}
