<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\job\schedule;

use addon\hsx_wecom\app\service\core\WecomNotificationService;
use core\base\BaseJob;

final class MessageRetry extends BaseJob
{
    public function doJob(array $params = []): void
    {
        (new WecomNotificationService())->retryPending(100);
    }
}
