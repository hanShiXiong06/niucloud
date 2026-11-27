<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query\contract;

interface DeviceQueryProviderInterface
{
    public function query(array $channel, array $mapping, string $queryCode, string $queryType): array;

    public function getProviderName(): string;
}
