<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\contract;

/** Optional provider declaration for two *mutually exclusive*, manual, single-business scopes.
 * Computed locally for this site; background capture has no interactive user or network permission. */
interface DisjointManualScopeInterface extends ScopedManualCaptureInterface
{
    /** @return array<int,array{0:string,1:string}> unordered pairs, never wildcards or target credentials */
    public function manualDisjointScopes(int $site): array;
}
