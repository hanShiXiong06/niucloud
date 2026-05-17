<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer;

use addon\hsx_recycle\app\model\printer\RecyclePrintTask;
use core\base\BaseAdminService;

/**
 * 回收打印触发服务
 * Class RecyclePrintTriggerService
 * @package addon\hsx_recycle\app\service\admin\printer
 */
class RecyclePrintTriggerService extends BaseAdminService
{
    protected $sceneService;
    protected $taskService;

    public function __construct()
    {
        parent::__construct();
        $this->sceneService = new RecyclePrintSceneService();
        $this->taskService = new RecyclePrintTaskService();
    }

    /**
     * 自动触发打印
     * @param string $triggerKey
     * @param array $payload
     * @return array
     */
    public function auto(string $triggerKey, array $payload = []): array
    {
        $plans = $this->sceneService->resolvePlansByTrigger($triggerKey, $payload, true);
        $results = [];

        foreach ($plans as $plan) {
            $results[] = $this->executePlanThroughTask($plan, $payload, RecyclePrintTask::MODE_AUTO);
        }

        $hasFailure = false;
        foreach ($results as $result) {
            if (empty($result['success'])) {
                $hasFailure = true;
                break;
            }
        }

        return [
            'success' => !$hasFailure,
            'trigger_key' => $triggerKey,
            'results' => $results,
        ];
    }

    /**
     * 手动执行指定场景
     * @param string $sceneKey
     * @param array $payload
     * @return array
     */
    public function manual(string $sceneKey, array $payload = []): array
    {
        $plan = $this->sceneService->resolvePlanBySceneKey($sceneKey, $payload, false);
        return $this->executePlanThroughTask($plan, $payload, RecyclePrintTask::MODE_MANUAL);
    }

    /**
     * 通过任务执行打印计划
     * @param array $plan
     * @param array $payload
     * @param string $mode
     * @return array
     */
    private function executePlanThroughTask(array $plan, array $payload, string $mode): array
    {
        if (empty($plan['can_print'])) {
            $this->sceneService->recordSkippedPlan($plan, $plan['message'] ?? '打印计划不可用');
            return array_merge($plan, [
                'success' => false,
                'message' => $plan['message'] ?? '打印计划不可用',
            ]);
        }

        $taskResult = $this->taskService->createFromPlan($plan, $payload, $mode);
        $task = $taskResult['task'] ?? [];
        if (!empty($taskResult['duplicate'])) {
            $message = ((int)($task['status'] ?? 0) === RecyclePrintTask::STATUS_SUCCESS)
                ? '该设备已自动打印过，已跳过重复打印'
                : '该打印任务已存在，已跳过重复自动打印';
            $this->sceneService->recordSkippedPlan($plan, $message);
            return $this->taskService->skippedResult($task, $message);
        }

        $taskId = (int)($task['task_id'] ?? 0);
        if ($taskId > 0) {
            $this->taskService->markRunning($taskId);
        }

        $response = $this->sceneService->executeDevicePrintPlan($plan);
        if ($taskId > 0) {
            $this->taskService->finish($taskId, $response);
            $response['task_id'] = $taskId;
        }

        return $response;
    }
}
