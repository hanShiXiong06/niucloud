<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\auth;

interface CaptchaTransportContract
{
    /** Fixed method names, not URLs. Only a bounded decoded JSON object may be returned. */
    public function post(string $method, #[\SensitiveParameter] array $body): array;
}
