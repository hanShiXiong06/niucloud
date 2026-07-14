<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use core\base\BaseAdminService;
use think\facade\Db;

class ErpGoodsMetaService extends BaseAdminService
{
    public function meta(array $where = []): array
    {
        $shopMeta = $this->phoneShopMeta(0, []);
        $titleRules = $this->titleRules();
        if ($shopMeta['available']) {
            return [
                'source' => 'phone_shop',
                'source_label' => '商城商品资料',
                'category_source' => 'erp_catalog',
                'catalog_endpoint' => 'erp/goods/catalog/hierarchy',
                'mode' => 'catalog_master',
                'title_rules' => $titleRules,
                'custom_fields' => $this->customFields(),
                'tips' => [
                    '商品目录只从 ERP 产品目录读取，商城不再作为分类主数据。',
                    '商城规格与成色只作为渠道属性，不再反向生成 ERP 分类。',
                ],
                'categories' => [],
                'specs' => $shopMeta['specs'],
            ];
        }

        return [
            'source' => 'erp',
            'source_label' => 'ERP 本地资料',
            'category_source' => 'erp_catalog',
            'catalog_endpoint' => 'erp/goods/catalog/hierarchy',
            'mode' => 'catalog_master',
            'title_rules' => $titleRules,
            'custom_fields' => $this->customFields(),
            'tips' => [
                '当前未检测到可用商城资料，ERP 使用本地默认规格。',
                '本地规格不绑定分类，适合 ERP 单独安装时快速录入。',
            ],
            'categories' => [],
            'specs' => $this->erpFallbackSpecs(),
        ];
    }

    private function phoneShopMeta(int $categoryId, array $categoryPath = []): array
    {
        try {
            if (!$this->isPhoneShopEnabled()) {
                return ['available' => false, 'categories' => [], 'specs' => []];
            }
            $categories = $this->phoneShopCategories();
            $specs = [
                'groups' => $this->phoneShopSpecGroups($categoryId, $categoryPath),
                'grades' => $this->phoneShopGrades(),
                'memory_groups' => $this->phoneShopMemoryGroups(),
                'colors' => [],
            ];
            $available = !empty($categories) || !empty($specs['groups']) || !empty($specs['grades']) || !empty($specs['memory_groups']);
            return compact('available', 'categories', 'specs');
        } catch (\Throwable) {
            return ['available' => false, 'categories' => [], 'specs' => []];
        }
    }

    private function isPhoneShopEnabled(): bool
    {
        return in_array('phone_shop', $this->getCurrentSiteAddonKeys(), true);
    }

    private function getCurrentSiteAddonKeys(): array
    {
        $site = $this->safeRead(function () {
            return Db::name('site')
                ->where('site_id', $this->site_id)
                ->field('group_id,app_type,app,addons')
                ->find() ?: [];
        }, []);
        if (!$site) {
            return [];
        }

        $keys = array_merge(
            $this->normalizeAddonKeys($site['app'] ?? []),
            $this->normalizeAddonKeys($site['addons'] ?? [])
        );
        if (($site['app_type'] ?? '') === 'site' && (int)($site['group_id'] ?? 0) > 0) {
            $group = $this->safeRead(function () use ($site) {
                return Db::name('site_group')
                    ->where('group_id', (int)$site['group_id'])
                    ->field('app,addon')
                    ->find() ?: [];
            }, []);
            $keys = array_merge(
                $keys,
                $this->normalizeAddonKeys($group['app'] ?? []),
                $this->normalizeAddonKeys($group['addon'] ?? [])
            );
        }

        return array_values(array_unique(array_filter(array_map(static fn($key) => (string)$key, $keys))));
    }

