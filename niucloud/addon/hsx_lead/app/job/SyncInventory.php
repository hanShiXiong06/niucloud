<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\SyncService;
use core\base\BaseJob;
final class SyncInventory extends BaseJob
{
    public function doJob($data=[]): bool
    {
        (new SyncService())->tick(5);
        return true;
    }
}
