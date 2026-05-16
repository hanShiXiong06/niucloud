<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer;

use addon\recycle\app\model\printer\RecyclePrintTask;
use core\base\BaseAdminService;

/**
 * 回收打印任务服务
 * Class RecyclePrintTaskService
 * @package addon\recycle\app\service\admin\printer
 */
class RecyclePrintTaskService extends BaseAdminService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrintTask();
    }

    /**
     * 创建打印任务
     * @param array $plan
     * @param array $payload
     * @param string $mode
     * @return array
     */
    public function createFromPlan(array $plan, array $payload = [], string $mode = RecyclePrintTask::MODE_AUTO): array
    {
        $uniqueKey = $this->buildUniqueKey($plan, $mode);
        if ($uniqueKey !== '') {
            $exists = $this->model
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['unique_key', '=', $uniqueKey],
                ])
                ->findOrEmpty();

            if (!$exists->isEmpty()) {
                $existsData = $exists->toArray();
                if (in_array((int)($existsData['status'] ?? 0), [RecyclePrintTask::STATUS_FAIL, RecyclePrintTask::STATUS_CANCELLED], true)) {
                    return [
                        'created' => false,
                        'duplicate' => false,
                        'retry_existing' => true,
                        'task' => $existsData,
                    ];
                }

                return [
                    'created' => false,
                    'duplicate' => true,
                    'task' => $existsData,
                ];
            }
        }

        $scene = $plan['scene'] ?? [];
        $device = $plan['device'] ?? [];
        $template = $plan['template'] ?? [];
        $printer = $plan['printer'] ?? [];

        $task = $this->model->create([
            'site_id' => $this->site_id,
            'scene_key' => $scene['scene_key'] ?? '',
            'scene_name' => $scene['scene_name'] ?? '',
            'trigger_key' => $scene['trigger_key'] ?? '',
            'biz_type' => 'device',
            'biz_id' => (int)($device['device_id'] ?? 0),
            'order_id' => (int)($device['order_id'] ?? 0),
            'device_id' => (int)($device['device_id'] ?? 0),
            'template_id' => (int)($template['template_id'] ?? 0),
            'template_name' => $template['template_name'] ?? '',
            'printer_id' => (int)($printer['printer_id'] ?? 0),
            'printer_name' => $printer['printer_name'] ?? '',
            'copies' => max(1, min(20, (int)($plan['copies'] ?? 1))),
            'priority' => (int)($plan['priority'] ?? 100),
            'mode' => $mode,
            'unique_key' => $uniqueKey !== '' ? $uniqueKey : $this->buildOneTimeUniqueKey($plan, $mode),
            'payload' => $payload,
            'variables_snapshot' => $plan['device_data'] ?? [],
            'instruction_snapshot' => $plan['template_info']['instruction_content'] ?? '',
            'response_snapshot' => [],
            'status' => RecyclePrintTask::STATUS_PENDING,
            'max_attempts' => (int)($scene['max_attempts'] ?? 3),
            'operator_uid' => $this->uid ?? 0,
        ]);

        return [
            'created' => true,
            'stable_unique_key' => $uniqueKey !== '',
            'duplicate' => false,
            'task' => $task->toArray(),
        ];
    }

    /**
     * 更新任务为执行中
     * @param int $taskId
     * @return void
     */
    public function markRunning(int $taskId): void
    {
        $this->model->where([
            ['task_id', '=', $taskId],
            ['site_id', '=', $this->site_id],
        ])->inc('attempts', 1)->update([
            'status' => RecyclePrintTask::STATUS_RUNNING,
            'update_time' => time(),
        ]);
    }

    /**
     * 完成任务
     * @param int $taskId
     * @param array $response
     * @return void
     */
    public function finish(int $taskId, array $response): void
    {
        $success = !empty($response['success']);
        $this->model->where([
            ['task_id', '=', $taskId],
            ['site_id', '=', $this->site_id],
        ])->update([
            'status' => $success ? RecyclePrintTask::STATUS_SUCCESS : RecyclePrintTask::STATUS_FAIL,
            'fail_reason' => $success ? '' : (string)($response['message'] ?? '打印失败'),
            'response_snapshot' => $response,
            'finish_time' => $success ? time() : 0,
            'update_time' => time(),
        ]);
    }

    /**
     * 标记跳过
     * @param array $task
     * @param string $message
     * @return array
     */
    public function skippedResult(array $task, string $message): array
    {
        return [
            'success' => true,
            'skipped' => true,
            'message' => $message,
            'task' => $task,
        ];
    }

    /**
     * 构建幂等键
     * @param array $plan
     * @param string $mode
     * @return string
     */
    public function buildUniqueKey(array $plan, string $mode = RecyclePrintTask::MODE_AUTO): string
    {
        if ($mode !== RecyclePrintTask::MODE_AUTO) {
            return '';
        }

        $scene = $plan['scene'] ?? [];
        $device = $plan['device'] ?? [];
        $scope = $scene['idempotency_scope'] ?? 'site_scene_biz';
        if ($scope === 'none') {
            return '';
        }

        $sceneKey = (string)($scene['scene_key'] ?? '');
        if ($sceneKey === '') {
            return '';
        }

        if ($scope === 'site_scene_device') {
            $deviceId = (int)($device['device_id'] ?? 0);
            return $deviceId > 0 ? "scene:{$sceneKey}|device:{$deviceId}" : '';
        }

        if ($scope === 'site_scene_order') {
            $orderId = (int)($device['order_id'] ?? 0);
            return $orderId > 0 ? "scene:{$sceneKey}|order:{$orderId}" : '';
        }

        $bizType = 'device';
        $bizId = (int)($device['device_id'] ?? 0);
        return $bizId > 0 ? "scene:{$sceneKey}|biz:{$bizType}:{$bizId}" : '';
    }

    private function buildOneTimeUniqueKey(array $plan, string $mode): string
    {
        $sceneKey = (string)($plan['scene']['scene_key'] ?? 'scene');
        return "once:{$mode}:{$sceneKey}:" . date('YmdHis') . ':' . str_replace('.', '', uniqid('', true));
    }
}
