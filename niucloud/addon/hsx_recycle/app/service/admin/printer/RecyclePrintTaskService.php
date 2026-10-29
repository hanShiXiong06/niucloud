<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer;

use addon\hsx_recycle\app\model\printer\RecyclePrintTask;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 回收打印任务服务
 * Class RecyclePrintTaskService
 * @package addon\hsx_recycle\app\service\admin\printer
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
     * @param bool $reuseAutomatic 最终确认的兜底触发不重复已受理/成功的自动任务
     * @return array
     */
    public function createFromPlan(array $plan, array $payload = [], string $mode = RecyclePrintTask::MODE_AUTO, bool $reuseAutomatic = false): array
    {
        $deviceId = (int)($plan['device']['device_id'] ?? 0);
        if ($mode === RecyclePrintTask::MODE_AUTO && ($plan['scene']['trigger_key'] ?? '') === 'device.sign.saved' && $deviceId > 0) {
            // 逐台保存与最终签收可能并发。共用设备行锁，把“查已有任务＋创建pending”串行化；
            // 创建提交后才执行真正打印，不能将云打印网络请求放在数据库锁内。
            return Db::transaction(function () use ($plan, $payload, $mode, $reuseAutomatic, $deviceId): array {
                $device = RecycleDevice::where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->lock(true)->findOrEmpty();
                if ($device->isEmpty()) throw new CommonException('设备不存在，未发起标签打印');
                return $this->createTaskFromPlan($plan, $payload, $mode, $reuseAutomatic);
            });
        }
        return $this->createTaskFromPlan($plan, $payload, $mode, $reuseAutomatic);
    }

    private function createTaskFromPlan(array $plan, array $payload, string $mode, bool $reuseAutomatic): array
    {
        if ($reuseAutomatic && $mode === RecyclePrintTask::MODE_AUTO) {
            $scene = $plan['scene'] ?? [];
            $biz = $plan['biz'] ?? [];
            $device = $plan['device'] ?? [];
            $bizType = (string)($biz['biz_type'] ?? $scene['biz_type'] ?? 'device');
            $bizId = (int)($biz['biz_id'] ?? $device['device_id'] ?? 0);
            if (!empty($scene['scene_key']) && $bizId > 0) {
                $where = [
                    ['site_id', '=', $this->site_id],
                    ['scene_key', '=', $scene['scene_key']],
                    ['trigger_key', '=', $scene['trigger_key'] ?? ''],
                    ['mode', '=', RecyclePrintTask::MODE_AUTO],
                    ['biz_type', '=', $bizType],
                    ['biz_id', '=', $bizId],
                    ['status', 'in', [RecyclePrintTask::STATUS_PENDING, RecyclePrintTask::STATUS_RUNNING, RecyclePrintTask::STATUS_SUCCESS]],
                ];
                if ($bizType === 'device') $where[] = ['device_id', '=', (int)($device['device_id'] ?? $bizId)];
                $exists = $this->model->where($where)->findOrEmpty();
                if (!$exists->isEmpty()) {
                    return ['created' => false, 'duplicate' => true, 'task' => $exists->toArray()];
                }
            }
        }

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
        $biz = $plan['biz'] ?? [];
        $device = $plan['device'] ?? [];
        $template = $plan['template'] ?? [];
        $printer = $plan['printer'] ?? [];
        $bizType = (string)($biz['biz_type'] ?? $scene['biz_type'] ?? 'device');
        $bizId = (int)($biz['biz_id'] ?? $device['device_id'] ?? 0);

        $task = $this->model->create([
            'site_id' => $this->site_id,
            'scene_key' => $scene['scene_key'] ?? '',
            'scene_name' => $scene['scene_name'] ?? '',
            'trigger_key' => $scene['trigger_key'] ?? '',
            'biz_type' => $bizType,
            'biz_id' => $bizId,
            'order_id' => (int)($biz['order_id'] ?? $device['order_id'] ?? 0),
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
            'variables_snapshot' => $plan['print_data'] ?? $plan['device_data'] ?? [],
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
        $biz = $plan['biz'] ?? [];
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
            $orderId = (int)($biz['order_id'] ?? $device['order_id'] ?? 0);
            return $orderId > 0 ? "scene:{$sceneKey}|order:{$orderId}" : '';
        }

        $bizType = (string)($biz['biz_type'] ?? $scene['biz_type'] ?? 'device');
        $bizId = (int)($biz['biz_id'] ?? $device['device_id'] ?? 0);
        return $bizId > 0 ? "scene:{$sceneKey}|biz:{$bizType}:{$bizId}" : '';
    }

    private function buildOneTimeUniqueKey(array $plan, string $mode): string
    {
        $sceneKey = (string)($plan['scene']['scene_key'] ?? 'scene');
        return "once:{$mode}:{$sceneKey}:" . date('YmdHis') . ':' . str_replace('.', '', uniqid('', true));
    }
}
