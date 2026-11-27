<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\express;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\express\provider\YisuExpressProvider;

/** 回收插件向统一快递能力中心注册自身提供的快递适配器。 */
class ExpressProviderRegistryListener
{
    public function handle(array $params = []): array
    {
        return [
            'providers' => [
                ThirdPartyDict::PROVIDER_YISU => YisuExpressProvider::class,
            ],
        ];
    }
}
