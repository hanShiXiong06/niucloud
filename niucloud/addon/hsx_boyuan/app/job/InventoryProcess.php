<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\job;

use addon\hsx_boyuan\app\service\PurchaseInventoryService;
use core\base\BaseJob;

/** One bounded read-only chunk per queued invocation; never loops or calls purchase creation. */
final class InventoryProcess extends BaseJob
{
    public function doJob(int $site,int $taskId): bool { return (new PurchaseInventoryService())->process($site,$taskId); }
}
