<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;
final class DomainEvent
{
    public function handle(array $event=[]): array { return (new \addon\hsx_boyuan\app\service\TaskService())->accept($event); }
}
