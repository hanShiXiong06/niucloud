<?php
declare(strict_types=1);
namespace addon\hsx_kingdee\app\listener;
use addon\hsx_kingdee\app\service\TaskService;
final class LedgerRecorded
{
    public function handle(array $event = []): array
    {
        return (new TaskService())->accept($event);
    }
}
