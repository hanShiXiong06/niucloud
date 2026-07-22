<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express_query;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\express_query\contract\ExpressQueryProviderInterface;
use addon\hsx_recycle\app\service\core\express_query\provider\AliExpressQueryProvider;
use addon\hsx_recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use core\exception\CommonException;
use think\facade\Log;

class ExpressQueryProviderRegistry
{
    /** @var array<string, class-string<ExpressQueryProviderInterface>> */
    private array $providers = [
        ThirdPartyDict::PROVIDER_ALI_EXPRESS => AliExpressQueryProvider::class,
    ];

    public function __construct()
    {
        $this->loadEventProviders();
    }

    public function resolve(int $siteId, string $providerKey = ''): ExpressQueryProviderInterface
    {
        if ($providerKey === '') {
            $config = new RecycleThirdPartyConfigService();
            if ($config->hasSavedConfig($siteId) && !$config->isServiceEnabled($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY)) {
                throw new CommonException('快递查询服务未启用');
            }
            $providerKey = $config->hasSavedConfig($siteId)
                ? $config->getActiveProvider($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY)
                : ThirdPartyDict::PROVIDER_ALI_EXPRESS;
        }

        $class = $this->providers[$providerKey] ?? '';
        if ($class === '' || !class_exists($class)) {
            throw new CommonException('快递查询服务商未接入或不可用：' . $providerKey);
        }
        $provider = new $class();
        if (!$provider instanceof ExpressQueryProviderInterface) {
            throw new CommonException('快递查询服务商未实现统一能力契约：' . $providerKey);
        }
        return $provider;
    }

    private function loadEventProviders(): void
    {
        try {
            foreach ((array)event('HsxExpressQueryProviderRegistry', []) as $response) {
                $providers = is_array($response['providers'] ?? null) ? $response['providers'] : $response;
                if (!is_array($providers)) {
                    continue;
                }
                foreach ($providers as $key => $class) {
                    if (is_string($key) && $key !== '' && is_string($class) && $class !== '') {
                        $this->providers[$key] = $class;
                    }
                }
            }
        } catch (\Throwable $e) {
            try {
                Log::warning('加载快递查询扩展失败，继续使用内置服务商：' . $e->getMessage());
            } catch (\Throwable $ignored) {
            }
        }
    }
}
