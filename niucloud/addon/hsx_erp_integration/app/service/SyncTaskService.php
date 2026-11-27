<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\model\ErpIntegrationSyncTask;
use addon\hsx_erp_integration\app\model\ErpIntegrationConnection;
use addon\hsx_erp_integration\app\model\ErpIntegrationFailure;
use think\facade\Log;
use addon\hsx_erp_integration\app\support\{ConnectionFields, TaskView};

/**
 * 同步任务执行服务
 */
class SyncTaskService
{
    private AdapterRegistry $registry;

    public function __construct()
    {
        $this->registry = app(AdapterRegistry::class);
    }

    /**
     * 执行同步任务
     * @param int $taskId
     */
    public function executeTask(int $taskId): void
    {
        $task = ErpIntegrationSyncTask::find($taskId);
        if (!$task || $task->status !== 'pending' || !empty($task->fact_key)) {
            return;
        }

        if (!ErpIntegrationSyncTask::where('id', $taskId)->where('status', 'pending')
            ->update(['status' => 'processing', 'updated_at' => time()])) return;
        $task->status = 'processing';

        try {
            // 1. 获取连接配置
            $connection = ErpIntegrationConnection::where('site_id', $task->site_id)->where('id', $task->connection_id)->find();
            if (!$connection || $connection->status !== 'enabled' || $connection->sync_mode === 'manual') {
                throw new \Exception('连接不存在或已暂停，未发送');
            }

            // 2. 获取适配器
            $adapter = AdapterRegistry::forSite((int)$task->site_id)->getAdapter($connection->adapter_code);

            // 3. 解析数据
            $payload = ConnectionFields::values($task->payload);
            $config = ConnectionFields::open(ConnectionFields::values($connection->config), (int)$task->site_id, $connection->adapter_code);
            $config['connection_id'] = (int)$connection->id;

            // 4. 调用适配器推送
            $result = match($task->business_type) {
                'purchase' => $adapter->syncPurchase($payload, $config),
                'sale' => $adapter->syncSale($payload, $config),
                'payment' => $adapter->syncPayment($payload, $config),
                'receipt' => $adapter->syncReceipt($payload, $config),
                'return' => $adapter->syncReturn($payload, $config),
                default => throw new \Exception('不支持的业务类型: ' . $task->business_type)
            };

            // 5. 处理结果
            if ($result['success']) {
                $task->status = 'succeeded';
                $task->external_doc_no = $result['external_doc_no'] ?? null;
                $task->external_doc_id = $result['external_doc_id'] ?? null;
                $task->succeeded_at = time();
                $task->error_message = null;
                $task->error_code = null;

                Log::info("ERP集成推送成功 [task_id={$taskId}] [business_type={$task->business_type}] [external_doc_no={$task->external_doc_no}]");
            } else {
                $this->handleFailure($task, $result['message'] ?? '未知错误', $result['error_code'] ?? null);
            }

            $task->updated_at = time();
            $task->save();

        } catch (\Throwable $e) {
            Log::error("ERP集成推送异常 [task_id={$taskId}] [error={$e->getMessage()}]");
            $this->handleFailure($task, $e->getMessage());
            $task->updated_at = time();
            $task->save();
        }
    }

    /**
     * 处理失败
     * @param ErpIntegrationSyncTask $task
     * @param string $errorMessage
     * @param string|null $errorCode
     */
    private function handleFailure($task, string $errorMessage, ?string $errorCode = null): void
    {
        $task->status = 'failed';
        $task->error_message = $errorMessage;
        $task->error_code = $errorCode;
        $task->retry_count += 1;
        $task->last_retry_at = time();

        // 判断是否需要重试
        $connection = ErpIntegrationConnection::find($task->connection_id);
        if ($errorCode === 'MAPPING_MISSING' && $connection && $task->retry_count < $connection->retry_times) {
            // 延迟重试（5分钟后）
            try {
                queue('hsx_erp_integration', 'RetrySyncJob', ['task_id' => $task->id], 300);
                Log::info("ERP集成任务将在5分钟后重试 [task_id={$task->id}] [retry_count={$task->retry_count}]");
            } catch (\Throwable $e) {
                // 队列失败，不阻断
                Log::warning("ERP集成重试队列添加失败 [task_id={$task->id}] [error={$e->getMessage()}]");
            }
        } else {
            // 超过重试次数，记录到失败表
            ErpIntegrationFailure::create([
                'site_id' => $task->site_id,
                'task_id' => $task->id,
                'connection_id' => $task->connection_id,
                'business_type' => $task->business_type,
                'source_type' => $task->source_type,
                'source_id' => $task->source_id,
                'source_no' => $task->source_no,
                'failure_type' => $this->detectFailureType($errorMessage, $errorCode),
                'error_detail' => $errorMessage,
                'resolved' => 0,
                'created_at' => time(),
            ]);

            Log::warning("ERP集成任务失败（已超过重试次数） [task_id={$task->id}] [error={$errorMessage}]");
        }
    }

    /**
     * 检测失败类型
     */
    private function detectFailureType(string $errorMessage, ?string $errorCode): string
    {
        if ($errorCode) {
            if (str_contains($errorCode, 'MAPPING')) {
                return 'mapping_missing';
            }
            if (str_contains($errorCode, 'TIMEOUT')) {
                return 'timeout';
            }
            if (str_contains($errorCode, 'VALIDATION')) {
                return 'validation_error';
            }
        }

        // 根据错误信息判断
        $message = strtolower($errorMessage);
        if (str_contains($message, '映射') || str_contains($message, 'mapping') || str_contains($message, '未配置')) {
            return 'mapping_missing';
        }
        if (str_contains($message, '超时') || str_contains($message, 'timeout')) {
            return 'timeout';
        }
        if (str_contains($message, '验证') || str_contains($message, 'validation')) {
            return 'validation_error';
        }

        return 'api_error';
    }

    /**
     * 重试任务
     * @param int $taskId
     */
    public function retryTask(int $taskId): void
    {
        $task = ErpIntegrationSyncTask::find($taskId);
        if (!$task || !TaskView::canRetry($task->toArray())) throw new \RuntimeException('任务已变化或发送结果未确定，不能重试');
        if (!ErpIntegrationConnection::where('site_id', $task->site_id)->where('id', $task->connection_id)->where('status', 'enabled')->count())
            throw new \RuntimeException('连接已暂停，不能重试');
        if (!ErpIntegrationSyncTask::where('id', $taskId)->where('status', 'failed')->where('error_code', 'MAPPING_MISSING')
            ->update(['status' => 'pending', 'error_message' => null, 'error_code' => null, 'updated_at' => time()]))
            throw new \RuntimeException('该任务已经处理，请刷新核对');

        // 执行任务
        $this->executeTask($taskId);
    }
}
