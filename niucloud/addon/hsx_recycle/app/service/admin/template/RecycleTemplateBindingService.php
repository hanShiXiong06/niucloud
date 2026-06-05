<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\template;

use addon\hsx_recycle\app\model\check\RecycleCheckTemplate;
use addon\hsx_recycle\app\model\device\RecycleDeviceModelDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\printer\RecyclePrinterTemplate;
use addon\hsx_recycle\app\model\template\RecycleTemplateBinding;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 回收模板绑定配置服务
 */
class RecycleTemplateBindingService extends BaseAdminService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleTemplateBinding();
    }

    public function getInfo(string $targetType, int $targetId, string $sceneKey = 'manual_device_label'): array
    {
        $binding = $this->findBinding($targetType, $targetId, $sceneKey);
        $effective = $this->resolveByTarget($targetType, $targetId, $sceneKey);

        return [
            'binding' => $binding,
            'effective' => $effective,
            'check_template_options' => $this->getCheckTemplateOptions(),
            'print_template_options' => $this->getPrintTemplateOptions(),
        ];
    }

    public function save(array $data): bool
    {
        $targetType = $this->normalizeTargetType((string)($data['target_type'] ?? 'model_dict'));
        $targetId = (int)($data['target_id'] ?? 0);
        $sceneKey = trim((string)($data['scene_key'] ?? 'manual_device_label')) ?: 'manual_device_label';

        if ($targetType !== 'global') {
            $this->assertModelNode($targetId);
        } else {
            $targetId = 0;
        }

        $checkTemplateId = (int)($data['check_template_id'] ?? 0);
        $printTemplateId = (int)($data['print_template_id'] ?? 0);
        if ($checkTemplateId > 0) {
            $this->assertCheckTemplate($checkTemplateId);
        }
        if ($printTemplateId > 0) {
            $this->assertPrintTemplate($printTemplateId);
        }

        $payload = [
            'site_id' => $this->site_id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'scene_key' => $sceneKey,
            'check_template_id' => $checkTemplateId,
            'print_template_id' => $printTemplateId,
            'inherit_enabled' => empty($data['inherit_enabled']) ? 0 : 1,
            'status' => empty($data['status']) ? 0 : 1,
            'remark' => trim((string)($data['remark'] ?? '')),
            'sort' => (int)($data['sort'] ?? 0),
        ];

        $exists = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['target_type', '=', $targetType],
            ['target_id', '=', $targetId],
            ['scene_key', '=', $sceneKey],
        ])->findOrEmpty();

        if ($exists->isEmpty()) {
            $this->model->create($payload);
        } else {
            $exists->save($payload);
        }

        return true;
    }

    public function reset(string $targetType, int $targetId, string $sceneKey = 'manual_device_label'): bool
    {
        $targetType = $this->normalizeTargetType($targetType);
        if ($targetType === 'global') {
            $targetId = 0;
        }
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['target_type', '=', $targetType],
            ['target_id', '=', $targetId],
            ['scene_key', '=', $sceneKey],
        ])->delete();

        return true;
    }

    public function resolveForDevice(array $device, string $sceneKey = 'manual_device_label'): array
    {
        $nodeId = (int)($device['category_id'] ?? 0);

        if ($nodeId > 0) {
            return $this->resolveByTarget('model_dict', $nodeId, $sceneKey);
        }

        return $this->resolveByTarget('global', 0, $sceneKey);
    }

    public function resolveByDeviceId(int $deviceId, string $sceneKey = 'manual_device_label'): array
    {
        if ($deviceId <= 0) {
            throw new CommonException('请选择设备');
        }

        $device = RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $deviceId],
        ])->findOrEmpty()->toArray();

        if (empty($device)) {
            throw new CommonException('设备不存在');
        }

        return $this->resolveForDevice($device, $sceneKey);
    }

    public function resolveByTarget(string $targetType, int $targetId, string $sceneKey = 'manual_device_label'): array
    {
        $targetType = $this->normalizeTargetType($targetType);
        $chain = $targetType === 'global' ? [] : $this->getAncestorChain($targetId);

        foreach ($chain as $index => $node) {
            $binding = $this->findBinding('model_dict', (int)$node['id'], $sceneKey);
            if (!empty($binding) && (int)($binding['status'] ?? 0) === 1 && $this->hasUsableTemplate($binding)) {
                if ($index > 0 && empty($binding['inherit_enabled'])) {
                    continue;
                }
                return $this->formatResolvedBinding($binding, $node, 'model_dict');
            }
        }

        $global = $this->findBinding('global', 0, $sceneKey);
        if (!empty($global) && (int)($global['status'] ?? 0) === 1 && $this->hasUsableTemplate($global)) {
            return $this->formatResolvedBinding($global, [], 'global');
        }

        return [
            'matched' => false,
            'source_type' => '',
            'source_id' => 0,
            'source_name' => '',
            'scene_key' => $sceneKey,
            'check_template_id' => 0,
            'check_template_name' => '',
            'print_template_id' => 0,
            'print_template_name' => '',
        ];
    }

    private function findBinding(string $targetType, int $targetId, string $sceneKey): array
    {
        $row = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['target_type', '=', $targetType],
            ['target_id', '=', $targetId],
            ['scene_key', '=', $sceneKey],
        ])->findOrEmpty()->toArray();

        if (empty($row)) {
            return [];
        }
        return $this->appendTemplateNames($row);
    }

    private function formatResolvedBinding(array $binding, array $node, string $sourceType): array
    {
        return [
            'matched' => true,
            'source_type' => $sourceType,
            'source_id' => $sourceType === 'global' ? 0 : (int)($node['id'] ?? 0),
            'source_name' => $sourceType === 'global' ? '通用兜底' : (string)($node['model_full_name'] ?? $node['node_name'] ?? ''),
            'scene_key' => (string)($binding['scene_key'] ?? ''),
            'check_template_id' => (int)($binding['check_template_id'] ?? 0),
            'check_template_name' => (string)($binding['check_template_name'] ?? ''),
            'print_template_id' => (int)($binding['print_template_id'] ?? 0),
            'print_template_name' => (string)($binding['print_template_name'] ?? ''),
            'binding_id' => (int)($binding['id'] ?? 0),
        ];
    }

    private function appendTemplateNames(array $row): array
    {
        $checkTemplateId = (int)($row['check_template_id'] ?? 0);
        $printTemplateId = (int)($row['print_template_id'] ?? 0);
        $row['check_template_name'] = $checkTemplateId > 0
            ? (string)(RecycleCheckTemplate::where([['site_id', '=', $this->site_id], ['id', '=', $checkTemplateId]])->value('template_name') ?: '')
            : '';
        $row['print_template_name'] = $printTemplateId > 0
            ? (string)(RecyclePrinterTemplate::where([['site_id', '=', $this->site_id], ['template_id', '=', $printTemplateId]])->value('template_name') ?: '')
            : '';
        return $row;
    }

    private function getAncestorChain(int $nodeId): array
    {
        $chain = [];
        $currentId = $nodeId;
        $guard = 0;
        while ($currentId > 0 && $guard < 10) {
            $node = RecycleDeviceModelDict::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $currentId],
            ])->findOrEmpty()->toArray();
            if (empty($node)) {
                break;
            }
            $chain[] = $node;
            $currentId = (int)($node['pid'] ?? 0);
            $guard++;
        }
        return $chain;
    }

    private function getCheckTemplateOptions(): array
    {
        return RecycleCheckTemplate::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
        ])->field('id,template_name,scene,is_default,sort,update_at')
            ->order('is_default desc, sort asc, id desc')
            ->select()
            ->toArray();
    }

    private function getPrintTemplateOptions(): array
    {
        return RecyclePrinterTemplate::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['template_type', '=', 'device_label'],
            ['delete_time', '=', 0],
        ])->field('template_id,template_name,template_type,width,height,is_default,update_time')
            ->order('is_default desc, update_time desc, template_id desc')
            ->select()
            ->toArray();
    }

    private function hasUsableTemplate(array $binding): bool
    {
        return (int)($binding['check_template_id'] ?? 0) > 0 || (int)($binding['print_template_id'] ?? 0) > 0;
    }

    private function normalizeTargetType(string $targetType): string
    {
        return $targetType === 'global' ? 'global' : 'model_dict';
    }

    private function assertModelNode(int $targetId): void
    {
        if ($targetId <= 0) {
            throw new CommonException('请选择型号节点');
        }
        $exists = RecycleDeviceModelDict::where([['site_id', '=', $this->site_id], ['id', '=', $targetId]])->count();
        if ($exists <= 0) {
            throw new CommonException('型号节点不存在');
        }
    }

    private function assertCheckTemplate(int $templateId): void
    {
        $exists = RecycleCheckTemplate::where([['site_id', '=', $this->site_id], ['id', '=', $templateId]])->count();
        if ($exists <= 0) {
            throw new CommonException('质检模板不存在');
        }
    }

    private function assertPrintTemplate(int $templateId): void
    {
        $exists = RecyclePrinterTemplate::where([['site_id', '=', $this->site_id], ['template_id', '=', $templateId], ['delete_time', '=', 0]])->count();
        if ($exists <= 0) {
            throw new CommonException('打印模板不存在');
        }
    }
}
