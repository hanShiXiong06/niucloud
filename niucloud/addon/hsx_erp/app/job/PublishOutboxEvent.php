<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\job;

use addon\hsx_erp\app\model\ErpOutboxEvent;
use core\base\BaseJob;

class PublishOutboxEvent extends BaseJob
{
    public function doJob(int $outbox_id): void
    {
        $event = ErpOutboxEvent::where([['id', '=', $outbox_id]])->findOrEmpty();
        if ($event->isEmpty() || (string)$event->status === 'published') {
            return;
        }

        try {
            event('ErpDomainEvent', (array)$event->payload);
            $event->save([
                'status' => 'published',
                'attempts' => (int)$event->attempts + 1,
                'published_at' => time(),
                'error_message' => '',
                'update_at' => time(),
            ]);
        } catch (\Throwable $e) {
            $event->save([
                'status' => 'failed',
                'attempts' => (int)$event->attempts + 1,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'update_at' => time(),
            ]);
            throw $e;
        }
    }
}
