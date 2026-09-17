<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\support;

class QuoteSearch
{
    public static function keyword(string $value): string
    {
        return mb_substr(trim((string)preg_replace('/\s+/u', ' ', $value)), 0, 80);
    }

    public static function modelTerm(string $value): string
    {
        $value = mb_strtolower((string)preg_replace('/[\s　]+/u', '', self::keyword($value)));
        // 报价源可能只写 17ProMax；品牌单独搜索时仍保留品牌词。
        if (preg_match('/\d/u', $value)) {
            $value = str_replace(['iphone', '苹果'], '', $value);
        }
        return $value;
    }

    public static function likePattern(string $value): string
    {
        return '%' . strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }

    /** 隐藏父分类下的报价不能通过搜索绕过展示开关。 */
    public static function visibleCategoryIds(array $categories): array
    {
        $map = array_column($categories, null, 'id');
        $ids = [0];
        foreach ($map as $id => $category) {
            $current = (int)$id;
            $visited = [];
            while ($current > 0) {
                $node = $map[$current] ?? null;
                if (!$node || isset($visited[$current]) || (int)$node['is_show'] !== 1
                    || (int)$node['source_id'] !== (int)$category['source_id']) {
                    continue 2;
                }
                $visited[$current] = true;
                $current = (int)$node['parent_id'];
            }
            $ids[] = (int)$id;
        }
        return $ids;
    }
}
