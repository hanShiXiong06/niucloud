<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\contract;

/** Optional local-only restriction on a manual route. Must not create tasks, call remote services or authorize a write. */
interface ScopedManualCaptureInterface
{
    public function admitsManualFact(int $site,array $connectionConfig,array $event): bool;
}
