<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\third_party;

use addon\hsx_recycle\app\service\core\device_query\provider\PathQueryProvider;
use addon\hsx_recycle\app\service\core\device_query\provider\GkdtQueryProvider;
use addon\hsx_recycle\app\service\core\device_query\provider\ServiceIdQueryProvider;

class DeviceQueryProviderRegistryListener
{
    public function handle(array $params = []): array
    {
        return [
            'providers' => [
                'path_query' => PathQueryProvider::class,
                '3023' => PathQueryProvider::class,
                'gkdt_query' => GkdtQueryProvider::class,
                'service_id_query' => ServiceIdQueryProvider::class,
            ],
        ];
    }
}
