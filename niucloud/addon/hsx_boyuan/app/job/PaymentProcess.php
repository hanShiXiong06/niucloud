<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\job;
use addon\hsx_boyuan\app\service\PaymentSyncService;
use addon\hsx_boyuan\app\gateway\Transport;
use core\base\BaseJob;
/** Exactly one attempt at an explicitly authorized frozen UAT accounting request. */
final class PaymentProcess extends BaseJob
{
    public function doJob(int $site,int $taskId,?Transport $transport=null): bool { return (new PaymentSyncService())->process($site,$taskId,$transport); }
}
