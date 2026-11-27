<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\provider;

use addon\hsx_phone_query\app\service\core\provider\contract\PhoneQueryProviderInterface;
use core\exception\CommonException;

class PhoneQueryProviderRegistry
{
    /** @var PhoneQueryProviderInterface[] */
    private array $providers;

    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? [
            new GkdtPhoneQueryProvider(),
            new PathPhoneQueryProvider(),
        ];
    }

    public function resolve(string $provider): PhoneQueryProviderInterface
    {
        foreach ($this->providers as $adapter) {
            if ($adapter instanceof PhoneQueryProviderInterface && $adapter->supports($provider)) {
                return $adapter;
            }
        }

        throw new CommonException('不支持的手机查询服务商类型：' . ($provider ?: '未配置'));
    }
}
