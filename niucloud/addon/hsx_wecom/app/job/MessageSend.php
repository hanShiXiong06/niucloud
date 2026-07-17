<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\job;

use addon\hsx_wecom\app\service\core\WecomNotificationService;
use core\base\BaseJob;

final class MessageSend extends BaseJob
{
    public function doJob(int $messageId): void
    {
        (new WecomNotificationService())->dispatch($messageId);
    }
}
