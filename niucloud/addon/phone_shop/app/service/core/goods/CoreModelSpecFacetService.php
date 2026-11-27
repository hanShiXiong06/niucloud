<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\support\GoodsScopedSpec;

/** 已选分类的规格、颜色和可点击结果共用同一份在售范围、同一套规格识别。 */
final class CoreModelSpecFacetService
{
    public function read(int $siteId, CoreMemoryFilterService $catalog, array $groups, array $categories,
        array $brands, array $grades, array $colors, array $selected, bool $collectIds = false): array
    {
        $empty = ['groups' => [], 'colors' => [], 'ids' => []];
        $categories = array_values(array_unique(array_filter(array_map(
            static fn($v) => is_scalar($v) && ctype_digit((string)$v) ? (int)$v : 0, $categories
        ))));
        if ($siteId <= 0 || !$groups || !$categories) return $empty;
        $bind = $catalog->bindings($siteId, $groups);
        $byGroup = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $value = (string)$item['value'];
                $byGroup[(int)$group['group_id']][$value] = $catalog->filterValues($siteId, [$value]);
            }
        }

        $query = Goods::where([
            ['g.site_id', '=', $siteId], ['g.is_gift', '=', GoodsDict::NOT_IS_GIFT],
            ['g.status', '=', 1], ['g.sale_status', '=', 'available'], ['g.is_online_sellable', '=', 1],
            ['g.delete_time', '=', 0], ['g.stock', '>', 0], ['sku.is_default', '=', 1], ['sku.stock', '>', 0],
        ])->alias('g')->join('phone_shop_goods_sku sku', 'sku.goods_id = g.goods_id AND sku.site_id = g.site_id')
            ->withSearch(['goods_category'], ['goods_category' => $categories]);
        if ($brands) $query->whereIn('g.brand_id', $brands);
        if ($grades) $query->whereIn('g.condition_grade', $grades);
        if ($collectIds && $colors) $query->whereIn('g.device_color', $colors);
        $colorSet = array_fill_keys($colors, true);
        $lookups = $seenItems = $seenColors = $ids = [];
        $selectedVariants = $catalog->filterValues($siteId, $selected);
        foreach ($query->field('g.goods_id,g.goods_category,g.goods_name,g.memory_group,g.device_color')->cursor() as $row) {
            $groupIds = $bind($row['goods_category']);
            $key = implode(',', $groupIds);
            if (!isset($lookups[$key])) {
                $variants = $owners = [];
                foreach ($groupIds as $groupId) {
                    foreach ($byGroup[$groupId] ?? [] as $value => $aliases) {
                        $variants[$value] = $aliases;
                        $owners[$value][] = $groupId;
                    }
                }
                $lookup = GoodsScopedSpec::lookup($variants);
                $wanted = [];
                foreach ($selectedVariants as $chosen) {
                    foreach (GoodsScopedSpec::matchingValues((string)$chosen, '', $lookup) as $value) $wanted[$value] = true;
                }
                $lookups[$key] = [$lookup, $owners, $wanted];
            }

            [$lookup, $owners, $wanted] = $lookups[$key];
            $matches = GoodsScopedSpec::matchingValues((string)$row['memory_group'], (string)$row['goods_name'], $lookup);
            $color = (string)$row['device_color'];
            $colorMatches = !$colorSet || isset($colorSet[$color]);
            $specMatches = !$selected;
            foreach ($matches as $value) {
                if (isset($wanted[$value])) $specMatches = true;
                if (!$collectIds && $colorMatches) {
                    foreach ($owners[$value] as $groupId) $seenItems[$groupId][$value] = true;
                }
            }
            if (!$specMatches) continue;
            if ($collectIds) {
                if ($colorMatches) $ids[(int)$row['goods_id']] = true;
            } elseif ($color !== '') {
                $seenColors[$color] = true;
            }
        }
        if ($collectIds) return ['groups' => [], 'colors' => [], 'ids' => array_keys($ids)];

        $filtered = [];
        foreach ($groups as $group) {
            $values = $seenItems[$group['group_id']] ?? [];
            $group['items'] = array_values(array_filter($group['items'], static fn($item) => isset($values[$item['value']])));
            if ($group['items']) $filtered[] = $group;
        }
        $availableColors = array_map('strval', array_keys($seenColors));
        sort($availableColors, SORT_STRING);
        return ['groups' => $filtered, 'colors' => $availableColors, 'ids' => []];
    }
}
