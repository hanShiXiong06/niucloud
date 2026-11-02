<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

/** Optional interactive authorization. Challenge state is server-only, short-lived and encrypted. */
interface InteractiveAuthProviderContract
{
    /** @return array{public:array,state:array} No password or token may appear in public. */
    public function beginAuthorization(): array;

    /**
     * Authorized credentials are returned to the server, never directly to the browser.
     * A choose_company result contains only safe options in public and temporary secrets in state.
     */
    public function authorize(#[\SensitiveParameter] array $credentials, #[\SensitiveParameter] array $state,
        #[\SensitiveParameter] array $answer): array;
}
