<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

interface TransportContract
{
    /** Returns decoded JSON only; must never log credentials, requests or remote bodies. */
    public function post(string $endpoint, #[\SensitiveParameter] array $headers, #[\SensitiveParameter] array $body): array;
}
