<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\address;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\address\contract\AddressParseProviderInterface;
use addon\hsx_recycle\app\service\core\address\provider\TencentCloudMarketAddressProvider;
use addon\hsx_recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use core\exception\CommonException;
use think\facade\Log;

class AddressParseProviderRegistry
{
    /** @var array<string, class-string<AddressParseProviderInterface>> */
    private array $providers = [
        ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS => TencentCloudMarketAddressProvider::class,
    ];

    public function __construct()
    {
        $this->loadEventProviders();
    }

    public function resolve(int $siteId, string $providerKey = ''): AddressParseProviderInterface
    {
        if ($providerKey === '') {
            $config = new RecycleThirdPartyConfigService();
            if ($config->hasSavedConfig($siteId) && !$config->isServiceEnabled($siteId, ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE)) {
                throw new CommonException('地址解析服务未启用');
            }
            $providerKey = $config->hasSavedConfig($siteId)
                ? $config->getActiveProvider($siteId, ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE)
                : ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS;
        }

        $class = $this->providers[$providerKey] ?? '';
        if ($class === '' || !class_exists($class)) {
            throw new CommonException('地址解析服务商未接入或不可用：' . $providerKey);
        }
        $provider = new $class();
        if (!$provider instanceof AddressParseProviderInterface) {
            throw new CommonException('地址解析服务商未实现统一能力契约：' . $providerKey);
        }
        return $provider;
    }

    private function loadEventProviders(): void
    {
        try {
            foreach ((array)event('HsxAddressParseProviderRegistry', []) as $response) {
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
                Log::warning('加载地址解析扩展失败，继续使用内置服务商：' . $e->getMessage());
            } catch (\Throwable $ignored) {
            }
        }
    }
}
