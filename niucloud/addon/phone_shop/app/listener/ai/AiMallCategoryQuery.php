<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

use addon\phone_shop\app\service\api\goods\GoodsCategoryService;

/** 将商城已公开的分类树投影为 AI 可检索的稳定节点，不暴露后台字段。 */
final class AiMallCategoryQuery
{
    public function query(int $siteId, string $keyword = '', int $limit = 100): array
    {
        if ($siteId <= 0 || $siteId !== (int)request()->siteId()) return [];
        $limit = max(1, min($limit, 200));
        $tree = (new GoodsCategoryService())->getTree();
        $rows = [];
        $walk = function (array $nodes, array $parents = []) use (&$walk, &$rows): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) continue;
                $name = trim((string)($node['category_name'] ?? ''));
                $pathNames = array_values(array_filter(array_merge($parents, [$name])));
                $children = array_values(array_filter((array)($node['child_list'] ?? []), 'is_array'));
                $rows[] = [
                    'category_id' => (int)($node['category_id'] ?? 0),
                    'pid' => (int)($node['pid'] ?? 0),
                    'name' => $name,
                    'full_name' => trim((string)($node['category_full_name'] ?? '')) ?: implode(' / ', $pathNames),
                    'path' => implode(' / ', $pathNames),
                    'level' => (int)($node['level'] ?? count($parents) + 1),
                    'has_children' => $children !== [],
                ];
                if ($children !== []) $walk($children, $pathNames);
            }
        };
        $walk((array)$tree);

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $matched = array_values(array_filter($rows, static function (array $row) use ($keyword): bool {
                return mb_stripos((string)$row['name'], $keyword) !== false
                    || mb_stripos((string)$row['full_name'], $keyword) !== false
                    || mb_stripos((string)$row['path'], $keyword) !== false;
            }));
            if ($matched !== []) $rows = $matched;
        }

        return array_slice(array_values(array_filter($rows, static fn(array $row): bool => $row['category_id'] > 0 && $row['name'] !== '')), 0, $limit);
    }
}
