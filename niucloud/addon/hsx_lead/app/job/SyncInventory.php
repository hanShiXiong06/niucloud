<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\SyncService;
use addon\hsx_lead\app\support\PhpRuntime;
use core\base\BaseJob;
final class SyncInventory extends BaseJob
{
    public function doJob($data = []): bool
    {
        $secretTrace = PhpRuntime::guardSecrets();
        try {
            (new SyncService())->tick(5);
            return true;
        } finally {
            $secretTrace->close();
        }
    }
}
