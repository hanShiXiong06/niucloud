<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\gateway;

interface Transport
{
    public function send(string $method, string $path, array $query, array $headers, array $body): array;
}
