<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer;

use addon\recycle\app\model\printer\RecyclePrintLog;
use addon\recycle\app\model\printer\RecyclePrintScene;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 回收打印场景服务
 * Class RecyclePrintSceneService
 * @package addon\recycle\app\service\admin\printer
 */
class RecyclePrintSceneService extends BaseAdminService
{
    protected $model;
    protected $templateService;
    protected $logService;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrintScene();
        $this->templateService = new RecyclePrinterTemplateService();
        $this->logService = new RecyclePrintLogService();
    }

    /**
     * 获取场景配置列表
     * @return array
     */
    public function getList(): array
    {
        $this->ensureBuiltinScenes();

        $rows = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->order('sort asc, scene_id asc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row = $this->appendSceneMeta($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * 获取场景详情
     * @param string $sceneKey
     * @return array
     */
    public function getInfo(string $sceneKey): array
    {
        $this->ensureBuiltinScenes();

        $info = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['scene_key', '=', $sceneKey],
            ])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new AdminException('打印场景不存在');
        }

        return $this->appendSceneMeta($info);
    }

    /**
     * 保存场景配置
     * @param string $sceneKey
     * @param array $data
     * @return bool
     */
    public function saveScene(string $sceneKey, array $data): bool
    {
        $this->ensureBuiltinScenes();

        $scene = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['scene_key', '=', $sceneKey],
            ])
            ->findOrEmpty();

        if ($scene->isEmpty()) {
            throw new AdminException('打印场景不存在');
        }

        $copies = max(1, min(20, (int)($data['copies'] ?? 1)));
        $payload = [
            'auto_print' => empty($data['auto_print']) ? 0 : 1,
            'template_id' => (int)($data['template_id'] ?? 0),
            'printer_id' => (int)($data['printer_id'] ?? 0),
            'copies' => $copies,
            'status' => empty($data['status']) ? 0 : 1,
            'sort' => (int)($data['sort'] ?? 0),
        ];

        if ($payload['template_id'] > 0) {
            $template = $this->templateService->getInfo($payload['template_id']);
            if (empty($template) || ($template['template_type'] ?? '') !== ($scene['template_type'] ?? '')) {
                throw new AdminException('选择的模板类型与打印场景不匹配');
            }
        }

        $scene->save($payload);
        return true;
    }

    /**
     * 切换场景状态
     * @param string $sceneKey
     * @param int $status
     * @return bool
     */
    public function modifyStatus(string $sceneKey, int $status): bool
    {
        $this->ensureBuiltinScenes();

        $scene = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['scene_key', '=', $sceneKey],
            ])
            ->findOrEmpty();

        if ($scene->isEmpty()) {
            throw new AdminException('打印场景不存在');
        }

        $scene->save(['status' => $status ? 1 : 0]);
        return true;
    }

    /**
     * 获取场景选项
     * @return array
     */
    public function getSceneOptions(): array
    {
        return array_values(RecyclePrintScene::sceneList());
    }

    /**
     * 手动设备标签打印计划
     * @param int $deviceId
     * @return array
     */
    public function resolveManualDeviceLabelPlan(int $deviceId): array
    {
        return $this->resolveDeviceScenePlan('manual_device_label', $deviceId, false);
    }

    /**
     * 执行手动设备标签打印
     * @param int $deviceId
     * @return array
     */
    public function printManualDeviceLabel(int $deviceId): array
    {
        $plan = $this->resolveManualDeviceLabelPlan($deviceId);
        return $this->executeDevicePrintPlan($plan);
    }

    /**
     * 质检完成后自动打印
     * @param int $deviceId
     * @return array
     */
    public function autoPrintAfterDeviceCheck(int $deviceId): array
    {
        $plan = $this->resolveDeviceScenePlan('device_check_complete', $deviceId, true);
        if (empty($plan['can_print'])) {
            $this->recordPlanLog($plan, 0, $plan['message'] ?? '打印计划不可用');
            return $plan;
        }

        if ($this->hasSuccessfulAutoPrintLog($plan)) {
            $message = '该设备已自动打印过，已跳过重复打印';
            $this->recordPlanLog($plan, 0, $message);
            return array_merge($plan, [
                'success' => true,
                'skipped' => true,
                'message' => $message,
            ]);
        }

        return $this->executeDevicePrintPlan($plan);
    }

    /**
     * 根据场景解析设备打印计划
     * @param string $sceneKey
     * @param int $deviceId
     * @param bool $requireAuto
     * @return array
     */
    public function resolveDeviceScenePlan(string $sceneKey, int $deviceId, bool $requireAuto = false): array
    {
        try {
            $scene = $this->getInfo($sceneKey);
            if ((int)$scene['status'] !== 1) {
                return $this->disabledPlan($scene, '打印场景未启用');
            }
            if ($requireAuto && (int)$scene['auto_print'] !== 1) {
                return $this->disabledPlan($scene, '自动打印未开启');
            }

            $templateId = (int)($scene['template_id'] ?? 0);
            if ($templateId <= 0) {
                $template = $this->templateService->getDefaultTemplate($scene['template_type']);
                $templateId = (int)($template['template_id'] ?? 0);
            }
            if ($templateId <= 0) {
                throw new AdminException('未配置可用打印模板，请先选择模板或设置默认模板');
            }

            $templateInfo = $this->templateService->getInfo($templateId);
            if (empty($templateInfo)) {
                throw new AdminException('打印模板不存在');
            }
            if (($templateInfo['template_type'] ?? '') !== $scene['template_type']) {
                throw new AdminException('打印模板类型与场景不匹配');
            }
            if (empty($templateInfo['instruction_content'])) {
                throw new AdminException('打印模板缺少打印指令内容');
            }

            $deviceData = $this->templateService->getDevicePrintData($deviceId);
            $bindPrinterId = (int)($scene['printer_id'] ?? 0);
            if ($bindPrinterId <= 0) {
                $bindPrinterId = (int)($templateInfo['printer_id'] ?? 0);
            }
            $printer = $this->templateService->getDefaultPrinter($bindPrinterId);
            if (empty($printer)) {
                throw new AdminException($bindPrinterId > 0 ? '场景或模板绑定的打印机未启用或不存在' : '当前账号未绑定可用打印机');
            }

            return [
                'can_print' => true,
                'message' => '打印计划已就绪',
                'scene' => [
                    'scene_key' => $scene['scene_key'],
                    'scene_name' => $scene['scene_name'],
                    'trigger_name' => $scene['trigger_name'] ?? '',
                    'auto_print' => (int)$scene['auto_print'],
                ],
                'device' => [
                    'device_id' => (int)$deviceData['device_id'],
                    'order_id' => (int)($deviceData['order_id'] ?? 0),
                    'order_no' => $deviceData['order_no'] ?? '',
                    'model' => $deviceData['model'] ?? '',
                    'imei' => $deviceData['imei'] ?? '',
                    'sn' => $deviceData['sn'] ?? '',
                    'status_name' => $deviceData['status_name'] ?? '',
                    'device_number' => $deviceData['device_number'] ?? '',
                ],
                'template' => [
                    'template_id' => (int)$templateInfo['template_id'],
                    'template_name' => $templateInfo['template_name'] ?? '',
                    'template_type' => $templateInfo['template_type'] ?? '',
                    'template_type_name' => $templateInfo['type_name'] ?? '',
                ],
                'printer' => [
                    'printer_id' => (int)$printer['printer_id'],
                    'printer_name' => $printer['printer_name'] ?? '',
                    'sn' => $printer['sn'] ?? '',
                    'brand' => $printer['brand'] ?? '',
                    'type' => $printer['type'] ?? '',
                ],
                'copies' => max(1, min(20, (int)($scene['copies'] ?? 1))),
                'template_info' => $templateInfo,
                'printer_info' => $printer,
                'device_data' => $deviceData,
            ];
        } catch (AdminException $e) {
            return [
                'can_print' => false,
                'message' => $e->getMessage(),
                'scene' => [
                    'scene_key' => $sceneKey,
                    'scene_name' => RecyclePrintScene::getSceneName($sceneKey),
                ],
                'device' => [
                    'device_id' => $deviceId,
                ],
            ];
        }
    }

    /**
     * 执行设备打印计划
     * @param array $plan
     * @return array
     */
    public function executeDevicePrintPlan(array $plan): array
    {
        if (empty($plan['can_print'])) {
            $this->recordPlanLog($plan, 0, $plan['message'] ?? '打印计划不可用');
            return $plan;
        }

        $templateInfo = $plan['template_info'] ?? [];
        $deviceData = $plan['device_data'] ?? [];
        $printer = $plan['printer_info'] ?? [];
        $copies = max(1, min(20, (int)($plan['copies'] ?? 1)));
        $results = [];
        $success = true;
        $message = '打印成功';

        for ($i = 0; $i < $copies; $i++) {
            $result = $this->templateService->printWithTemplateData($templateInfo, $deviceData, $printer);
            $results[] = $result;
            if (empty($result['success'])) {
                $success = false;
                $message = $result['message'] ?? '打印失败';
                break;
            }
        }

        $response = [
            'success' => $success,
            'message' => $success ? '标签打印成功' : $message,
            'scene' => $plan['scene'],
            'device' => $plan['device'],
            'template' => $plan['template'],
            'printer' => $plan['printer'],
            'copies' => $copies,
            'results' => $results,
        ];

        $this->recordPlanLog($plan, $success ? 1 : 0, $response['message'], $response);
        return $response;
    }

    /**
     * 创建内置场景
     * @return void
     */
    public function ensureBuiltinScenes(): void
    {
        foreach (RecyclePrintScene::sceneList() as $scene) {
            $exists = $this->model
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['scene_key', '=', $scene['scene_key']],
                ])
                ->findOrEmpty();

            if (!$exists->isEmpty()) {
                continue;
            }

            $this->model->create([
                'site_id' => $this->site_id,
                'scene_key' => $scene['scene_key'],
                'scene_name' => $scene['scene_name'],
                'biz_type' => $scene['biz_type'],
                'template_type' => $scene['template_type'],
                'auto_print' => $scene['auto_print'],
                'template_id' => 0,
                'printer_id' => 0,
                'copies' => 1,
                'status' => 1,
                'sort' => 0,
            ]);
        }
    }

    /**
     * 补充场景展示信息
     * @param array $row
     * @return array
     */
    private function appendSceneMeta(array $row): array
    {
        $builtin = RecyclePrintScene::sceneList()[$row['scene_key']] ?? [];
        $row['trigger_name'] = $builtin['trigger_name'] ?? '';
        $row['description'] = $builtin['description'] ?? '';
        $row['template_type_name'] = $this->templateService->getTypeList()[$row['template_type']] ?? $row['template_type'];
        $row['auto_print_name'] = (int)$row['auto_print'] === 1 ? '自动打印' : '手动确认';
        $row['status_name'] = (int)$row['status'] === 1 ? '启用' : '停用';

        if (!empty($row['template_id'])) {
            try {
                $template = $this->templateService->getInfo((int)$row['template_id']);
                $row['template_name'] = $template['template_name'] ?? '';
            } catch (\Throwable $e) {
                $row['template_name'] = '指定模板不可用';
            }
        } else {
            $row['template_name'] = '使用默认模板';
        }

        if (!empty($row['printer_id'])) {
            $printer = $this->templateService->getDefaultPrinter((int)$row['printer_id']);
            $row['printer_name'] = $printer['printer_name'] ?? '指定打印机不可用';
        } else {
            $row['printer_name'] = '模板绑定或当前账号默认打印机';
        }

        return $row;
    }

    /**
     * 返回未执行计划
     * @param array $scene
     * @param string $message
     * @return array
     */
    private function disabledPlan(array $scene, string $message): array
    {
        return [
            'can_print' => false,
            'message' => $message,
            'scene' => [
                'scene_key' => $scene['scene_key'] ?? '',
                'scene_name' => $scene['scene_name'] ?? '',
            ],
        ];
    }

    /**
     * 判断当前设备在自动场景下是否已经成功打印过
     * @param array $plan
     * @return bool
     */
    private function hasSuccessfulAutoPrintLog(array $plan): bool
    {
        $sceneKey = (string)($plan['scene']['scene_key'] ?? '');
        $deviceId = (int)($plan['device']['device_id'] ?? 0);

        if ($sceneKey === '' || $deviceId <= 0) {
            return false;
        }

        return RecyclePrintLog::where([
            ['site_id', '=', $this->site_id],
            ['scene_key', '=', $sceneKey],
            ['device_id', '=', $deviceId],
            ['status', '=', 1],
        ])->count() > 0;
    }

    /**
     * 记录计划日志
     * @param array $plan
     * @param int $status
     * @param string $message
     * @param array $response
     * @return void
     */
    private function recordPlanLog(array $plan, int $status, string $message, array $response = []): void
    {
        $this->logService->record([
            'scene_key' => $plan['scene']['scene_key'] ?? '',
            'scene_name' => $plan['scene']['scene_name'] ?? '',
            'biz_type' => 'device',
            'biz_id' => (int)($plan['device']['device_id'] ?? 0),
            'order_id' => (int)($plan['device']['order_id'] ?? 0),
            'device_id' => (int)($plan['device']['device_id'] ?? 0),
            'template_id' => (int)($plan['template']['template_id'] ?? 0),
            'template_name' => $plan['template']['template_name'] ?? '',
            'printer_id' => (int)($plan['printer']['printer_id'] ?? 0),
            'printer_name' => $plan['printer']['printer_name'] ?? '',
            'copies' => (int)($plan['copies'] ?? 1),
            'status' => $status,
            'message' => $message,
            'plan_snapshot' => $this->cleanPlanForLog($plan),
            'response_snapshot' => $response,
        ]);
    }

    /**
     * 移除日志中的敏感字段和大字段
     * @param array $plan
     * @return array
     */
    private function cleanPlanForLog(array $plan): array
    {
        unset($plan['printer_info']['user_key'], $plan['printer_info']['user_name']);
        unset($plan['template_info']['instruction_content'], $plan['template_info']['content'], $plan['template_info']['html_content']);
        unset($plan['device_data']['check_result'], $plan['device_data']['check_result_seller'], $plan['device_data']['check_result_buyer']);
        return $plan;
    }
}
