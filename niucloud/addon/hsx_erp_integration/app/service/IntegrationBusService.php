<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\model\ErpIntegrationConnection;
use addon\hsx_erp_integration\app\model\ErpIntegrationSyncTask;

/**
 * ERP 集成总线
 *
 * 负责接收 hsx_erp 的业务事件，分发到各个外部 ERP 适配器
 */
class IntegrationBusService
{
    private SyncTaskService $taskService;
    private AdapterRegistry $registry;

    public function __construct()
    {
        $this->taskService = new SyncTaskService();
        $this->registry = app(AdapterRegistry::class);
    }

    /** Captures the original ERP fact locally; never dispatches to an adapter or queues a write. */
    public function acceptDomainEvent(array $event): array
    {
        return (new DomainCaptureService())->accept($event);
    }

    /**
     * 推送采购入库到所有已启用的外部ERP
     * @param int $siteId
     * @param string $sourceType 例如：erp_purchase
     * @param int $sourceId
     * @param array $data 业务数据
     */
    public function dispatchPurchase(int $siteId, string $sourceType, int $sourceId, array $data): void
    {
        $this->dispatch($siteId, 'purchase', $sourceType, $sourceId, $data);
    }

    /**
     * 推送销售出库
     */
    public function dispatchSale(int $siteId, string $sourceType, int $sourceId, array $data): void
    {
        $this->dispatch($siteId, 'sale', $sourceType, $sourceId, $data);
    }

    /**
     * 推送付款
     */
    public function dispatchPayment(int $siteId, string $sourceType, int $sourceId, array $data): void
    {
        $this->dispatch($siteId, 'payment', $sourceType, $sourceId, $data);
    }

    /**
     * 推送收款
     */
    public function dispatchReceipt(int $siteId, string $sourceType, int $sourceId, array $data): void
    {
        $this->dispatch($siteId, 'receipt', $sourceType, $sourceId, $data);
    }

    /**
     * 推送退货
     */
    public function dispatchReturn(int $siteId, string $sourceType, int $sourceId, array $data): void
    {
        $this->dispatch($siteId, 'return', $sourceType, $sourceId, $data);
    }

    /**
     * 统一分发逻辑
     * @param int $siteId
     * @param string $businessType
     * @param string $sourceType
     * @param int $sourceId
     * @param array $data
     */
    private function dispatch(int $siteId, string $businessType, string $sourceType, int $sourceId, array $data): void
    {
        // 获取该站点启用的外部ERP连接
        $connections = ErpIntegrationConnection::where('site_id', $siteId)
            ->where('status', 'enabled')
            ->select()
            ->toArray();

        // A manual route takes scope away from the legacy broadcast, including if configuration
        // was changed concurrently or externally. The domain listener surfaces any ambiguity.
        foreach ($connections as $conn) {
            if ($conn['sync_mode'] === 'manual' && in_array($businessType,
                \addon\hsx_erp_integration\app\support\ConnectionFields::values($conn['business_types'] ?? []), true)) return;
        }
        foreach ($connections as $conn) {
            if (!in_array($conn['sync_mode'], ['async','sync'], true)) continue;
            // 检查该连接是否启用了此业务类型
            $businessTypes = \addon\hsx_erp_integration\app\support\ConnectionFields::values($conn['business_types'] ?? []);
            if (!in_array($businessType, $businessTypes)) {
                continue;
            }

            // 检查是否已创建过成功的任务
            $existingTask = ErpIntegrationSyncTask::where('connection_id', $conn['id'])
                ->where('business_type', $businessType)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('status', 'succeeded')
                ->findOrEmpty();

            if (!$existingTask->isEmpty()) {
                continue; // 已成功，不重复推送
            }

            // 创建任务
            $task = ErpIntegrationSyncTask::create([
                'site_id' => $siteId,
                'connection_id' => $conn['id'],
                'business_type' => $businessType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_no' => $data['source_no'] ?? $data[$businessType . '_no'] ?? '',
                'status' => 'pending',
                'payload' => $data,
                'created_at' => time(),
                'updated_at' => time(),
            ]);

            // 异步执行（放入队列）
            if ($conn['sync_mode'] === 'async') {
                // 使用 ThinkPHP 队列
                try {
                    queue('hsx_erp_integration', 'ErpSyncJob', ['task_id' => $task->id]);
                } catch (\Throwable $e) {
                    // 队列失败则立即执行
                    $this->taskService->executeTask($task->id);
                }
            } else {
                // 同步执行
                $this->taskService->executeTask($task->id);
            }
        }
    }
}
