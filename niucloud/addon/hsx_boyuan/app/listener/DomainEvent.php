<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;
use addon\hsx_boyuan\app\service\TaskService;
use addon\hsx_boyuan\app\support\Problem;
final class DomainEvent
{
    public function handle(array $event=[]): array
    {
        try {
            return (new TaskService())->accept($event);
        } catch (\Throwable $e) {
            // 结构化失败会保留原事件待重试，同时允许后面的回收、商城监听器继续消费。
            // 不能抛异常截断财务回写，也不能假装已接收而吞掉待同步的单据。
            $reason = $e instanceof Problem ? $e->reason : 'CAPTURE_FAILED';
            TaskService::audit((int)($event['site_id'] ?? 0),0,'','capture','failed',
                '博远待同步：'.$reason.'；事件 '.(string)($event['event_id'] ?? ''));
            return ['consumer'=>'hsx_boyuan','status'=>'failed','message'=>'本地业务已记账；博远未接收同步事件（'.$reason.'），请核对原单，不要重复开单。'];
        }
    }
}