    private function normalizeAddonKeys($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function phoneShopCategories(): array
    {
        $service = 'addon\\phone_shop\\app\\service\\admin\\goods\\CategoryService';
        if (!class_exists($service)) {
            return [];
        }
        try {
            return (array)(new $service())->getTree();
        } catch (\Throwable) {
            return [];
        }
    }

    private function phoneShopSpecGroups(int $categoryId, array $categoryPath = []): array
    {
        return $this->safeRead(function () use ($categoryId, $categoryPath) {
            $categoryIds = array_values(array_unique(array_filter(array_map('intval', array_merge([$categoryId], $categoryPath)))));
            $query = Db::name('phone_shop_goods_spec_group')
                ->where('site_id', $this->site_id)
                ->order('sort asc, group_id asc');
            if ($categoryIds) {
                $query->where(function ($q) use ($categoryIds) {
                    $q->whereIn('category_id', $categoryIds);
                    foreach ($categoryIds as $cid) {
                        $q->whereOr(function ($qq) use ($cid) {
                            $qq->whereFindInSet('category_ids', $cid);
                        });
                    }
                });
            }
            $groups = $query->select()->toArray();
            if (!$groups) {
                return [];
            }
            $groupIds = array_values(array_filter(array_map(static fn($row) => (int)($row['group_id'] ?? 0), $groups)));
            $items = $groupIds ? Db::name('phone_shop_goods_spec_item')
                ->where('site_id', $this->site_id)
                ->whereIn('group_id', $groupIds)
                ->order('sort asc, item_id asc')
                ->select()
                ->toArray() : [];
            $itemMap = [];
            foreach ($items as $item) {
                $itemMap[(int)$item['group_id']][] = [
                    'id' => (int)$item['item_id'],
                    'label' => (string)$item['item_value'],
                    'value' => (string)$item['item_value'],
                    'sort' => (int)($item['sort'] ?? 0),
                    'source_id' => (int)$item['item_id'],
                ];
            }
            $normalized = array_map(function ($group) use ($itemMap) {
                $id = (int)$group['group_id'];
                return [
                    'id' => $id,
                    'label' => (string)($group['label'] ?: '规格'),
                    'key' => 'spec_' . $id,
                    'category_id' => (int)($group['category_id'] ?? 0),
                    'category_ids' => (string)($group['category_ids'] ?? ''),
                    'title_part' => $this->isTitleSpec((string)($group['label'] ?? '规格')),
                    'items' => $itemMap[$id] ?? [],
                    'source_id' => $id,
                ];
            }, $groups);
            return $normalized;
        }, []);
    }

    private function phoneShopGrades(): array
    {
        return $this->safeRead(function () {
            return array_map(static fn($row) => [
                'id' => (int)$row['grade_id'],
                'label' => (string)$row['grade_name'],
                'value' => (string)$row['grade_name'],
                'sort' => (int)($row['sort'] ?? 0),
                'source_id' => (int)$row['grade_id'],
            ], Db::name('phone_shop_goods_grade')
                ->where('site_id', $this->site_id)
                ->where('status', 1)
                ->order('sort asc, grade_id asc')
                ->select()
                ->toArray());
        }, []);
    }

    private function phoneShopMemoryGroups(): array
    {
        return $this->safeRead(function () {
            $specRows = Db::name('phone_shop_memory_spec')
                ->where('site_id', $this->site_id)
                ->order('sort asc, spec_id asc')
                ->select()
                ->toArray();
            $specMap = [];
            foreach ($specRows as $row) {
                $specMap[(int)$row['spec_id']] = [
                    'id' => (int)$row['spec_id'],
                    'label' => (string)$row['spec_name'],
                    'value' => (string)$row['spec_name'],
                    'sort' => (int)($row['sort'] ?? 0),
                    'source_id' => (int)$row['spec_id'],
                ];
            }
            return array_map(static function ($group) use ($specMap) {
                $ids = array_values(array_filter(array_map('intval', explode(',', (string)($group['memory_ids'] ?? '')))));
                return [
                    'id' => (int)$group['group_id'],
                    'label' => (string)$group['group_name'],
                    'key' => 'memory_' . (int)$group['group_id'],
                    'items' => array_values(array_filter(array_map(static fn($id) => $specMap[$id] ?? null, $ids))),
                    'source_id' => (int)$group['group_id'],
                ];
            }, Db::name('phone_shop_memory_group')
                ->where('site_id', $this->site_id)
                ->order('sort asc, group_id asc')
                ->select()
                ->toArray());
        }, []);
    }

    private function erpFallbackSpecs(): array
    {
        $localSpecs = $this->safeRead(function () {
            return (new ErpGoodsSpecService())->meta();
        }, []);
        if (!empty($localSpecs['groups']) || !empty($localSpecs['grades'])) {
            return array_merge([
                'groups' => [],
                'grades' => [],
                'memory_groups' => [],
                'colors' => [],
            ], $localSpecs);
        }
        return [
            'groups' => [
                ['key' => 'memory_ios', 'label' => '苹果内存', 'title_part' => true, 'items' => $this->options(['64G', '128G', '256G', '512G', '1T'])],
                ['key' => 'memory_android', 'label' => '安卓内存', 'title_part' => true, 'items' => $this->options(['4+64G', '6+128G', '8+128G', '8+256G', '12+256G', '16+512G'])],
            ],
            'grades' => $this->options(['全新', '99新', '95新', '9成新', '8成新', '小花', '大花']),
            'memory_groups' => [],
            'colors' => [],
        ];
    }

    private function options(array $values): array
    {
        return array_map(static fn($value, $index) => [
            'id' => $index + 1,
            'label' => $value,
            'value' => $value,
            'sort' => ($index + 1) * 10,
        ], array_values($values), array_keys(array_values($values)));
    }

    private function safeRead(callable $callback, array $default): array
    {
        try {
            return (array)$callback();
        } catch (\Throwable) {
            return $default;
        }
    }

    private function defaultTitleRules(): array
    {
        return [
            'category_mode' => 'auto',
            'category_modes' => [
                ['label' => '自动', 'value' => 'auto', 'desc' => '三级分类默认取二级+三级，二级分类取一级+二级'],
                ['label' => '一级+二级', 'value' => 'level_1_2'],
                ['label' => '二级+三级', 'value' => 'level_2_3'],
                ['label' => '单独三级', 'value' => 'level_3'],
                ['label' => '完整路径', 'value' => 'full'],
            ],
            'spec_in_title' => true,
            'spec_title_labels' => ['内存', '容量', '颜色', '色'],
            'grade_in_title' => false,
            'separator' => ' ',
        ];
    }

    private function titleRules(): array
    {
        $rules = $this->defaultTitleRules();
        $config = (new ErpConfigService())->getRules()['product_title'] ?? [];

        if (isset($config['category_mode'])) {
            $rules['category_mode'] = (string)$config['category_mode'];
        }
        if (array_key_exists('spec_in_title', $config)) {
            $rules['spec_in_title'] = (int)$config['spec_in_title'] === 1;
        }
        if (array_key_exists('grade_in_title', $config)) {
            $rules['grade_in_title'] = (int)$config['grade_in_title'] === 1;
        }
        if (isset($config['separator'])) {
            $separator = trim((string)$config['separator']);
            $rules['separator'] = $separator === '' ? ' ' : $separator;
        }

        return $rules;
    }

    private function customFields(): array
    {
        return [
            [
                'key' => 'color',
                'label' => '颜色',
                'type' => 'text',
                'searchable' => true,
                'title_part' => true,
                'placeholder' => '输入或检索颜色',
                'source' => 'dict',
            ],
            [
                'key' => 'battery',
                'label' => '电池',
                'type' => 'number',
                'suffix' => '%',
                'min' => 0,
                'max' => 100,
                'placeholder' => '输入电池效率',
            ],
            [
                'key' => 'warranty',
                'label' => '保修截止',
                'type' => 'date',
                'placeholder' => '选择保修截止时间',
            ],
        ];
    }

    private function isTitleSpec(string $label): bool
    {
        foreach ($this->defaultTitleRules()['spec_title_labels'] as $keyword) {
            if ($keyword !== '' && mb_strpos($label, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function normalizeCategoryPath($path): array
    {
        if (is_string($path)) {
            $decoded = json_decode($path, true);
            if (is_array($decoded)) {
                $path = $decoded;
            } else {
                $path = $path === '' ? [] : explode(',', $path);
            }
        }
        if (!is_array($path)) {
            return [];
        }
        return array_values(array_filter(array_map(static function ($item) {
            if (is_array($item)) {
                return (int)($item['category_id'] ?? $item['id'] ?? 0);
            }
            return (int)$item;
        }, $path)));
    }
}
