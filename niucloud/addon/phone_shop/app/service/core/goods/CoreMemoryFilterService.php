<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\GoodsSpecGroup;
use addon\phone_shop\app\model\goods\GoodsSpecItem;
use addon\phone_shop\app\support\GoodsMemorySpec;
use app\service\core\sys\CoreConfigService;

/** 内存/表盘/其他规格筛选只消费本站维护的规格字典，不从库存反推候选项。 */
class CoreMemoryFilterService
{
    private array $catalogs = [];
    private array $categories = [];
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
        if (!isset($this->variants[$siteId])) {
            $config = (new CoreConfigService())->getConfigValue($siteId, GoodsMemorySpec::CONFIG_KEY);
            $aliases = is_array($config) ? (array)($config['memory_aliases'] ?? []) : [];
            $known = [];
            foreach ($this->groups($siteId) as $group) {
                foreach ($group['items'] as $item) {
                    $raw = (string)$item['value'];
                    $normal = GoodsMemorySpec::normalize($raw, $aliases);
                    $variants = array_values(array_unique(array_filter([$raw, $normal], static fn($v) => $v !== '')));
                    // 后台写 1TB、存量商品写 1T 时，展示和点击查询要能双向匹配。
                    // 只扩展单容量的单位，不拆开 12GB+256GB 或改写手表尺寸。
                    if (preg_match('/^\d+[gt]b?$/iD', $raw) && preg_match('/^(\d+[GT])B$/D', $normal, $m)
                        && GoodsMemorySpec::normalize($m[1], $aliases) === $normal && !in_array($m[1], $variants, true)) {
                        $variants[] = $m[1];
                    }
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

    /** 给一批在售商品复用的分类归属查询。并选手机和手表时，各自只消费自己分类的字典。 */
    public function bindings(int $siteId, array $groups): callable
    {
        $groupIds = array_column($groups, 'group_id');
        $inScope = array_fill_keys($groupIds, true);
        $bound = [];
        foreach ($this->catalogs[$siteId][0] ?? [] as $group) {
            $gid = (int)$group['group_id'];
            if (!isset($inScope[$gid])) continue;
            $ids = $group['category_ids'] ?? [];
            if (is_string($ids)) $ids = explode(',', $ids);
            $ids = is_array($ids) ? $ids : [];
            $ids[] = $group['category_id'] ?? 0;
            foreach ($ids as $id) {
                if ((int)$id > 0) $bound[(int)$id][$gid] = true;
            }
        }
        $parents = array_column($this->categories[$siteId] ?? [], 'pid', 'category_id');
        $byCategory = [];
        return static function ($path) use ($bound, $parents, &$byCategory, $groupIds): array {
            $ids = is_array($path) ? $path : json_decode((string)$path, true);
            $nodes = $ancestors = [];
            foreach ((array)$ids as $id) {
                $id = (int)$id;
                if (!isset($parents[$id])) continue;
                if (!isset($byCategory[$id])) {
                    $node = $id;
                    $nearest = $visited = [];
                    while ($node > 0 && isset($parents[$node]) && !isset($visited[$node])) {
                        $visited[$node] = true;
                        if (!$nearest && isset($bound[$node])) $nearest = $bound[$node];
                        $node = (int)$parents[$node];
                    }
                    unset($visited[$id]);
                    $byCategory[$id] = [$nearest, $visited];
                }
                $nodes[$id] = true;
                $ancestors += $byCategory[$id][1];
            }
            // 商品常保存完整路径。手表叶子已有自己的表径组时，不再叠加上级的通用苹果容量组。
            // 多条独立挂类路径分别继承自己的最近绑定。
            $allowed = [];
            foreach (array_diff_key($nodes, $ancestors) as $leaf => $_) $allowed += $byCategory[$leaf][0];
            return array_values(array_intersect($groupIds, array_keys($allowed)));
        };
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
