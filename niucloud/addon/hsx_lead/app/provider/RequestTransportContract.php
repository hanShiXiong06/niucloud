<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

/** Optional fixed-endpoint form/HTML capability. Existing JSON-only transports stay valid. */
interface RequestTransportContract extends TransportContract
{
    /** @return array{status:int,headers:array<string,array<string>>,body:string} */
    public function request(string $method, string $endpoint, #[\SensitiveParameter] array $headers,
        #[\SensitiveParameter] array $data, string $encoding): array;
}
