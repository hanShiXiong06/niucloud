<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

/** Optional challenge that must be explicitly requested for a validated account. */
interface CredentialChallengeProviderContract extends InteractiveAuthProviderContract
{
    /** Public response never contains the submitted code or reusable login credentials. */
    public function beginAuthorizationFor(#[\SensitiveParameter] array $credentials): array;
}
