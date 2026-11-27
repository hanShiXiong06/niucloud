<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

use addon\hsx_recycle\app\service\core\device_query\contract\DeviceQueryProviderInterface;
use addon\hsx_recycle\app\service\core\device_query\provider\GkdtQueryProvider;
use addon\hsx_recycle\app\service\core\device_query\provider\PathQueryProvider;
use addon\hsx_recycle\app\service\core\device_query\provider\ServiceIdQueryProvider;
use think\facade\Log;

/** 设备查询渠道注册中心；3023、爱查及未来渠道通过事件热插拔。 */
class DeviceQueryProviderRegistry
{
    /** @var array<string, class-string<DeviceQueryProviderInterface>> */
    private array $providers = [
        'path_query' => PathQueryProvider::class,
        '3023' => PathQueryProvider::class,
        'gkdt_query' => GkdtQueryProvider::class,
        'service_id_query' => ServiceIdQueryProvider::class,
    ];

    public function __construct()
    {
        $this->loadEventProviders();
    }

    public function resolve(string $providerKey): DeviceQueryProviderInterface
    {
        $providerKey = trim($providerKey);
        $class = $this->providers[$providerKey] ?? '';
        if ($class === '' || !class_exists($class)) {
            throw new \RuntimeException('不支持的设备查询渠道类型：' . $providerKey);
        }
        $provider = new $class();
        if (!$provider instanceof DeviceQueryProviderInterface) {
            throw new \RuntimeException('设备查询渠道未实现统一能力契约：' . $providerKey);
        }
        return $provider;
    }

    private function loadEventProviders(): void
    {
        try {
            foreach ((array)event('HsxDeviceQueryProviderRegistry', []) as $response) {
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
                Log::warning('加载设备查询渠道扩展失败，继续使用内置渠道：' . $e->getMessage());
            } catch (\Throwable $ignored) {
            }
        }
    }
}
