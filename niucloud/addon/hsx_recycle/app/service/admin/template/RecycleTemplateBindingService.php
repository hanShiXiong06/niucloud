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
use addon\hsx_recycle\app\service\core\device\DeviceCatalogPolicy;
use addon\hsx_recycle\app\service\core\device\DeviceCatalogWriteLock;
use think\facade\Db;
use addon\hsx_recycle\app\service\core\printer\PrinterTemplateLibrary;

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
        $policy = (new DeviceCatalogPolicy())->forSite((int)$this->site_id);
        $readOnly = !empty($policy['read_only']);
        if (!empty($policy['effective'])) $binding['check_template_id'] = (int)$effective['check_template_id'];

        return [
            'binding' => $binding,
            'effective' => $effective,
            'check_read_only' => $readOnly,
            'catalog_message' => !empty($policy['effective']) ? $policy['message'] : '',
            'check_template_options' => $this->getCheckTemplateOptions((int)($binding['check_template_id'] ?? 0)),
            'print_template_options' => $this->getPrintTemplateOptions(),
        ];
    }

    public function save(array $data): bool
    {
        $lock = new DeviceCatalogWriteLock(); $lock->acquire();
        return Db::transaction(fn() => $this->saveBinding($data));
    }

    private function saveBinding(array $data): bool
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
        $policy = (new DeviceCatalogPolicy())->forSite((int)$this->site_id);
        if (!empty($policy['effective']) && !empty($policy['can_manage_public'])) {
            if ($checkTemplateId > 0 && !RecycleCheckTemplate::where('site_id', 0)->where('id', $checkTemplateId)->count()) throw new CommonException('公共质检模板不存在');
            if ($printTemplateId > 0) $this->assertPrintTemplate($printTemplateId);
            $sharedWhere = [['site_id', '=', 0], ['target_type', '=', $targetType], ['target_id', '=', $targetId], ['scene_key', '=', $sceneKey]];
            $shared = $this->model->where($sharedWhere)->findOrEmpty();
            $sharedData = ['site_id' => 0, 'target_type' => $targetType, 'target_id' => $targetId, 'scene_key' => $sceneKey,
                'check_template_id' => $checkTemplateId, 'print_template_id' => $shared->isEmpty() ? 0 : (int)$shared['print_template_id'], 'inherit_enabled' => empty($data['inherit_enabled']) ? 0 : 1,
                'status' => empty($data['status']) ? 0 : 1, 'sort' => (int)($data['sort'] ?? 0)];
            if ($shared->isEmpty()) $this->model->create($sharedData); else $shared->save($sharedData);
            $checkTemplateId = 0;
        }
        if (!empty((new DeviceCatalogPolicy())->forSite((int)$this->site_id)['read_only'])) {
            $effective = $this->resolveByTarget($targetType, $targetId, $sceneKey);
            if ($checkTemplateId !== (int)$effective['check_template_id']) throw new CommonException(DeviceCatalogPolicy::READ_ONLY_MESSAGE);
            $checkTemplateId = 0; // 站点只保存打印配置，不覆盖公共质检绑定。
        }
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
        $lock = new DeviceCatalogWriteLock(); $lock->acquire();
        $targetType = $this->normalizeTargetType($targetType);
        if ($targetType === 'global') {
            $targetId = 0;
        }
        $policy = (new DeviceCatalogPolicy())->forSite((int)$this->site_id);
        if (!empty($policy['effective']) && !empty($policy['can_manage_public'])) {
            throw new CommonException('当前使用公共资料库，请在模板选择中修改质检关联；重置可能同时影响全站质检和本站打印，本次未执行');
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

    /**
     * 读取当前有效绑定；解码未记录模板的设备选项时，可禁用全局兜底，避免套用不相关的字典。
     */
    public function resolveByTarget(string $targetType, int $targetId, string $sceneKey = 'manual_device_label', bool $allowGlobalFallback = true): array
    {
        $local = $this->resolveLocalTarget($targetType, $targetId, $sceneKey, $allowGlobalFallback);
        $owner = (new DeviceCatalogPolicy())->ownerSiteId((int)$this->site_id);
        if ($targetType !== 'global' && $targetId > 0) {
            $nodeOwner = RecycleDeviceModelDict::whereIn('site_id', DeviceCatalogPolicy::referenceSiteIds((int)$this->site_id))->where('id', $targetId)->value('site_id');
            if ($nodeOwner !== null) $owner = (int)$nodeOwner;
        }
        if ($owner === (int)$this->site_id) return $local;
        $oldSiteId = $this->site_id;
        $this->site_id = $owner;
        try { $shared = $this->resolveLocalTarget($targetType, $targetId, $sceneKey, $allowGlobalFallback); }
        finally { $this->site_id = $oldSiteId; }
        // 本站独立配置优先；公共绑定只能指向 site_id=0 的公共版式，不能泄漏原站私有标签。
        $publicPrint = PrinterTemplateLibrary::get(0, (int)$shared['print_template_id']);
        if ((int)$local['print_template_id'] > 0 || !$publicPrint || (int)$publicPrint['status'] !== 1) {
            $shared['print_template_id'] = $local['print_template_id'];
            $shared['print_template_name'] = $local['print_template_name'];
        }
        $shared['matched'] = $shared['check_template_id'] > 0 || $shared['print_template_id'] > 0;
        return $shared;
    }

    private function resolveLocalTarget(string $targetType, int $targetId, string $sceneKey, bool $allowGlobalFallback): array
    {
        $targetType = $this->normalizeTargetType($targetType);
        $chain = $targetType === 'global' ? [] : $this->getAncestorChain($targetId);

        // check 与 print 各自独立解析：沿型号→祖先链各取第一个非0，缺的再回退 global 兜底。
        // 这样拍机堂型号(只绑了 check)，print 会自动回退到 global 打印模板，无需逐型号设置。
        $checkId = 0; $checkName = ''; $printId = 0; $printName = '';
        $srcType = ''; $srcId = 0; $srcName = '';
        $pick = function (array $b, array $node, string $type) use (&$checkId, &$checkName, &$printId, &$printName, &$srcType, &$srcId, &$srcName) {
            if ($checkId <= 0 && (int)($b['check_template_id'] ?? 0) > 0) {
                $checkId = (int)$b['check_template_id'];
                $checkName = (string)($b['check_template_name'] ?? '');
                if ($srcType === '') { $srcType = $type; $srcId = $type === 'global' ? 0 : (int)($node['id'] ?? 0); $srcName = $type === 'global' ? '通用兜底' : (string)($node['model_full_name'] ?? $node['node_name'] ?? ''); }
            }
            if ($printId <= 0 && (int)($b['print_template_id'] ?? 0) > 0) {
                $printId = (int)$b['print_template_id'];
                $printName = (string)($b['print_template_name'] ?? '');
                if ($srcType === '') { $srcType = $type; $srcId = $type === 'global' ? 0 : (int)($node['id'] ?? 0); $srcName = $type === 'global' ? '通用兜底' : (string)($node['model_full_name'] ?? $node['node_name'] ?? ''); }
            }
        };

        foreach ($chain as $index => $node) {
            $binding = $this->findBinding('model_dict', (int)$node['id'], $sceneKey);
            if (empty($binding) || (int)($binding['status'] ?? 0) !== 1) {
                continue;
            }
            if ($index > 0 && empty($binding['inherit_enabled'])) {
                continue;
            }
            $pick($binding, $node, 'model_dict');
            if ($checkId > 0 && $printId > 0) {
                break;
            }
        }

        if ($allowGlobalFallback && ($checkId <= 0 || $printId <= 0)) {
            $global = $this->findBinding('global', 0, $sceneKey);
            if (!empty($global) && (int)($global['status'] ?? 0) === 1) {
                $pick($global, [], 'global');
            }
        }

        return [
            'matched' => ($checkId > 0 || $printId > 0),
            'source_type' => $srcType,
            'source_id' => $srcId,
            'source_name' => $srcName,
            'scene_key' => $sceneKey,
            'check_template_id' => $checkId,
            'check_template_name' => $checkName,
            'print_template_id' => $printId,
            'print_template_name' => $printName,
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
            ? (string)(RecycleCheckTemplate::where([['site_id', 'in', DeviceCatalogPolicy::referenceSiteIds((int)$this->site_id)], ['id', '=', $checkTemplateId]])->value('template_name') ?: '')
            : '';
        $printTemplate = $printTemplateId > 0 ? PrinterTemplateLibrary::get((int)$this->site_id, $printTemplateId) : [];
        $row['print_template_name'] = (string)($printTemplate['template_name'] ?? '');
        // 保留已明确配置的 ID；失效时由打印计划报错，不能静默换成另一张标签。
        return $row;
    }

    private function getAncestorChain(int $nodeId): array
    {
        $chain = [];
        $currentId = $nodeId;
        $guard = 0;
        while ($currentId > 0 && $guard < 10) {
            $node = RecycleDeviceModelDict::where([
                ['site_id', 'in', DeviceCatalogPolicy::referenceSiteIds((int)$this->site_id)],
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

    private function getCheckTemplateOptions(int $includeId = 0): array
    {
        // 批量导入的拍机堂模板(scene=pjt)有上万条,不进下拉;它们通过型号绑定自动生效
        $options = RecycleCheckTemplate::where([
            ['site_id', '=', (new DeviceCatalogPolicy())->ownerSiteId((int)$this->site_id)],
            ['status', '=', 1],
            ['scene', '<>', 'pjt'],
        ])->field('id,template_name,scene,is_default,sort,update_at')
            ->order('is_default desc, sort asc, id desc')
            ->limit(200)
            ->select()
            ->toArray();

        if ($includeId > 0 && !in_array($includeId, array_column($options, 'id'))) {
            $bound = RecycleCheckTemplate::where([
                ['site_id', 'in', DeviceCatalogPolicy::referenceSiteIds((int)$this->site_id)],
                ['id', '=', $includeId],
            ])->field('id,template_name,scene,is_default,sort,update_at')
                ->findOrEmpty()
                ->toArray();
            if (!empty($bound)) {
                array_unshift($options, $bound);
            }
        }

        return $options;
    }

    private function getPrintTemplateOptions(): array
    {
        $options = PrinterTemplateLibrary::query((int)$this->site_id)->where([
            ['status', '=', 1],
            ['template_type', '=', 'device_label'],
            ['delete_time', '=', 0],
        ])->field('template_id,site_id,template_name,template_type,width,height,is_default,update_time')
            ->order('is_default desc, update_time desc, template_id desc')
            ->select()
            ->toArray();
        return array_map(fn(array $item) => PrinterTemplateLibrary::describe($item, (int)$this->site_id), $options);
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
        $exists = RecycleDeviceModelDict::where([['site_id', '=', (new DeviceCatalogPolicy())->ownerSiteId((int)$this->site_id)], ['id', '=', $targetId]])->count();
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
        $exists = PrinterTemplateLibrary::query((int)$this->site_id)->where('template_id', $templateId)->where('status', 1)->where('template_type', 'device_label')->count();
        if ($exists <= 0) {
            throw new CommonException('打印模板不存在');
        }
    }
}
