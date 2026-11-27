<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\model\express\ExpressProviderConfig;
use addon\hsx_recycle\app\service\core\express\contract\ExpressProviderInterface;
use addon\hsx_recycle\app\service\core\express\provider\YisuExpressProvider;
use addon\hsx_recycle\app\service\core\express\provider\Kuaidi100ExpressProvider;
use addon\hsx_recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 快递供应商注册中心。
 *
 * 新供应商通过 HsxExpressProviderRegistry 事件贡献 Provider 类，业务层无需
 * 修改 switch/case。内置亿速兜底用于兼容事件缓存尚未刷新的存量站点。
 */
class ExpressProviderRegistry
{
    /** @var array<string, class-string<ExpressProviderInterface>> */
    private $providers = [
        ThirdPartyDict::PROVIDER_YISU => YisuExpressProvider::class,
        ThirdPartyDict::PROVIDER_KUAIDI100 => Kuaidi100ExpressProvider::class,
    ];

    public function __construct()
    {
        $this->loadEventProviders();
    }

    public function activeKey(int $siteId): string
    {
        $configService = new RecycleThirdPartyConfigService();
        if ($configService->hasSavedConfig($siteId)) {
            if (!$configService->isServiceEnabled($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER)) {
                throw new CommonException('快递服务未启用，请先在第三方配置中启用');
            }
            $key = $configService->getActiveProvider($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER);
        } else {
            $key = ExpressProviderConfig::getDefaultProvider($siteId);
        }

        if ($key === '') {
            throw new CommonException('未配置快递服务商，请先在后台设置');
        }
        return $key;
    }

    public function resolve(int $siteId, string $providerKey = ''): ExpressProviderInterface
    {
        $providerKey = $providerKey !== '' ? $providerKey : $this->activeKey($siteId);
        $class = $this->providers[$providerKey] ?? '';
        if ($class === '' || !class_exists($class)) {
            throw new CommonException('快递服务商未接入或不可用：' . $providerKey);
        }

        $provider = new $class();
        if (!$provider instanceof ExpressProviderInterface) {
            throw new CommonException('快递服务商未实现统一能力契约：' . $providerKey);
        }
        return $provider;
    }

    public function all(): array
    {
        return $this->providers;
    }

    /**
     * 可自动核实的渠道与查询所需的持久化标识。
     * 可选能力由 Provider 自己声明，调度器不枚举厂商，也不自动重下单。
     *
     * @return array<string, array<int, string>>
     */
    public function queryRequirements(): array
    {
        $result = [];
        foreach ($this->providers as $key => $class) {
            if (!class_exists($class) || !is_subclass_of($class, ExpressProviderInterface::class)
                || !method_exists($class, 'queryRequirements')) {
                continue;
            }
            $requirements = (new $class())->queryRequirements();
            if (!is_array($requirements)) continue;
            $result[$key] = array_values(array_filter($requirements, static function ($field): bool {
                return is_string($field) && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $field) === 1;
            }));
        }
        return $result;
    }

    private function loadEventProviders(): void
    {
        try {
            $responses = (array)event('HsxExpressProviderRegistry', []);
            foreach ($responses as $response) {
                if (!is_array($response)) {
                    continue;
                }
                $providers = is_array($response['providers'] ?? null) ? $response['providers'] : $response;
                foreach ($providers as $key => $class) {
                    if (!is_string($key) || $key === '' || !is_string($class) || $class === '') {
                        continue;
                    }
                    $this->providers[$key] = $class;
                }
            }
        } catch (\Throwable $e) {
            try {
                Log::warning('加载快递供应商扩展失败，继续使用内置供应商：' . $e->getMessage());
            } catch (\Throwable $ignored) {
                // CLI 独立契约测试未初始化框架容器时无需写框架日志。
            }
        }
    }
}
