<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\express;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\express_query\provider\AliExpressQueryProvider;

class ExpressQueryProviderRegistryListener
{
    public function handle(array $params = []): array
    {
        return ['providers' => [ThirdPartyDict::PROVIDER_ALI_EXPRESS => AliExpressQueryProvider::class]];
    }
}
