<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckField;
use addon\hsx_recycle\app\model\check\RecycleCheckGroup;
use addon\hsx_recycle\app\model\check\RecycleCheckOption;
use addon\hsx_recycle\app\model\check\RecycleCheckTemplate;
use addon\hsx_recycle\app\service\admin\template\RecycleTemplateBindingService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class RecycleCheckTemplateService extends BaseAdminService
{
    private RecycleCheckTemplate $templateModel;
    private RecycleCheckGroup $groupModel;
    private RecycleCheckField $fieldModel;
    private RecycleCheckOption $optionModel;

    public function __construct()
    {
        parent::__construct();
        $this->templateModel = new RecycleCheckTemplate();
        $this->groupModel = new RecycleCheckGroup();
        $this->fieldModel = new RecycleCheckField();
        $this->optionModel = new RecycleCheckOption();
        $this->model = $this->templateModel;
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $this->ensureSingleDefault();
        // 导入模板名以下划线连接,放宽搜索词空格为通配
        if (!empty($where['keyword'])) {
            $where['keyword'] = str_replace(' ', '%', trim((string)$where['keyword']));
        }
        $query = $this->templateModel
            ->withSearch(['site_id', 'status', 'scene', 'keyword'], $where);
        $this->applySourceFilter($query, (string)($where['source'] ?? ''));
        // 管理页下钻:点到哪个节点就按该节点子树过滤
        $this->applyCategoryFilter($query, (int)($where['category_id'] ?? 0), (string)($where['category_match'] ?? 'subtree'));
        $query->orderRaw("is_default desc, scene = 'pjt' asc, sort asc, id desc");
        return $this->pageQuery($query);
    }

    /**
     * 按来源过滤:manual=手工模板(非pjt),pjt=拍机堂导入
     */
    private function applySourceFilter($query, string $source): void
    {
        if ($source === 'manual') {
            $query->where('scene', '<>', 'pjt');
        } elseif ($source === 'pjt') {
            $query->where('scene', '=', 'pjt');
        }
    }

    /**
     * 按设备分类节点过滤:只保留该节点范围内绑定过的模板;手工模板始终保留
     * @param string $match root=按顶级大类(验机弹窗,便于切换同类型号);subtree=按选中节点子树(管理页下钻)
     */
    private function applyCategoryFilter($query, int $categoryId, string $match = 'root'): void
    {
        if ($categoryId <= 0) {
            return;
        }
        $prefix = $this->getCategoryPathPrefix($categoryId, $match);
        if ($prefix === '') {
            return;
        }
        $siteId = $this->site_id;
        $query->where(function ($q) use ($prefix, $siteId) {
            $q->whereIn('id', function ($sub) use ($prefix, $siteId) {
                $sub->name('recycle_template_binding')->alias('b')
                    ->join('recycle_device_model_dict d', 'd.id = b.target_id AND d.site_id = b.site_id')
                    ->where([
                        ['b.site_id', '=', $siteId],
                        ['b.target_type', '=', 'model_dict'],
                        ['b.status', '=', 1],
                        ['b.check_template_id', '>', 0],
                    ])
                    ->where(function ($p) use ($prefix) {
                        $p->whereLike('d.model_full_name', $prefix . '/%')
                            ->whereOr('d.model_full_name', '=', $prefix);
                    })
                    ->field('b.check_template_id');
            })->whereOr('scene', '<>', 'pjt');
        });
    }

    public function all(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $this->ensureSingleDefault();
        // 导入模板名以下划线连接,把搜索词中的空格放宽为通配,便于"iPhone 17"式输入
        if (!empty($where['keyword'])) {
            $where['keyword'] = str_replace(' ', '%', trim((string)$where['keyword']));
        }
        // 模板可达上万条(批量导入),下拉场景必须限量,配合 keyword 远程搜索使用
        $limit = (int)($where['limit'] ?? 0);
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $query = $this->templateModel
            ->withSearch(['site_id', 'status', 'scene', 'keyword'], $where);
        $this->applySourceFilter($query, (string)($where['source'] ?? ''));
        // 传入设备分类节点时,只取该顶级分类下绑定过的模板;手工模板(非pjt)不受限
        $this->applyCategoryFilter($query, (int)($where['category_id'] ?? 0));

        return $query
            ->orderRaw("is_default desc, scene = 'pjt' asc, sort asc, id desc")
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 取分类节点的路径前缀:root=顶级大类名(首段),subtree=节点完整路径
     */
    private function getCategoryPathPrefix(int $nodeId, string $match): string
    {
        $node = (new \addon\hsx_recycle\app\model\device\RecycleDeviceModelDict())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $nodeId],
        ])->field('node_name,model_full_name')->findOrEmpty()->toArray();
        if (empty($node)) {
            return '';
        }
        $path = trim((string)($node['model_full_name'] ?? ''));
        if ($path === '') {
            return trim((string)$node['node_name']);
        }
        return $match === 'subtree' ? $path : trim(explode('/', $path)[0]);
    }

    public function info(int $id): array
    {
        $info = $this->templateModel->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('质检模板不存在');
        }
        return $info;
    }

    public function addTemplate(array $data): int
    {
        $name = trim((string)($data['template_name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请输入模板名称');
        }
        $key = trim((string)($data['template_key'] ?? ''));
        if ($key === '') {
            $key = 'custom_' . time();
        }
        $scene = (string)($data['scene'] ?? 'phone');
        $record = $this->templateModel->create([
            'site_id' => $this->site_id,
            'template_key' => $key,
            'template_name' => $name,
            'scene' => $scene,
            'is_default' => (int)($data['is_default'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
            'sort' => (int)($data['sort'] ?? 0),
            'version' => 1,
        ]);
        if ((int)($data['is_default'] ?? 0) === 1) {
            $this->setDefault((int)$record->id);
        } else {
            $this->ensureSingleDefault();
        }
        return (int)$record->id;
    }

    public function editTemplate(int $id, array $data): bool
    {
        $template = $this->info($id);
        $save = [];
        foreach (['template_key', 'template_name', 'scene'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '') {
                $save[$field] = (string)$data[$field];
            }
        }
        foreach (['is_default', 'status', 'sort'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '') {
                $save[$field] = (int)$data[$field];
            }
        }
        $save['version'] = Db::raw('version + 1');
        $this->templateModel->where('id', $id)->update($save);
        if ((int)($data['is_default'] ?? 0) === 1) {
            $this->setDefault($id);
        } else {
            $this->ensureSingleDefault();
        }
        return true;
    }

    public function deleteTemplate(int $id): bool
    {
        $template = $this->info($id);
        $fieldIds = $this->fieldModel->where('template_id', $id)->column('id');
        if (!empty($fieldIds)) {
            $this->optionModel->whereIn('field_id', $fieldIds)->delete();
        }
        $this->fieldModel->where('template_id', $id)->delete();
        $this->groupModel->where('template_id', $id)->delete();
        $this->templateModel->where('id', $id)->delete();
        $this->ensureSingleDefault();
        return true;
    }

    public function setDefault(int $id): bool
    {
        $template = $this->info($id);
        Db::transaction(function () use ($id, $template) {
            $this->templateModel->where([
                ['site_id', '=', $this->site_id],
            ])->update(['is_default' => 0]);
            $this->templateModel->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
            ])->update(['is_default' => 1, 'status' => 1]);
        });
        return true;
    }

    public function groups(int $templateId): array
    {
        $this->info($templateId);
        return $this->groupModel->where([
            ['site_id', '=', $this->site_id],
            ['template_id', '=', $templateId],
        ])->order('sort asc,id asc')->select()->toArray();
    }

    public function saveGroup(array $data): int
    {
        $templateId = (int)($data['template_id'] ?? 0);
        $this->info($templateId);
        $id = (int)($data['id'] ?? 0);
        $save = [
            'site_id' => $this->site_id,
            'template_id' => $templateId,
            'group_key' => (string)($data['group_key'] ?? ''),
            'group_name' => (string)($data['group_name'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ];
        if ($save['group_key'] === '' || $save['group_name'] === '') {
            throw new CommonException('请填写分组标识和分组名称');
        }
        if ($id > 0) {
            $this->groupModel->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($save);
            $this->touchTemplate($templateId);
            return $id;
        }
        $record = $this->groupModel->create($save);
        $this->touchTemplate($templateId);
        return (int)$record->id;
    }

    public function deleteGroup(int $id): bool
    {
        $group = $this->groupModel->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($group)) {
            throw new CommonException('质检分组不存在');
        }
        $fieldIds = $this->fieldModel->where('group_id', $id)->column('id');
        if (!empty($fieldIds)) {
            $this->optionModel->whereIn('field_id', $fieldIds)->delete();
        }
        $this->fieldModel->where('group_id', $id)->delete();
        $this->groupModel->where('id', $id)->delete();
        $this->touchTemplate((int)$group['template_id']);
        return true;
    }

    public function fields(int $templateId, int $groupId = 0): array
    {
        $this->info($templateId);
        $query = $this->fieldModel->where([
            ['site_id', '=', $this->site_id],
            ['template_id', '=', $templateId],
        ]);
        if ($groupId > 0) {
            $query->where('group_id', '=', $groupId);
        }
        $fields = $query->order('sort asc,id asc')->select()->toArray();
        $fieldIds = array_column($fields, 'id');
        $options = empty($fieldIds) ? [] : $this->optionModel
            ->whereIn('field_id', $fieldIds)
            ->order('sort asc,id asc')
            ->select()
            ->toArray();
        $optionMap = [];
        foreach ($options as $option) {
            $optionMap[(int)$option['field_id']][] = $option;
        }
        foreach ($fields as &$field) {
            $field['options'] = $optionMap[(int)$field['id']] ?? [];
        }
        return $fields;
    }

    public function saveField(array $data): int
    {
        $templateId = (int)($data['template_id'] ?? 0);
        $this->info($templateId);
        $id = (int)($data['id'] ?? 0);
        $save = [
            'site_id' => $this->site_id,
            'template_id' => $templateId,
            'group_id' => (int)($data['group_id'] ?? 0),
            'field_key' => (string)($data['field_key'] ?? ''),
            'field_name' => (string)($data['field_name'] ?? ''),
            'component' => (string)($data['component'] ?? 'input'),
            'selection_mode' => (string)($data['selection_mode'] ?? ''),
            'unit' => (string)($data['unit'] ?? ''),
            'placeholder' => (string)($data['placeholder'] ?? ''),
            'default_value' => (string)($data['default_value'] ?? ''),
            'is_required' => (int)($data['is_required'] ?? 0),
            'is_show' => (int)($data['is_show'] ?? 1),
            'seller_visible' => (int)($data['seller_visible'] ?? 1),
            'buyer_visible' => (int)($data['buyer_visible'] ?? 0),
            'result_visible' => (int)($data['result_visible'] ?? 1),
            'result_template' => (string)($data['result_template'] ?? ''),
            'api_fill_enabled' => (int)($data['api_fill_enabled'] ?? 0),
            'api_fill_policy' => (string)($data['api_fill_policy'] ?? 'empty_only'),
            'sort' => (int)($data['sort'] ?? 0),
            'extra_config' => $this->normalizeJsonConfig($data['extra_config'] ?? []),
        ];
        if ($save['group_id'] <= 0 || $save['field_key'] === '' || $save['field_name'] === '') {
            throw new CommonException('请填写字段分组、字段标识和字段名称');
        }
        $this->assertSummaryFieldLimit($templateId, $id, $save['extra_config']);
        if ($id > 0) {
            $this->fieldModel->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($save);
            $this->touchTemplate($templateId);
            return $id;
        }
        $record = $this->fieldModel->create($save);
        $this->touchTemplate($templateId);
        return (int)$record->id;
    }

    public function deleteField(int $id): bool
    {
        $field = $this->fieldModel->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($field)) {
            throw new CommonException('质检字段不存在');
        }
        $this->optionModel->where('field_id', $id)->delete();
        $this->fieldModel->where('id', $id)->delete();
        $this->touchTemplate((int)$field['template_id']);
        return true;
    }

    public function saveOption(array $data): int
    {
        $fieldId = (int)($data['field_id'] ?? 0);
        $field = $this->fieldModel->where([['id', '=', $fieldId], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($field)) {
            throw new CommonException('质检字段不存在');
        }
        $id = (int)($data['id'] ?? 0);
        $save = [
            'site_id' => $this->site_id,
            'field_id' => $fieldId,
            'option_label' => (string)($data['option_label'] ?? ''),
            'option_value' => (string)($data['option_value'] ?? ''),
            'is_default' => (int)($data['is_default'] ?? 0),
            'is_show' => (int)($data['is_show'] ?? 1),
            'sort' => (int)($data['sort'] ?? 0),
            'extra_config' => $this->normalizeJsonConfig($data['extra_config'] ?? []),
        ];
        if ($save['option_label'] === '') {
            throw new CommonException('请输入选项名称');
        }
        if ($save['option_value'] === '') {
            $save['option_value'] = (string)time();
        }
        if ($id > 0) {
            $this->optionModel->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($save);
            $this->touchTemplate((int)$field['template_id']);
            return $id;
        }
        $record = $this->optionModel->create($save);
        $this->touchTemplate((int)$field['template_id']);
        return (int)$record->id;
    }

    public function deleteOption(int $id): bool
    {
        $option = $this->optionModel->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($option)) {
            throw new CommonException('质检选项不存在');
        }
        $field = $this->fieldModel->where('id', (int)$option['field_id'])->findOrEmpty()->toArray();
        $this->optionModel->where('id', $id)->delete();
        if (!empty($field)) {
            $this->touchTemplate((int)$field['template_id']);
        }
        return true;
    }

    /**
     * 设置选项默认选中:单选字段(radio/select)互斥,只能有一个默认;多选(checkbox)可多个
     */
    public function setOptionDefault(int $optionId, int $isDefault): bool
    {
        $option = $this->optionModel->where([['id', '=', $optionId], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($option)) {
            throw new CommonException('质检选项不存在');
        }
        $field = $this->fieldModel->where([['id', '=', (int)$option['field_id']], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($field)) {
            throw new CommonException('质检字段不存在');
        }
        $isDefault = $isDefault ? 1 : 0;
        $isSingle = in_array($field['component'], ['radio', 'select'], true) || $field['selection_mode'] === 'single';

        Db::transaction(function () use ($option, $field, $isDefault, $isSingle) {
            // 单选设默认时先清掉同字段其它默认,保证互斥
            if ($isDefault && $isSingle) {
                $this->optionModel->where([
                    ['site_id', '=', $this->site_id],
                    ['field_id', '=', (int)$option['field_id']],
                ])->update(['is_default' => 0, 'update_at' => time()]);
            }
            $this->optionModel->where('id', (int)$option['id'])->update([
                'is_default' => $isDefault,
                'update_at' => time(),
            ]);
        });
        $this->touchTemplate((int)$field['template_id']);
        return true;
    }

    public function schema(array $where = []): array
    {
        $templateId = (int)($where['template_id'] ?? 0);
        $resolve = null;

        // 未显式指定模板时，按设备/分类节点解析绑定模板；解析失败静默落入默认兜底
        if ($templateId <= 0) {
            $deviceId = (int)($where['device_id'] ?? 0);
            $categoryId = (int)($where['category_id'] ?? 0);
            if ($deviceId > 0 || $categoryId > 0) {
                try {
                    $bindingService = new RecycleTemplateBindingService();
                    $resolve = $deviceId > 0
                        ? $bindingService->resolveByDeviceId($deviceId)
                        : $bindingService->resolveByTarget('model_dict', $categoryId);
                } catch (\Throwable $e) {
                    $resolve = null;
                }
                if (!empty($resolve['matched']) && (int)($resolve['check_template_id'] ?? 0) > 0) {
                    $resolvedTemplate = $this->templateModel->where([
                        ['site_id', '=', $this->site_id],
                        ['id', '=', (int)$resolve['check_template_id']],
                        ['status', '=', 1],
                    ])->findOrEmpty()->toArray();
                    if (!empty($resolvedTemplate)) {
                        $templateId = (int)$resolvedTemplate['id'];
                    } else {
                        $resolve['matched'] = false;
                    }
                }
            }
        }

        if ($templateId > 0) {
            $template = $this->info($templateId);
        } else {
            $scene = (string)($where['scene'] ?? 'phone');
            $this->ensureSingleDefault();
            $template = $this->templateModel->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
            ])->order('is_default desc,sort asc,id asc')->findOrEmpty()->toArray();
            if (empty($template)) {
                $this->initDefault();
                $template = $this->templateModel->where([
                    ['site_id', '=', $this->site_id],
                    ['status', '=', 1],
                ])->order('is_default desc,sort asc,id asc')->findOrEmpty()->toArray();
            }
        }
        if (empty($template)) {
            return ['template' => null, 'groups' => []];
        }

        $groups = $this->groupModel->where([
            ['site_id', '=', $this->site_id],
            ['template_id', '=', $template['id']],
            ['status', '=', 1],
        ])->order('sort asc,id asc')->select()->toArray();
        $fields = $this->fieldModel->where([
            ['site_id', '=', $this->site_id],
            ['template_id', '=', $template['id']],
            ['is_show', '=', 1],
        ])->order('sort asc,id asc')->select()->toArray();
        $fieldIds = array_column($fields, 'id');
        $options = empty($fieldIds) ? [] : $this->optionModel
            ->whereIn('field_id', $fieldIds)
            ->where('is_show', 1)
            ->order('sort asc,id asc')
            ->select()
            ->toArray();
        $optionMap = [];
        foreach ($options as $option) {
            $optionMap[(int)$option['field_id']][] = $this->formatOption($option);
        }
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[(int)$field['group_id']][] = $this->formatField($field, $optionMap[(int)$field['id']] ?? []);
        }
        foreach ($groups as &$group) {
            $group['fields'] = $fieldMap[(int)$group['id']] ?? [];
        }
        return [
            'template' => $template,
            'groups' => $groups,
            'resolve' => [
                'matched' => !empty($resolve['matched']),
                'source_type' => (string)($resolve['source_type'] ?? ''),
                'source_name' => (string)($resolve['source_name'] ?? ''),
                'template_id' => (int)$template['id'],
                'template_name' => (string)($template['template_name'] ?? ''),
            ],
        ];
    }

    public function initDefault(): array
    {
        $existing = $this->templateModel->where([
            ['site_id', '=', $this->site_id],
            ['template_key', '=', 'default_phone'],
        ])->findOrEmpty()->toArray();
        if (!empty($existing)) {
            $this->ensureSingleDefault((int)$existing['id']);
            return $this->schema(['template_id' => (int)$existing['id']]);
        }

        $template = $this->templateModel->create([
            'site_id' => $this->site_id,
            'template_key' => 'default_phone',
            'template_name' => '默认手机质检模板',
            'scene' => 'phone',
            'is_default' => 1,
            'status' => 1,
            'sort' => 0,
            'version' => 1,
        ]);
        $templateId = (int)$template->id;
        $this->ensureSingleDefault($templateId);

        $groups = [
            ['device_info', '设备信息', '容量、颜色、系统、保修、电池和锁', 10],
            ['appearance', '外观规格', '外屏、内屏、中框等外观判断', 20],
            ['issues', '问题记录', '功能异常和维修记录', 30],
        ];
        $groupIds = [];
        foreach ($groups as [$key, $name, $description, $sort]) {
            $group = $this->groupModel->create([
                'site_id' => $this->site_id,
                'template_id' => $templateId,
                'group_key' => $key,
                'group_name' => $name,
                'description' => $description,
                'sort' => $sort,
                'status' => 1,
            ]);
            $groupIds[$key] = (int)$group->id;
        }

        $this->createDefaultField($templateId, $groupIds['device_info'], 'capacity', '内存', 'input', '', '', '如 256GB', '', '内存{value}', 10);
        $this->createDefaultField($templateId, $groupIds['device_info'], 'color', '颜色', 'input', '', '', '如 深空黑色', '', '颜色{value}', 20);
        $this->createDefaultField($templateId, $groupIds['device_info'], 'system_version', '系统版本', 'input', '', '', '如 iOS 17.3.1', '', '系统{value}', 30);
        $this->createDefaultField($templateId, $groupIds['device_info'], 'warranty_info', '保修信息', 'input', '', '', '保修日期/过保/未激活', '', '保修: {value}', 40);
        $this->createDefaultField($templateId, $groupIds['device_info'], 'battery', '电池健康度', 'number', '', '%', '', '', '电池健康度{value}%', 50);
        $this->createDefaultField($templateId, $groupIds['device_info'], 'battery_num', '循环次数', 'number', '', '次', '', '', '循环{value}次', 60);
        $this->createDefaultField($templateId, $groupIds['device_info'], 'activation_lock', '激活锁', 'switch', '', '', '', '', '激活锁开启', 70);
        $this->createDefaultField($templateId, $groupIds['device_info'], 'mdm_lock', '监管锁', 'switch', '', '', '', '', '监管锁开启', 80);

        $this->createDefaultField($templateId, $groupIds['appearance'], 'screen_id', '外屏规格', 'radio', 'single', '', '', 'recycle_display', '外屏{label}', 10);
        $this->createDefaultField($templateId, $groupIds['appearance'], 'indisplay_id', '内屏规格', 'radio', 'single', '', '', 'recycle_indisplay', '内屏{label}', 20);
        $this->createDefaultField($templateId, $groupIds['appearance'], 'appearance_id', '中框规格', 'radio', 'single', '', '', 'recycle_appearance', '中框{label}', 30);

        $this->createDefaultField($templateId, $groupIds['issues'], 'function_ids', '功能异常', 'checkbox', 'multiple', '', '', 'recycle_function', '功能: {labels}', 10);
        $this->createDefaultField($templateId, $groupIds['issues'], 'fix_ids', '维修记录', 'checkbox', 'multiple', '', '', 'recycle_fix', '维修记录: {labels}', 20);

        return $this->schema(['template_id' => $templateId]);
    }

    private function createDefaultField(int $templateId, int $groupId, string $key, string $name, string $component, string $mode, string $unit, string $placeholder, string $dictKey, string $resultTemplate, int $sort): void
    {
        $field = $this->fieldModel->create([
            'site_id' => $this->site_id,
            'template_id' => $templateId,
            'group_id' => $groupId,
            'field_key' => $key,
            'field_name' => $name,
            'component' => $component,
            'selection_mode' => $mode,
            'unit' => $unit,
            'placeholder' => $placeholder,
            'default_value' => '',
            'is_required' => 0,
            'is_show' => 1,
            'seller_visible' => 1,
            'buyer_visible' => 0,
            'result_visible' => 1,
            'result_template' => $resultTemplate,
            'api_fill_enabled' => in_array($key, ['capacity', 'color', 'system_version', 'warranty_info', 'activation_lock', 'mdm_lock'], true) ? 1 : 0,
            'api_fill_policy' => 'overwrite',
            'sort' => $sort,
            'extra_config' => [],
        ]);
        if ($dictKey !== '') {
            $this->createOptionsFromDict((int)$field->id, $dictKey);
        }
    }

    private function createOptionsFromDict(int $fieldId, string $dictKey): void
    {
        $dict = Db::name('sys_dict')->where('key', $dictKey)->find();
        $items = [];
        if (!empty($dict['dictionary'])) {
            $decoded = is_string($dict['dictionary']) ? json_decode($dict['dictionary'], true) : $dict['dictionary'];
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }
        if (empty($items)) {
            $items = $this->fallbackDictItems($dictKey);
        }
        foreach ($items as $index => $item) {
            $label = (string)($item['name'] ?? $item['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $this->optionModel->create([
                'site_id' => $this->site_id,
                'field_id' => $fieldId,
                'option_label' => $label,
                'option_value' => (string)($item['value'] ?? ($index + 1)),
                'is_default' => 0,
                'is_show' => 1,
                'sort' => (int)($item['sort'] ?? $index),
                'extra_config' => [],
            ]);
        }
    }

    private function fallbackDictItems(string $dictKey): array
    {
        $defaults = [
            'recycle_display' => ['无划痕', '细微划痕', '小划痕', '明显划痕', '硬划痕', '外爆', '内爆', '未知部件', '官方提示'],
            'recycle_indisplay' => ['正常', '漏液', '老化', '亮点/坏点', '阴阳屏', '烧屏', '内爆'],
            'recycle_appearance' => ['无磕碰', '细微划痕', '轻微氧化', '中度磨损', '重度磨损', '严重损坏', '组装壳', '组装后玻璃'],
            'recycle_function' => ['通话', '充电', '指纹', '面容', 'WiFi', '蓝牙', '指南针', 'NFC', '振动', '重力', '距离感应', '光线感应', '闪光', '触摸', '主麦', '前麦', '后麦', '扬声器', '听筒', '网络锁', '按键', '前摄', '后摄'],
            'recycle_fix' => ['原装', '换屏', '换电池', '换后盖', '换摄像头', '主板维修', '其他维修'],
        ];
        return array_map(static fn($name, $index) => ['name' => $name, 'value' => (string)($index + 1), 'sort' => $index], $defaults[$dictKey] ?? [], array_keys($defaults[$dictKey] ?? []));
    }

    private function formatField(array $field, array $options): array
    {
        return [
            'id' => (int)$field['id'],
            'field_key' => $field['field_key'],
            'field_name' => $field['field_name'],
            'component' => $field['component'],
            'selection_mode' => $field['selection_mode'],
            'unit' => $field['unit'],
            'placeholder' => $field['placeholder'],
            'default_value' => $field['default_value'],
            'is_required' => (int)$field['is_required'],
            'seller_visible' => (int)$field['seller_visible'],
            'buyer_visible' => (int)$field['buyer_visible'],
            'result_visible' => (int)$field['result_visible'],
            'result_template' => $field['result_template'],
            'api_fill_enabled' => (int)$field['api_fill_enabled'],
            'api_fill_policy' => $field['api_fill_policy'],
            'sort' => (int)$field['sort'],
            'extra_config' => $field['extra_config'] ?? [],
            'options' => $options,
        ];
    }

    private function formatOption(array $option): array
    {
        return [
            'id' => (int)$option['id'],
            'name' => $option['option_label'],
            'label' => $option['option_label'],
            'value' => (string)$option['option_value'],
            'is_default' => (int)$option['is_default'],
            'sort' => (int)$option['sort'],
            'extra_config' => $option['extra_config'] ?? [],
        ];
    }

    private function normalizeJsonConfig($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function assertSummaryFieldLimit(int $templateId, int $fieldId, array $extraConfig): void
    {
        if ((int)($extraConfig['summary_visible'] ?? 0) !== 1) {
            return;
        }

        $fields = $this->fieldModel->where([
            ['site_id', '=', $this->site_id],
            ['template_id', '=', $templateId],
        ])->when($fieldId > 0, function ($query) use ($fieldId) {
            $query->where('id', '<>', $fieldId);
        })->field('extra_config')->select()->toArray();

        $count = 0;
        foreach ($fields as $field) {
            $config = $this->normalizeJsonConfig($field['extra_config'] ?? []);
            if ((int)($config['summary_visible'] ?? 0) === 1) {
                $count++;
            }
        }

        if ($count >= 5) {
            throw new CommonException('设备摘要最多展示5个字段');
        }
    }

    private function touchTemplate(int $templateId): void
    {
        $this->templateModel->where('id', $templateId)->update([
            'version' => Db::raw('version + 1'),
            'update_at' => time(),
        ]);
    }

    private function ensureSingleDefault(int $preferredId = 0): void
    {
        Db::transaction(function () use ($preferredId) {
            if ($preferredId > 0) {
                $preferred = $this->templateModel->where([
                    ['id', '=', $preferredId],
                    ['site_id', '=', $this->site_id],
                ])->findOrEmpty()->toArray();
                if (!empty($preferred)) {
                    $this->templateModel->where([
                        ['site_id', '=', $this->site_id],
                    ])->update(['is_default' => 0]);
                    $this->templateModel->where('id', $preferredId)->update(['is_default' => 1, 'status' => 1]);
                    return;
                }
            }

            $defaultIds = $this->templateModel->where([
                ['site_id', '=', $this->site_id],
                ['is_default', '=', 1],
            ])->order('status desc,sort asc,id asc')->column('id');

            if (!empty($defaultIds)) {
                $keepId = (int)$defaultIds[0];
                $this->templateModel->where([
                    ['site_id', '=', $this->site_id],
                ])->where('id', '<>', $keepId)->update(['is_default' => 0]);
                $this->templateModel->where('id', $keepId)->update(['status' => 1]);
                return;
            }

            $fallbackId = (int)$this->templateModel->where([
                ['site_id', '=', $this->site_id],
            ])->order('status desc,sort asc,id asc')->value('id');
            if ($fallbackId > 0) {
                $this->templateModel->where([
                    ['site_id', '=', $this->site_id],
                ])->where('id', '<>', $fallbackId)->update(['is_default' => 0]);
                $this->templateModel->where('id', $fallbackId)->update(['is_default' => 1, 'status' => 1]);
            }
        });
    }
}
