<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\job\schedule;

use addon\hsx_erp\app\service\admin\ErpIntegrationService;
use core\base\BaseJob;
use think\facade\Log;

final class DomainEventRetry extends BaseJob
{
    public function doJob(array $params = []): string
    {
        try {
            $service = new ErpIntegrationService();
            $outbox = $service->retryPendingDomainEvents(
                (int)($params['site_id'] ?? 0),
                (int)($params['limit'] ?? 100)
            );
            $inbox = $service->retryFailedExternalRequests(
                (int)($params['site_id'] ?? 0),
                (int)($params['limit'] ?? 100)
            );
            if ((int)$outbox['failed'] > 0 || (int)$outbox['dead'] > 0
                || (int)$inbox['failed'] > 0 || (int)$inbox['dead'] > 0) {
                Log::warning('ERP跨插件事件补偿仍有失败', ['outbox' => $outbox, 'inbox' => $inbox]);
            }
            // 调度器会把 Job 返回值写入 sys_schedule_log.execute_result 文本字段，
            // 不能返回关联数组，否则 ThinkORM 会将其误认为字段更新表达式。
            return sprintf(
                '发件箱：扫描 %d/成功 %d/失败 %d/人工 %d；退款收件箱：扫描 %d/成功 %d/失败 %d/人工 %d',
                (int)($outbox['scanned'] ?? 0),
                (int)($outbox['done'] ?? 0),
                (int)($outbox['failed'] ?? 0),
                (int)($outbox['dead'] ?? 0),
                (int)($inbox['scanned'] ?? 0),
                (int)($inbox['done'] ?? 0),
                (int)($inbox['failed'] ?? 0),
                (int)($inbox['dead'] ?? 0)
            );
        } catch (\Throwable $e) {
            Log::error('ERP跨插件事件补偿失败：' . $e->getMessage());
            // 交给框架调度器记录失败状态，避免业务异常被误标为执行成功。
            throw $e;
        }
    }
}
