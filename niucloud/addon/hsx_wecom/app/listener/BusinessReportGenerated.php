<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\listener;

use addon\hsx_wecom\app\service\core\WecomNotificationService;

final class BusinessReportGenerated
{
    public function handle(array $event): array
    {
        return (new WecomNotificationService())->enqueueBusinessReport($event);
    }
}
