<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\job;

use addon\hsx_erp_integration\app\service\SyncTaskService;
use think\queue\Job;

/**
 * ERP同步重试任务（队列任务）
 */
class RetrySyncJob
{
    /**
     * 执行队列任务
     * @param Job $job
     * @param array $data ['task_id' => 任务ID]
     */
    public function fire(Job $job, array $data): void
    {
        try {
            $taskId = $data['task_id'] ?? 0;
            if ($taskId > 0) {
                $service = new SyncTaskService();
                $service->retryTask($taskId);
            }

            // 任务完成，删除队列
            $job->delete();

        } catch (\Throwable $e) {
            // 失败直接删除，不再重试（因为本身就是重试任务）
            $job->delete();
            \think\facade\Log::error('ERP同步重试任务失败: ' . $e->getMessage());
        }
    }
}
