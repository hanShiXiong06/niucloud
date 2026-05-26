<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer;

use addon\hsx_recycle\app\model\printer\RecyclePrintScene;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\model\order\RecycleReturnOrder;
use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\order\RecycleReturnOrderDict;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 回收打印场景服务
 * Class RecyclePrintSceneService
 * @package addon\hsx_recycle\app\service\admin\printer
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
            if ($this->isObsoleteScene((string)($row['scene_key'] ?? ''))) {
                $row = null;
                continue;
            }
            $row = $this->appendSceneMeta($row);
        }
        unset($row);

        return array_values(array_filter($rows));
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
            'trigger_key' => (string)($data['trigger']['key'] ?? $scene['trigger_key'] ?? ''),
            'template_id' => (int)($data['template_id'] ?? 0),
            'printer_id' => (int)($data['printer_id'] ?? 0),
            'copies' => $copies,
            'status' => empty($data['status']) ? 0 : 1,
            'sort' => (int)($data['sort'] ?? 0),
            'idempotency_scope' => $this->normalizeIdempotencyScope((string)($data['idempotency_scope'] ?? $scene['idempotency_scope'] ?? 'site_scene_biz')),
            'retry_enabled' => empty($data['retry_enabled']) ? 0 : 1,
            'max_attempts' => max(1, min(10, (int)($data['max_attempts'] ?? $scene['max_attempts'] ?? 3))),
            'condition_config' => $this->encodeConditionConfig($data, $scene->toArray()),
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
        return [
            'biz_type_options' => $this->getBizTypeOptions(),
            'template_type_options' => $this->formatMapOptions($this->templateService->getTypeList()),
            'trigger_options' => $this->getTriggerOptions(''),
            'button_position_options' => $this->getButtonPositionOptions(),
            'device_status_options' => $this->getDeviceStatusOptions(),
            'status_options' => $this->getBizStatusOptions(),
            'idempotency_scope_options' => $this->formatMapOptions($this->getIdempotencyScopeList()),
            'print_variable_options' => $this->getPrintVariableOptions(),
            'builtin_scenes' => array_values(RecyclePrintScene::sceneList()),
        ];
    }

    /**
     * 添加自定义场景
     * @param array $data
     * @return array
     */
    public function addScene(array $data): array
    {
        $this->ensureBuiltinScenes();

        $sceneName = trim((string)($data['scene_name'] ?? ''));
        if ($sceneName === '') {
            throw new AdminException('请输入场景名称');
        }

        $bizType = (string)($data['biz_type'] ?? 'device');
        $templateType = (string)($data['template_type'] ?? $this->getDefaultTemplateTypeByBiz($bizType));
        if (!array_key_exists($templateType, $this->templateService->getTypeList())) {
            throw new AdminException('模板类型不正确');
        }
        if (!array_key_exists($bizType, $this->getBizTypeMap())) {
            throw new AdminException('业务类型不正确');
        }

        $sceneKey = $this->makeCustomSceneKey();
        $trigger = is_array($data['trigger'] ?? null) ? $data['trigger'] : [];
        if (empty($trigger['key'])) {
            $triggerOptions = $this->getTriggerOptions($bizType);
            $trigger['key'] = $triggerOptions[0]['key'] ?? '';
            $trigger['name'] = $triggerOptions[0]['name'] ?? '';
        }

        $scene = [
            'scene_key' => $sceneKey,
            'trigger_key' => (string)($trigger['key'] ?? ''),
            'scene_name' => $sceneName,
            'biz_type' => $bizType,
            'template_type' => $templateType,
            'auto_print' => empty($data['auto_print']) ? 0 : 1,
            'idempotency_scope' => $this->normalizeIdempotencyScope((string)($data['idempotency_scope'] ?? 'site_scene_device')),
            'condition_config' => '',
        ];

        $this->model->create([
            'site_id' => $this->site_id,
            'scene_key' => $sceneKey,
            'trigger_key' => $scene['trigger_key'],
            'scene_name' => $sceneName,
            'biz_type' => $bizType,
            'template_type' => $templateType,
            'auto_print' => (int)$scene['auto_print'],
            'idempotency_scope' => $scene['idempotency_scope'],
            'retry_enabled' => empty($data['retry_enabled']) ? 0 : 1,
            'max_attempts' => max(1, min(10, (int)($data['max_attempts'] ?? 3))),
            'condition_config' => $this->encodeConditionConfig($data, $scene),
            'template_id' => (int)($data['template_id'] ?? 0),
            'printer_id' => (int)($data['printer_id'] ?? 0),
            'copies' => max(1, min(20, (int)($data['copies'] ?? 1))),
            'status' => empty($data['status']) ? 0 : 1,
            'sort' => (int)($data['sort'] ?? 0),
        ]);

        return $this->getInfo($sceneKey);
    }

    /**
     * 删除自定义场景
     * @param string $sceneKey
     * @return bool
     */
    public function deleteScene(string $sceneKey): bool
    {
        if ($this->isBuiltinScene($sceneKey)) {
            throw new AdminException('系统内置场景不能删除，可停用或修改配置');
        }

        $scene = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['scene_key', '=', $sceneKey],
            ])
            ->findOrEmpty();

        if ($scene->isEmpty()) {
            throw new AdminException('打印场景不存在');
        }

        $scene->delete();
        return true;
    }

    /**
     * 获取手动打印动作
     * @param string $bizType
     * @return array
     */
    public function getManualActions(string $bizType = 'device'): array
    {
        $this->ensureBuiltinScenes();

        $rows = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
                ['biz_type', '=', $bizType],
            ])
            ->order('sort asc, scene_id asc')
            ->select()
            ->toArray();

        $actions = [];
        foreach ($rows as $row) {
            if ($this->isObsoleteScene((string)($row['scene_key'] ?? ''))) {
                continue;
            }
            $row = $this->appendSceneMeta($row);
            $button = $row['button_config'] ?? [];
            if (empty($button['enabled'])) {
                continue;
            }
            $actions[] = [
                'scene_key' => $row['scene_key'],
                'scene_name' => $row['scene_name'],
                'button_text' => $button['text'] ?: $row['scene_name'],
                'button_position' => $button['position'] ?: 'device_actions',
                'visible_device_status' => array_values($button['visible_device_status'] ?? []),
                'visible_confirm_status' => array_values($button['visible_confirm_status'] ?? []),
                'visible_pay_status' => array_values($button['visible_pay_status'] ?? []),
                'confirm_required' => (int)($button['confirm_required'] ?? 1),
                'template_type' => $row['template_type'],
                'template_type_name' => $row['template_type_name'],
                'template_name' => $row['template_name'],
                'printer_name' => $row['printer_name'],
                'copies' => (int)$row['copies'],
                'sort' => (int)$row['sort'],
            ];
        }

        return $actions;
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
        return $this->printScene('manual_device_label', ['device_id' => $deviceId]);
    }

    /**
     * 按场景执行打印
     * @param string $sceneKey
     * @param array $payload
     * @return array
     */
    public function printScene(string $sceneKey, array $payload = []): array
    {
        return (new RecyclePrintTriggerService())->manual($sceneKey, $payload);
    }

    /**
     * 质检完成后自动打印
     * @param int $deviceId
     * @return array
     */
    public function autoPrintAfterDeviceCheck(int $deviceId): array
    {
        return (new RecyclePrintTriggerService())->auto('device.check.saved', [
            'device_id' => $deviceId,
        ]);
    }

    /**
     * 根据触发事件解析打印计划
     * @param string $triggerKey
     * @param array $payload
     * @param bool $requireAuto
     * @return array
     */
    public function resolvePlansByTrigger(string $triggerKey, array $payload = [], bool $requireAuto = false): array
    {
        $this->ensureBuiltinScenes();

        $scenes = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->order('sort asc, scene_id asc')
            ->select()
            ->toArray();

        $plans = [];
        foreach ($scenes as $scene) {
            if ($this->isObsoleteScene((string)($scene['scene_key'] ?? ''))) {
                continue;
            }
            if (!$this->sceneMatchesTrigger($scene, $triggerKey)) {
                continue;
            }
            $plans[] = $this->resolveScenePlan((string)$scene['scene_key'], $payload, $requireAuto, $scene);
        }

        if (empty($plans)) {
            $plans[] = $this->disabledPlan([
                'scene_key' => '',
                'scene_name' => $triggerKey,
                'trigger_key' => $triggerKey,
            ], '未配置可用打印场景');
        }

        return $plans;
    }

    /**
     * 根据场景标识解析打印计划
     * @param string $sceneKey
     * @param array $payload
     * @param bool $requireAuto
     * @return array
     */
    public function resolvePlanBySceneKey(string $sceneKey, array $payload = [], bool $requireAuto = false): array
    {
        return $this->resolveScenePlan($sceneKey, $payload, $requireAuto);
    }

    /**
     * 根据场景解析打印计划
     * @param string $sceneKey
     * @param array $payload
     * @param bool $requireAuto
     * @param array $sceneData
     * @return array
     */
    public function resolveScenePlan(string $sceneKey, array $payload = [], bool $requireAuto = false, array $sceneData = []): array
    {
        try {
            $scene = !empty($sceneData) ? $this->appendSceneMeta($sceneData) : $this->getInfo($sceneKey);
            if ((int)$scene['status'] !== 1) {
                return $this->disabledPlan($scene, '打印场景未启用');
            }
            if ($requireAuto && (int)$scene['auto_print'] !== 1) {
                return $this->disabledPlan($scene, '自动打印未开启');
            }
            if (!$requireAuto && !$this->isManualSceneVisible($scene, $payload)) {
                return $this->disabledPlan($scene, '当前业务状态不满足手动打印按钮展示规则');
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

            $bizInfo = $this->resolveBizPrintData((string)($scene['biz_type'] ?? 'device'), $payload);
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
                    'trigger_key' => $scene['trigger_key'] ?? '',
                    'trigger_name' => $scene['trigger_name'] ?? '',
                    'execution_mode' => $scene['execution_mode'] ?? '',
                    'button_text' => $scene['button_config']['text'] ?? '',
                    'auto_print' => (int)$scene['auto_print'],
                    'idempotency_scope' => $scene['idempotency_scope'] ?? 'site_scene_biz',
                    'max_attempts' => (int)($scene['max_attempts'] ?? 3),
                    'biz_type' => $scene['biz_type'] ?? 'device',
                ],
                'biz' => $bizInfo['summary'],
                'device' => $bizInfo['device'],
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
                'device_data' => $bizInfo['print_data'],
                'print_data' => $bizInfo['print_data'],
            ];
        } catch (AdminException $e) {
            return [
                'can_print' => false,
                'message' => $e->getMessage(),
                'scene' => [
                    'scene_key' => $sceneKey,
                    'scene_name' => RecyclePrintScene::getSceneName($sceneKey),
                ],
                'biz' => [
                    'biz_type' => $payload['biz_type'] ?? '',
                    'biz_id' => (int)($payload['biz_id'] ?? $payload['device_id'] ?? $payload['order_id'] ?? $payload['return_order_id'] ?? $payload['consignment_id'] ?? 0),
                ],
            ];
        }
    }

    /**
     * 根据场景解析设备打印计划
     * @param string $sceneKey
     * @param int $deviceId
     * @param bool $requireAuto
     * @return array
     */
    public function resolveDeviceScenePlan(string $sceneKey, int $deviceId, bool $requireAuto = false, array $sceneData = []): array
    {
        return $this->resolveScenePlan($sceneKey, ['device_id' => $deviceId], $requireAuto, $sceneData);
    }

    /**
     * 执行打印计划
     * @param array $plan
     * @return array
     */
    public function executePrintPlan(array $plan): array
    {
        if (empty($plan['can_print'])) {
            $this->recordPlanLog($plan, 0, $plan['message'] ?? '打印计划不可用');
            return $plan;
        }

        $templateInfo = $plan['template_info'] ?? [];
        $printData = $plan['print_data'] ?? $plan['device_data'] ?? [];
        $printer = $plan['printer_info'] ?? [];
        $copies = max(1, min(20, (int)($plan['copies'] ?? 1)));
        $results = [];
        $success = true;
        $message = '打印成功';

        for ($i = 0; $i < $copies; $i++) {
            $result = $this->templateService->printWithTemplateData($templateInfo, $printData, $printer);
            $results[] = $result;
            if (empty($result['success'])) {
                $success = false;
                $message = $result['message'] ?? '打印失败';
                break;
            }
        }

        $response = [
            'success' => $success,
            'message' => $success ? '打印成功' : $message,
            'scene' => $plan['scene'],
            'biz' => $plan['biz'] ?? [],
            'device' => $plan['device'] ?? [],
            'template' => $plan['template'],
            'printer' => $plan['printer'],
            'copies' => $copies,
            'results' => $results,
        ];

        $this->recordPlanLog($plan, $success ? 1 : 0, $response['message'], $response);
        return $response;
    }

    /**
     * 执行设备打印计划
     * @param array $plan
     * @return array
     */
    public function executeDevicePrintPlan(array $plan): array
    {
        return $this->executePrintPlan($plan);
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
                $builtin = RecyclePrintScene::sceneList()[$scene['scene_key']] ?? [];
                $patch = [];
                if ((($exists['trigger_key'] ?? '') === '' || ($exists['trigger_key'] ?? '') === 'device.label.manual') && !empty($builtin['trigger_key'])) {
                    $patch['trigger_key'] = $builtin['trigger_key'];
                }
                if (($exists['idempotency_scope'] ?? '') === '' && !empty($builtin['idempotency_scope'])) {
                    $patch['idempotency_scope'] = $builtin['idempotency_scope'];
                }
                if (($exists['condition_config'] ?? '') === '' && !empty($builtin['condition_config'])) {
                    $patch['condition_config'] = json_encode($builtin['condition_config'], JSON_UNESCAPED_UNICODE);
                }
                if (($exists['scene_name'] ?? '') === '手动打印设备标签') {
                    $patch['scene_name'] = $builtin['scene_name'];
                }
                if (!empty($patch)) {
                    $exists->save($patch);
                }
                continue;
            }

            $this->model->create([
                'site_id' => $this->site_id,
                'scene_key' => $scene['scene_key'],
                'trigger_key' => $scene['trigger_key'] ?? '',
                'scene_name' => $scene['scene_name'],
                'biz_type' => $scene['biz_type'],
                'template_type' => $scene['template_type'],
                'auto_print' => $scene['auto_print'],
                'idempotency_scope' => $scene['idempotency_scope'] ?? 'site_scene_biz',
                'retry_enabled' => 1,
                'max_attempts' => 3,
                'condition_config' => json_encode($scene['condition_config'] ?? [], JSON_UNESCAPED_UNICODE),
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
        $row['trigger_key'] = $row['trigger_key'] ?? ($builtin['trigger_key'] ?? '');
        $row['is_builtin'] = $this->isBuiltinScene((string)($row['scene_key'] ?? '')) ? 1 : 0;
        $row['trigger_options'] = $builtin['trigger_options'] ?? $this->getTriggerOptions((string)($row['biz_type'] ?? 'device'));
        $row['button_position_options'] = $this->getButtonPositionOptions();
        $row['device_status_options'] = $this->getDeviceStatusOptions();
        $row['idempotency_scope_options'] = $this->formatMapOptions($this->getIdempotencyScopeList());
        $row['description'] = $builtin['description'] ?? '自定义打印场景。可配置手动按钮和自动触发节点。';
        $row['condition_config'] = $this->decodeConditionConfig($row['condition_config'] ?? '', $builtin['condition_config'] ?? []);
        $row['condition_config']['trigger'] = $this->normalizeTriggerConfig($row['condition_config']['trigger'] ?? [], $row);
        $row['trigger_name'] = $this->getTriggerName($row['trigger_key'] ?? '', $row);
        $row['execution_mode'] = $this->resolveExecutionMode($row);
        $row['button_config'] = $this->normalizeButtonConfig($row['condition_config']['button'] ?? [], $row);
        $row['visibility_summary'] = $this->buildVisibilitySummary($row);
        $row['template_type_name'] = $this->templateService->getTypeList()[$row['template_type']] ?? $row['template_type'];
        $row['auto_print_name'] = $this->buildExecutionSummary($row);
        $row['status_name'] = (int)$row['status'] === 1 ? '启用' : '停用';
        $row['idempotency_scope'] = $row['idempotency_scope'] ?? ($builtin['idempotency_scope'] ?? 'site_scene_biz');
        $row['retry_enabled'] = (int)($row['retry_enabled'] ?? 1);
        $row['max_attempts'] = (int)($row['max_attempts'] ?? 3);
        $row['idempotency_scope_name'] = $this->getIdempotencyScopeList()[$row['idempotency_scope']] ?? $row['idempotency_scope'];

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

    private function getBizTypeMap(): array
    {
        return [
            'device' => '设备',
            'order' => '订单',
            'return' => '退货',
            'consignment' => '代卖',
        ];
    }

    private function getBizTypeOptions(): array
    {
        return $this->formatMapOptions($this->getBizTypeMap());
    }

    private function getDefaultTemplateTypeByBiz(string $bizType): string
    {
        return [
            'device' => 'device_label',
            'order' => 'order_receipt',
            'return' => 'return_label',
            'consignment' => 'consignment_receipt',
        ][$bizType] ?? 'device_label';
    }

    public function getIdempotencyScopeList(): array
    {
        return [
            'none' => '不限制重复打印',
            'site_scene_biz' => '同一业务同一场景只自动打印一次',
            'site_scene_device' => '同一设备同一场景只自动打印一次',
            'site_scene_order' => '同一订单同一场景只自动打印一次',
        ];
    }

    private function normalizeIdempotencyScope(string $scope): string
    {
        return array_key_exists($scope, $this->getIdempotencyScopeList()) ? $scope : 'site_scene_biz';
    }

    private function encodeConditionConfig(array $data, array $scene): string
    {
        $current = $this->decodeConditionConfig($scene['condition_config'] ?? '', []);
        $button = $this->normalizeButtonConfig($data['button'] ?? ($current['button'] ?? []), $scene);
        if (isset($data['button']) && is_array($data['button'])) {
            $button = $this->normalizeButtonConfig($data['button'], $scene);
        }
        $trigger = $this->normalizeTriggerConfig($data['trigger'] ?? ($current['trigger'] ?? []), $scene);

        $config = $current;
        unset($config['execution_mode']);
        $config['button'] = $button;
        $config['trigger'] = $trigger;

        return json_encode($config, JSON_UNESCAPED_UNICODE);
    }

    private function decodeConditionConfig($rawConfig, array $fallback = []): array
    {
        if (is_array($rawConfig)) {
            return array_merge($fallback, $rawConfig);
        }
        if (!is_string($rawConfig) || trim($rawConfig) === '') {
            return $fallback;
        }
        $decoded = json_decode($rawConfig, true);
        return is_array($decoded) ? array_merge($fallback, $decoded) : $fallback;
    }

    private function resolveExecutionMode(array $scene): string
    {
        $button = $scene['button_config'] ?? $this->normalizeButtonConfig($scene['condition_config']['button'] ?? [], $scene);
        $manual = !empty($button['enabled']);
        $auto = (int)($scene['auto_print'] ?? 0) === 1;
        if ($manual && $auto) {
            return 'both';
        }
        if ($auto) {
            return 'auto';
        }
        if ($manual) {
            return 'manual';
        }
        return 'disabled';
    }

    private function normalizeTriggerConfig(array $trigger, array $scene): array
    {
        $key = (string)($trigger['key'] ?? $scene['trigger_key'] ?? '');
        if ($key === 'device.label.manual') {
            $key = RecyclePrintScene::sceneList()[$scene['scene_key']]['trigger_key'] ?? '';
        }
        return [
            'key' => $key,
            'name' => $this->getTriggerName($key, $scene),
        ];
    }

    private function getTriggerName(string $triggerKey, array $scene): string
    {
        foreach (($scene['trigger_options'] ?? []) as $option) {
            if (($option['key'] ?? '') === $triggerKey) {
                return (string)($option['name'] ?? $triggerKey);
            }
        }
        $builtin = RecyclePrintScene::sceneList()[$scene['scene_key'] ?? ''] ?? [];
        if (($builtin['trigger_key'] ?? '') === $triggerKey) {
            return (string)($builtin['trigger_name'] ?? $triggerKey);
        }
        foreach ($this->getTriggerOptions((string)($scene['biz_type'] ?? 'device')) as $option) {
            if (($option['key'] ?? '') === $triggerKey) {
                return (string)($option['name'] ?? $triggerKey);
            }
        }
        return $triggerKey;
    }

    private function buildExecutionSummary(array $scene): string
    {
        $parts = [];
        if (!empty($scene['button_config']['enabled'])) {
            $parts[] = '手动按钮';
        }
        if ((int)($scene['auto_print'] ?? 0) === 1) {
            $parts[] = '自动打印';
        }
        return empty($parts) ? '未开启执行入口' : implode(' + ', $parts);
    }

    private function normalizeButtonConfig(array $button, array $scene): array
    {
        $defaultStatuses = $button['visible_device_status'] ?? [];
        if (empty($defaultStatuses) && ($scene['scene_key'] ?? '') === 'manual_device_label') {
            $defaultStatuses = [2, 3, 4, 5];
        }

        return [
            'enabled' => empty($button['enabled']) ? 0 : 1,
            'text' => trim((string)($button['text'] ?? $scene['scene_name'] ?? '打印')),
            'position' => (string)($button['position'] ?? 'device_actions'),
            'visible_device_status' => $this->normalizeIntList($defaultStatuses),
            'visible_confirm_status' => $this->normalizeIntList($button['visible_confirm_status'] ?? []),
            'visible_pay_status' => $this->normalizeIntList($button['visible_pay_status'] ?? []),
            'confirm_required' => array_key_exists('confirm_required', $button) ? (empty($button['confirm_required']) ? 0 : 1) : 1,
        ];
    }

    private function normalizeIntList($value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)), static fn($item) => $item !== '');
        }
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            $num = (int)$item;
            if (!in_array($num, $list, true)) {
                $list[] = $num;
            }
        }
        return $list;
    }

    private function buildVisibilitySummary(array $scene): string
    {
        $button = $scene['button_config'] ?? [];
        $statuses = $button['visible_device_status'] ?? [];
        $bizType = (string)($scene['biz_type'] ?? 'device');
        $statusMap = $this->getStatusNameMap($bizType);
        $names = [];
        foreach ($statuses as $status) {
            $names[] = $statusMap[(int)$status] ?? (string)$status;
        }
        $parts = [];
        $bizLabel = $this->getBizTypeMap()[$bizType] ?? '业务';
        $parts[] = empty($names) ? '手动按钮：未配置展示状态' : '手动按钮：' . $bizLabel . '处于【' . implode('、', $names) . '】时显示';
        $parts[] = (int)($scene['auto_print'] ?? 0) === 1
            ? '自动节点：' . ($scene['trigger_name'] ?? $scene['trigger_key'] ?? '')
            : '自动节点：未开启';
        return implode('；', $parts);
    }

    private function getStatusNameMap(string $bizType): array
    {
        if ($bizType === 'order') {
            $map = [];
            foreach (RecycleOrderDict::getOrderStatus('') as $value => $item) {
                $map[(int)$value] = is_array($item) ? (string)($item['name'] ?? $value) : (string)$item;
            }
            return $map;
        }

        if ($bizType === 'return') {
            $map = [];
            foreach (RecycleReturnOrderDict::getOrderStatusList() as $value => $item) {
                $map[(int)$value] = (string)($item['name'] ?? $value);
            }
            return $map;
        }

        if ($bizType === 'consignment') {
            return RecycleConsignmentDict::getStatus();
        }

        return RecycleOrderDict::getDeviceStatus();
    }

    private function sceneMatchesTrigger(array $scene, string $triggerKey): bool
    {
        $builtin = RecyclePrintScene::sceneList()[$scene['scene_key'] ?? ''] ?? [];
        $scene['trigger_options'] = $builtin['trigger_options'] ?? $this->getTriggerOptions((string)($scene['biz_type'] ?? 'device'));
        $conditionConfig = $this->decodeConditionConfig($scene['condition_config'] ?? '', $builtin['condition_config'] ?? []);
        return ($conditionConfig['trigger']['key'] ?? $scene['trigger_key'] ?? '') === $triggerKey;
    }

    private function getTriggerOptions(string $bizType = 'device'): array
    {
        $options = [
            [
                'key' => 'device.check.saved',
                'name' => '质检保存后',
                'biz_type' => 'device',
                'description' => '管理端暂存或完成设备质检后触发。',
            ],
            [
                'key' => 'device.price.saved',
                'name' => '设备定价后',
                'biz_type' => 'device',
                'description' => '管理端保存设备报价后触发。仅绑定并开启自动打印的场景会执行。',
            ],
            [
                'key' => 'device.paid',
                'name' => '设备打款后',
                'biz_type' => 'device',
                'description' => '管理端完成单台或多台设备打款后触发。',
            ],
            [
                'key' => 'order.created',
                'name' => '订单创建后',
                'biz_type' => 'order',
                'description' => '回收订单创建成功后触发。',
            ],
            [
                'key' => 'order.signed',
                'name' => '订单签收后',
                'biz_type' => 'order',
                'description' => '订单签收或确认收货后触发。',
            ],
            [
                'key' => 'order.completed',
                'name' => '订单完成后',
                'biz_type' => 'order',
                'description' => '订单完成后触发。',
            ],
            [
                'key' => 'return.created',
                'name' => '退货单创建后',
                'biz_type' => 'return',
                'description' => '退货单创建成功后触发。',
            ],
            [
                'key' => 'return.confirmed',
                'name' => '确认退货后',
                'biz_type' => 'return',
                'description' => '后台确认退货、保存退货快递信息后触发。',
            ],
            [
                'key' => 'return.express.saved',
                'name' => '退货物流保存后',
                'biz_type' => 'return',
                'description' => '退货快递公司或快递单号保存后触发。',
            ],
            [
                'key' => 'return.completed',
                'name' => '退货完成后',
                'biz_type' => 'return',
                'description' => '后台完成退货流程后触发。',
            ],
            [
                'key' => 'consignment.created',
                'name' => '转入代卖后',
                'biz_type' => 'consignment',
                'description' => '设备从回收订单转入独立代卖订单后触发。',
            ],
            [
                'key' => 'consignment.listed',
                'name' => '代卖上架后',
                'biz_type' => 'consignment',
                'description' => '后台设置或更新挂牌价后触发。',
            ],
            [
                'key' => 'consignment.sold',
                'name' => '代卖成交后',
                'biz_type' => 'consignment',
                'description' => '后台登记成交价和客户结算金额后触发。',
            ],
            [
                'key' => 'consignment.settled',
                'name' => '代卖结算后',
                'biz_type' => 'consignment',
                'description' => '后台完成客户结算后触发。',
            ],
            [
                'key' => 'consignment.cancelled',
                'name' => '取消代卖后',
                'biz_type' => 'consignment',
                'description' => '后台取消代卖订单后触发。',
            ],
            [
                'key' => 'consignment.returned',
                'name' => '代卖退回后',
                'biz_type' => 'consignment',
                'description' => '后台将代卖设备退回客户后触发。',
            ],
        ];

        if ($bizType === '') {
            return $options;
        }
        return array_values(array_filter($options, static fn($item) => ($item['biz_type'] ?? '') === $bizType));
    }

    private function getButtonPositionOptions(): array
    {
        return [
            [
                'value' => 'device_actions',
                'label' => '设备列表操作区',
                'description' => '显示在订单设备列表的单台设备操作按钮中。',
            ],
            [
                'value' => 'order_actions',
                'label' => '订单列表操作区',
                'description' => '显示在订单列表或订单详情的操作按钮中。',
            ],
            [
                'value' => 'return_order_actions',
                'label' => '退货单操作区',
                'description' => '显示在退货单列表或退货详情的操作按钮中。',
            ],
            [
                'value' => 'consignment_order_actions',
                'label' => '代卖单操作区',
                'description' => '显示在代卖订单列表或代卖详情的操作按钮中。',
            ],
        ];
    }

    private function getBizStatusOptions(): array
    {
        return [
            'device' => $this->getDeviceStatusOptions(),
            'order' => $this->formatStatusOptions(RecycleOrderDict::getOrderStatus('')),
            'return' => $this->formatReturnStatusOptions(),
            'consignment' => $this->formatMapOptions(RecycleConsignmentDict::getStatus()),
        ];
    }

    private function getDeviceStatusOptions(): array
    {
        $options = [];
        foreach (RecycleOrderDict::getDeviceStatus() as $value => $label) {
            $options[] = [
                'value' => (int)$value,
                'label' => $label,
            ];
        }
        return $options;
    }

    private function formatMapOptions(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }
        return $options;
    }

    private function formatStatusOptions(array $map): array
    {
        $options = [];
        foreach ($map as $value => $item) {
            $options[] = [
                'value' => (int)$value,
                'label' => is_array($item) ? (string)($item['name'] ?? $value) : (string)$item,
            ];
        }
        return $options;
    }

    private function formatReturnStatusOptions(): array
    {
        $options = [];
        foreach (RecycleReturnOrderDict::getOrderStatusList() as $value => $item) {
            $options[] = [
                'value' => (int)$value,
                'label' => (string)($item['name'] ?? $value),
            ];
        }
        return $options;
    }

    private function resolveBizPrintData(string $bizType, array $payload): array
    {
        if ($bizType === 'order') {
            $orderId = (int)($payload['order_id'] ?? $payload['biz_id'] ?? 0);
            if ($orderId <= 0) {
                throw new AdminException('缺少订单ID，无法生成打印数据');
            }
            $printData = $this->templateService->getOrderPrintData($orderId);
            return [
                'summary' => [
                    'biz_type' => 'order',
                    'biz_id' => $orderId,
                    'order_id' => $orderId,
                    'title' => $printData['order_no'] ?? '',
                    'subtitle' => $printData['order_status_name'] ?? '',
                ],
                'device' => [
                    'device_id' => 0,
                    'order_id' => $orderId,
                    'order_no' => $printData['order_no'] ?? '',
                    'model' => $printData['first_device_model'] ?? '',
                    'imei' => $printData['first_device_imei'] ?? '',
                    'status_name' => $printData['order_status_name'] ?? '',
                    'device_number' => $printData['device_count'] ?? '',
                ],
                'print_data' => $printData,
            ];
        }

        if ($bizType === 'return') {
            $returnOrderId = (int)($payload['return_order_id'] ?? $payload['biz_id'] ?? 0);
            if ($returnOrderId <= 0) {
                throw new AdminException('缺少退货单ID，无法生成打印数据');
            }
            $printData = $this->templateService->getReturnPrintData($returnOrderId);
            return [
                'summary' => [
                    'biz_type' => 'return',
                    'biz_id' => $returnOrderId,
                    'order_id' => (int)($printData['order_id'] ?? 0),
                    'return_order_id' => $returnOrderId,
                    'title' => $printData['return_order_no'] ?? '',
                    'subtitle' => $printData['return_status_name'] ?? '',
                ],
                'device' => [
                    'device_id' => 0,
                    'order_id' => (int)($printData['order_id'] ?? 0),
                    'order_no' => $printData['origin_order_no'] ?? '',
                    'model' => $printData['first_device_model'] ?? '',
                    'imei' => $printData['first_device_imei'] ?? '',
                    'sn' => $printData['first_device_sn'] ?? '',
                    'status_name' => $printData['return_status_name'] ?? '',
                    'device_number' => $printData['device_count'] ?? '',
                ],
                'print_data' => $printData,
            ];
        }

        if ($bizType === 'consignment') {
            $consignmentId = (int)($payload['consignment_id'] ?? $payload['biz_id'] ?? 0);
            if ($consignmentId <= 0) {
                throw new AdminException('缺少代卖订单ID，无法生成打印数据');
            }
            $printData = $this->templateService->getConsignmentPrintData($consignmentId);
            return [
                'summary' => [
                    'biz_type' => 'consignment',
                    'biz_id' => $consignmentId,
                    'order_id' => (int)($printData['source_order_id'] ?? 0),
                    'consignment_id' => $consignmentId,
                    'title' => $printData['consignment_no'] ?? '',
                    'subtitle' => $printData['status_name'] ?? '',
                ],
                'device' => [
                    'device_id' => (int)($printData['source_device_id'] ?? 0),
                    'order_id' => (int)($printData['source_order_id'] ?? 0),
                    'order_no' => $printData['source_order_no'] ?? '',
                    'model' => $printData['device_model'] ?? '',
                    'imei' => $printData['device_imei'] ?? '',
                    'sn' => $printData['device_sn'] ?? '',
                    'status_name' => $printData['status_name'] ?? '',
                    'device_number' => '1/1',
                ],
                'print_data' => $printData,
            ];
        }

        $deviceId = (int)($payload['device_id'] ?? $payload['biz_id'] ?? 0);
        if ($deviceId <= 0) {
            throw new AdminException('缺少设备ID，无法生成打印数据');
        }
        $printData = $this->templateService->getDevicePrintData($deviceId);
        return [
            'summary' => [
                'biz_type' => 'device',
                'biz_id' => $deviceId,
                'order_id' => (int)($printData['order_id'] ?? 0),
                'device_id' => $deviceId,
                'title' => $printData['imei'] ?? '',
                'subtitle' => $printData['model'] ?? '',
            ],
            'device' => [
                'device_id' => (int)$printData['device_id'],
                'order_id' => (int)($printData['order_id'] ?? 0),
                'order_no' => $printData['order_no'] ?? '',
                'model' => $printData['model'] ?? '',
                'imei' => $printData['imei'] ?? '',
                'sn' => $printData['sn'] ?? '',
                'status_name' => $printData['status_name'] ?? '',
                'device_number' => $printData['device_number'] ?? '',
            ],
            'print_data' => $printData,
        ];
    }

    private function getPrintVariableOptions(): array
    {
        return [
            'device' => [
                ['key' => 'imei', 'label' => 'IMEI', 'sample' => '358000000000000'],
                ['key' => 'model', 'label' => '设备型号', 'sample' => 'iPhone 15 Pro'],
                ['key' => 'capacity', 'label' => '容量', 'sample' => '256GB'],
                ['key' => 'color', 'label' => '颜色', 'sample' => '黑色'],
                ['key' => 'battery', 'label' => '电池健康度', 'sample' => '95'],
                ['key' => 'check_info', 'label' => '质检信息', 'sample' => '外观正常; 功能正常'],
                ['key' => 'price', 'label' => '回收价格', 'sample' => '3200.00'],
                ['key' => 'order_no', 'label' => '订单编号', 'sample' => 'R202605250001'],
            ],
            'order' => [
                ['key' => 'order_no', 'label' => '订单编号', 'sample' => 'R202605250001'],
                ['key' => 'customer_name', 'label' => '客户姓名', 'sample' => '张三'],
                ['key' => 'customer_phone', 'label' => '客户手机号', 'sample' => '13800000000'],
                ['key' => 'device_count', 'label' => '设备数量', 'sample' => '3'],
                ['key' => 'total_amount', 'label' => '订单金额', 'sample' => '6800.00'],
                ['key' => 'order_status_name', 'label' => '订单状态', 'sample' => '待打款'],
            ],
            'return' => [
                ['key' => 'return_order_no', 'label' => '退货单号', 'sample' => 'RT202605250001'],
                ['key' => 'origin_order_no', 'label' => '原订单编号', 'sample' => 'R202605250001'],
                ['key' => 'express_company', 'label' => '退货快递公司', 'sample' => '顺丰速运'],
                ['key' => 'express_no', 'label' => '退货快递单号', 'sample' => 'SF123456789'],
                ['key' => 'return_address', 'label' => '退货地址', 'sample' => '广东省深圳市...'],
                ['key' => 'member_name', 'label' => '收件人', 'sample' => '张三'],
                ['key' => 'member_mobile', 'label' => '收件电话', 'sample' => '13800000000'],
                ['key' => 'device_summary', 'label' => '退货设备摘要', 'sample' => 'iPhone 15 Pro / 358...'],
            ],
            'consignment' => [
                ['key' => 'consignment_no', 'label' => '代卖单号', 'sample' => 'C202605250001'],
                ['key' => 'source_order_no', 'label' => '来源订单号', 'sample' => 'R202605250001'],
                ['key' => 'device_imei', 'label' => '设备IMEI', 'sample' => '358000000000000'],
                ['key' => 'device_model', 'label' => '设备型号', 'sample' => 'iPhone 15 Pro'],
                ['key' => 'status_name', 'label' => '代卖状态', 'sample' => '代卖中'],
                ['key' => 'quote_price', 'label' => '回收报价', 'sample' => '3200.00'],
                ['key' => 'listing_price', 'label' => '挂牌价', 'sample' => '3999.00'],
                ['key' => 'sold_price', 'label' => '成交价', 'sample' => '4200.00'],
                ['key' => 'settlement_amount', 'label' => '客户结算', 'sample' => '4000.00'],
                ['key' => 'service_fee', 'label' => '服务收益', 'sample' => '200.00'],
                ['key' => 'customer_name', 'label' => '客户姓名', 'sample' => '张三'],
                ['key' => 'customer_phone', 'label' => '客户手机号', 'sample' => '13800000000'],
            ],
        ];
    }

    private function isBuiltinScene(string $sceneKey): bool
    {
        return array_key_exists($sceneKey, RecyclePrintScene::sceneList());
    }

    private function isObsoleteScene(string $sceneKey): bool
    {
        return in_array($sceneKey, ['device_check_complete'], true);
    }

    private function makeCustomSceneKey(): string
    {
        do {
            $sceneKey = 'custom_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
            $exists = $this->model
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['scene_key', '=', $sceneKey],
                ])
                ->findOrEmpty();
        } while (!$exists->isEmpty());

        return $sceneKey;
    }

    private function isManualSceneVisible(array $scene, $payload): bool
    {
        $payload = is_array($payload) ? $payload : ['device_id' => (int)$payload];
        $bizId = $this->getPayloadBizId((string)($scene['biz_type'] ?? 'device'), $payload);
        if ($bizId <= 0) {
            return true;
        }
        $button = $scene['button_config'] ?? [];
        if (empty($button['enabled'])) {
            return false;
        }
        $visibleStatuses = $button['visible_device_status'] ?? [];
        if (empty($visibleStatuses)) {
            return true;
        }
        $bizStatus = $this->getBizStatusForVisibility((string)($scene['biz_type'] ?? 'device'), $bizId);
        if (empty($bizStatus)) {
            return false;
        }
        if (!in_array((int)$bizStatus['status'], array_map('intval', $visibleStatuses), true)) {
            return false;
        }
        $confirmStatuses = $button['visible_confirm_status'] ?? [];
        if (!empty($confirmStatuses) && !in_array((int)($bizStatus['confirm_status'] ?? 0), array_map('intval', $confirmStatuses), true)) {
            return false;
        }
        $payStatuses = $button['visible_pay_status'] ?? [];
        if (!empty($payStatuses) && !in_array((int)($bizStatus['pay_status'] ?? 0), array_map('intval', $payStatuses), true)) {
            return false;
        }
        return true;
    }

    private function getPayloadBizId(string $bizType, array $payload): int
    {
        if ($bizType === 'order') {
            return (int)($payload['order_id'] ?? $payload['biz_id'] ?? 0);
        }
        if ($bizType === 'return') {
            return (int)($payload['return_order_id'] ?? $payload['biz_id'] ?? 0);
        }
        if ($bizType === 'consignment') {
            return (int)($payload['consignment_id'] ?? $payload['biz_id'] ?? 0);
        }
        return (int)($payload['device_id'] ?? $payload['biz_id'] ?? 0);
    }

    private function getBizStatusForVisibility(string $bizType, int $bizId): array
    {
        if ($bizType === 'order') {
            $order = (new RecycleOrder())->where([
                ['id', '=', $bizId],
                ['site_id', '=', $this->site_id],
            ])->field('id,status,pay_status')->findOrEmpty()->toArray();
            return empty($order) ? [] : [
                'status' => (int)($order['status'] ?? 0),
                'pay_status' => (int)($order['pay_status'] ?? 0),
            ];
        }

        if ($bizType === 'return') {
            $returnOrder = (new RecycleReturnOrder())->where([
                ['id', '=', $bizId],
                ['site_id', '=', $this->site_id],
            ])->field('id,status')->findOrEmpty()->toArray();
            return empty($returnOrder) ? [] : [
                'status' => (int)($returnOrder['status'] ?? 0),
            ];
        }

        if ($bizType === 'consignment') {
            $consignment = (new RecycleConsignmentOrder())->where([
                ['id', '=', $bizId],
                ['site_id', '=', $this->site_id],
            ])->field('id,status,pay_status')->findOrEmpty()->toArray();
            return empty($consignment) ? [] : [
                'status' => (int)($consignment['status'] ?? 0),
                'pay_status' => (int)($consignment['pay_status'] ?? 0),
            ];
        }

        $device = (new RecycleDevice())->where([
            ['id', '=', $bizId],
            ['site_id', '=', $this->site_id],
        ])->field('id,status,confirm_status,pay_status')->findOrEmpty()->toArray();
        return empty($device) ? [] : [
            'status' => (int)($device['status'] ?? 0),
            'confirm_status' => (int)($device['confirm_status'] ?? 0),
            'pay_status' => (int)($device['pay_status'] ?? 0),
        ];
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
                'trigger_key' => $scene['trigger_key'] ?? '',
            ],
        ];
    }

    public function recordSkippedPlan(array $plan, string $message): void
    {
        $this->recordPlanLog($plan, 0, $message);
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
        $biz = $plan['biz'] ?? [];
        $bizType = (string)($biz['biz_type'] ?? $plan['scene']['biz_type'] ?? 'device');
        $this->logService->record([
            'scene_key' => $plan['scene']['scene_key'] ?? '',
            'scene_name' => $plan['scene']['scene_name'] ?? '',
            'biz_type' => $bizType,
            'biz_id' => (int)($biz['biz_id'] ?? $plan['device']['device_id'] ?? 0),
            'order_id' => (int)($biz['order_id'] ?? $plan['device']['order_id'] ?? 0),
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
