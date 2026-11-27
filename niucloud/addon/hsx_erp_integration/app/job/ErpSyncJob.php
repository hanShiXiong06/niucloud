<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\job;

use addon\hsx_erp_integration\app\service\SyncTaskService;
use think\queue\Job;

/**
 * ERP同步任务（队列任务）
 */
class ErpSyncJob
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
                $service->executeTask($taskId);
            }

            // 任务完成，删除队列
            $job->delete();

        } catch (\Throwable $e) {
            // 任务失败
            if ($job->attempts() > 3) {
                // 超过3次尝试，删除任务
                $job->delete();
            } else {
                // 重新放入队列（延迟60秒）
                $job->release(60);
            }
        }
    }

    /**
     * 任务失败回调
     * @param array $data
     */
    public function failed(array $data): void
    {
        // 记录失败日志
        \think\facade\Log::error('ERP同步任务失败: ' . json_encode($data));
    }
}
