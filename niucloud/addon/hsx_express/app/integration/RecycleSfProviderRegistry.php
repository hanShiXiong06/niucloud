<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

/** 只注册适配器；回收插件未安装时不加载其接口，也不发起任何外部请求。 */
final class RecycleSfProviderRegistry
{
    public function handle(array $params = []): array
    {
        if (!interface_exists('addon\\hsx_recycle\\app\\service\\core\\express\\contract\\ExpressProviderInterface')) {
            return [];
        }
        return ['providers' => ['sf_direct' => RecycleSfPickupProvider::class]];
    }
}
