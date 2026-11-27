<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

interface Transport
{
    public function post(string $service, array $payload, array $headers): array;
}
