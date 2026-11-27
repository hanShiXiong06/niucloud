<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

/** Read-only ERP boundary. Credentials are transient inputs, never inventory fields. */
interface ProviderContract
{
    public function key(): string;

    /** @return array{access_token:string,identity:array} */
    public function login(#[\SensitiveParameter] array $credentials): array;

    /** @return array{rows:array,has_more:bool,cursor:array} */
    public function fetchPage(#[\SensitiveParameter] array $credentials, array $cursor, array $scope): array;

    /** context requires connection_id; tenant_id/store_id are enforced when supplied. */
    public function normalize(array $row, array $context): array;
}
