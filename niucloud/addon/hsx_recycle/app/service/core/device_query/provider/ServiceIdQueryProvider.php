<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query\provider;

/**
 * 历史兼容别名。旧站点保存的是 service_id_query，新配置统一使用 gkdt_query。
 */
class ServiceIdQueryProvider extends GkdtQueryProvider
{
    public function getProviderName(): string
    {
        return 'service_id_query';
    }
}
