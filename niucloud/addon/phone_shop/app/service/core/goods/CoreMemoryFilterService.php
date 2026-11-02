<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\GoodsSpecGroup;
use addon\phone_shop\app\model\goods\GoodsSpecItem;
use addon\phone_shop\app\service\core\intake\CoreListingMappingService;

/** 内存/表盘/其他规格筛选只消费本站维护的规格字典，不从库存反推候选项。 */
class CoreMemoryFilterService
{
    private array $catalogs = [];
    private array $categories = [];
    private array $mappingConfigs = [];
    private array $variants = [];

    public function groups(int $siteId, array $categoryIds = []): array
    {
        if ($siteId <= 0) return [];
        if (!isset($this->catalogs[$siteId])) {
            $groups = GoodsSpecGroup::where('site_id', $siteId)->order('sort asc,group_id desc')->select()->toArray();
            $ids = array_column($groups, 'group_id');
            $items = $ids === [] ? [] : GoodsSpecItem::where('site_id', $siteId)->whereIn('group_id', $ids)
                ->order('sort asc,item_id asc')->select()->toArray();
            $this->catalogs[$siteId] = [$groups, $items];
        }
        if ($categoryIds !== [] && !isset($this->categories[$siteId])) {
            $this->categories[$siteId] = Category::where('site_id', $siteId)->field('category_id,pid')->select()->toArray();
        }
        return $this->buildGroups($siteId, $this->catalogs[$siteId][0], $this->catalogs[$siteId][1], $categoryIds, $this->categories[$siteId] ?? []);
    }

    /** 查询仍使用商品现有 memory_group 字段；只扩展字典值的已知建品规范形式。 */
    public function filterValues(int $siteId, array $selected): array
    {
        $values = [];
        $selected = array_values(array_unique(array_filter(array_map(static fn($v) => is_scalar($v) ? trim((string)$v) : '', $selected), static fn($v) => $v !== '')));
        if ($selected === []) return [];
        if (!isset($this->mappingConfigs[$siteId])) {
            $this->mappingConfigs[$siteId] = (new CoreListingMappingService())->getConfig($siteId);
        }
        if (!isset($this->variants[$siteId])) {
            $mapper = new CoreListingMappingService();
            $aliases = (array)($this->mappingConfigs[$siteId]['memory_aliases'] ?? []);
            $known = [];
            foreach ($this->groups($siteId) as $group) {
                foreach ($group['items'] as $item) {
                    $raw = (string)$item['value'];
                    $normal = $mapper->normalizeMemory($raw, $aliases);
                    $variants = array_values(array_unique(array_filter([$raw, $normal], static fn($v) => $v !== '')));
                    foreach ($variants as $variant) $known[$variant] = array_values(array_unique(array_merge($known[$variant] ?? [], $variants)));
                }
            }
            $this->variants[$siteId] = $known;
        }
        foreach ($selected as $value) {
            // 明确的直传筛选值仍可精确查询，但不会变成对外展示选项或猜测更多脏值。
            foreach ($this->variants[$siteId][$value] ?? [$value] as $variant) $values[$variant] = true;
        }
        return array_map('strval', array_keys($values));
    }

    /** 纯组合逻辑供接口与隔离回归复用；再次核对站点，避免关系查询漏隔离。 */
    public function buildGroups(int $siteId, array $groups, array $items, array $categoryIds = [], array $categories = []): array
    {
        if ($siteId <= 0) return [];
        $parents = [];
        foreach ($categories as $category) {
            if (isset($category['site_id']) && (int)$category['site_id'] !== $siteId) continue;
            $id = (int)($category['category_id'] ?? 0);
            if ($id > 0) $parents[$id] = (int)($category['pid'] ?? 0);
        }
        $requested = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn($id) => $id > 0)));
        $selected = array_values(array_filter($requested, static fn($id) => array_key_exists($id, $parents)));
        if ($categoryIds !== [] && $selected === []) return [];
        $related = function (int $left, int $right) use ($parents): bool {
            $walk = static function (int $node, int $ancestor) use ($parents): bool {
                $seen = [];
                while ($node > 0 && !isset($seen[$node])) {
                    if ($node === $ancestor) return true;
                    $seen[$node] = true;
                    $node = $parents[$node] ?? 0;
                }
                return false;
            };
            return isset($parents[$left], $parents[$right]) && ($walk($left, $right) || $walk($right, $left));
        };
        usort($groups, static fn($a, $b) => ((int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0)) ?: ((int)($b['group_id'] ?? 0) <=> (int)($a['group_id'] ?? 0)));
        usort($items, static fn($a, $b) => ((int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0)) ?: ((int)($a['item_id'] ?? 0) <=> (int)($b['item_id'] ?? 0)));
        $itemsByGroup = [];
        foreach ($items as $item) $itemsByGroup[(int)($item['group_id'] ?? 0)][] = $item;
        $result = [];
        foreach ($groups as $group) {
            $id = (int)($group['group_id'] ?? 0);
            $name = trim((string)($group['label'] ?? ''));
            if ((int)($group['site_id'] ?? 0) !== $siteId || $id <= 0 || $name === '' || isset($result[$id])) continue;
            $bound = $group['category_ids'] ?? [];
            if (is_string($bound)) $bound = explode(',', $bound);
            $bound = is_array($bound) ? $bound : [];
            $bound[] = $group['category_id'] ?? 0;
            $bound = array_values(array_unique(array_filter(array_map('intval', $bound), static fn($value) => $value > 0)));
            if ($selected !== []) {
                $matched = false;
                foreach ($selected as $selectedId) foreach ($bound as $boundId) if ($related($selectedId, $boundId)) $matched = true;
                if (!$matched) continue;
            }
            $options = $seen = [];
            foreach ($itemsByGroup[$id] ?? [] as $item) {
                $value = trim((string)($item['item_value'] ?? ''));
                $itemId = (int)($item['item_id'] ?? 0);
                if ((int)($item['site_id'] ?? 0) !== $siteId || (int)($item['group_id'] ?? 0) !== $id
                    || $itemId <= 0 || $value === '' || isset($seen[$value])) continue;
                $seen[$value] = true;
                $options[] = ['item_id' => $itemId, 'value' => $value, 'label' => $value, 'sort' => (int)($item['sort'] ?? 0)];
            }
            if ($options === []) continue;
            $result[$id] = ['group_id' => $id, 'group_name' => $name, 'label' => $name, 'sort' => (int)($group['sort'] ?? 0), 'items' => $options];
        }
        return array_values($result);
    }

    public function flatten(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) foreach ((array)($group['items'] ?? []) as $item) {
            $value = (string)($item['value'] ?? '');
            if ($value !== '' && !isset($items[$value])) $items[$value] = ['value' => $value, 'label' => (string)($item['label'] ?? $value)];
        }
        return array_values($items);
    }
}
