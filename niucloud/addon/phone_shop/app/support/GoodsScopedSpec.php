<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

/** 只识别给定分类字典的规格，不把旧数字编号解释成容量。 */
final class GoodsScopedSpec
{
    /** 展示值 => 查询别名；同一分类的多件商品共用一次构建的索引。 */
    public static function lookup(array $dictVariants): array
    {
        $index = ['exact' => [], 'raw' => [], 'tokens' => [], 'kinds' => []];
        foreach ($dictVariants as $display => $variants) {
            $display = trim((string)$display);
            if ($display === '') continue;
            $index['exact'][self::key($display)] = $display;
            foreach (array_merge([$display], (array)$variants) as $variant) {
                if (!is_scalar($variant)) continue;
                $key = self::key((string)$variant);
                if ($key === '') continue;
                $index['raw'][$key][$display] = $display;
                $token = self::token($key);
                if ($token === null) continue;
                [$kind, $canonical] = $token;
                $index['tokens'][$kind][$canonical][$display] = $display;
                $index['kinds'][$kind] = true;
            }
        }
        return $index;
    }

    /** 同维度的结构化字段优先；旧编号、空值、其他维度才从标题取带单位的唯一选项。 */
    public static function matchingValues(string $raw, string $title, array $lookup): array
    {
        $key = self::key($raw);
        if ($key !== '') {
            if (isset($lookup['exact'][$key])) return [$lookup['exact'][$key]];
            if (isset($lookup['raw'][$key])) return self::uniqueChoice($lookup['raw'][$key]);
            $token = self::token($key);
            if ($token !== null) {
                $choices = $lookup['tokens'][$token[0]][$token[1]] ?? [];
                if ($choices !== []) return self::uniqueChoice($choices);
                // 表盘分组中的 32GB 是存储，不是尺寸。反之亦然。
                // 但同一维度的规格若不在字典内，不能用标题中的其他值冒充。
                $hasStorage = !empty($lookup['kinds']['capacity']) || !empty($lookup['kinds']['combo']);
                $hasSize = !empty($lookup['kinds']['size']);
                if ($token[0] === 'size' ? ($hasSize || !$hasStorage) : ($hasStorage || !$hasSize)) return [];
            } elseif (!ctype_digit($key)) {
                return [];
            }
        }

        if ($title === '' || empty($lookup['kinds'])) return [];
        // “适配 42 / 46mm 的表带”描述的是配件兼容性，不是这块表的尺寸。
        if (preg_match('/[表錶][带帶]|腕[带帶]|[适適][配用]|兼容|相容|compatible|watch\s*(?:band|strap|case)/iu', $title)) return [];
        // 仅剔除有通信语境的 2G/3G/4G/5G；4GB、5GB 等明确容量不能丢。
        $network = '[2-5]G(?:\s*[\/／]\s*[2-5]G)*';
        $title = (string)preg_replace([
            '/(?:全网通|全網通|双模|雙模|移动|移動|联通|聯通|电信|電信)\s*' . $network . '(?![A-Z0-9.])/iu',
            '/(?<![0-9.])' . $network . '\s*(?:网络|網絡|網路|全网通|全網通|通信|通訊|通讯|版)/iu',
        ], ' ', $title);
        // 两个规格共用最后的单位，不能只把最后一个识别为商品规格。
        if (preg_match('/\d(?:\.\d+)?\s*(?:[\/／、,，|&＆~～—–-]|或|至|到|和|及)\s*\d+(?:\.\d+)?\s*(?:[gt]b?|mm|毫米)(?![a-z0-9])/iu', $title)) return [];

        $seen = [];
        // 先完整消耗 RAM + ROM，避免把安卓“12+256G”拆成苹果容量“256GB”。
        $remaining = preg_replace_callback('/(?<![\d.])(\d+)\s*([gt]b?)?\s*[+＋]\s*(\d+)\s*([gt]b?)?(?![a-z0-9.])/iu', static function ($m) use (&$seen) {
            $explicitUnit = ($m[2] ?? '') !== '' || ($m[4] ?? '') !== '';
            $seen['combo'][$explicitUnit ? self::capacity($m[1], $m[2] ?? '') . '+' . self::capacity($m[3], $m[4] ?? '') : '?'] = true;
            return ' ';
        }, $title);
        if (!is_string($remaining)) return [];
        preg_match_all('/(?<![\d.])\d+(?:\.\d+)?\s*(?:[gt]b?|mm|毫米)(?![a-z0-9.])/iu', $remaining, $tokens);
        foreach ($tokens[0] as $part) {
            $token = self::token(self::key($part));
            if ($token !== null) $seen[$token[0]][$token[1]] = true;
        }
        // 若写着“12+256G / 512G”，它不是单一配置，更不能抽出某个单容量。
        if (isset($seen['combo'], $seen['capacity'])) return [];
        $match = [];
        foreach ($seen as $kind => $values) {
            if (empty($lookup['kinds'][$kind])) continue;
            if (count($values) !== 1) return [];
            $choices = $lookup['tokens'][$kind][array_key_first($values)] ?? [];
            if ($choices === []) continue;
            if ($match !== [] || count($choices) !== 1) return [];
            $match = array_values($choices);
        }
        return $match;
    }

    private static function key(string $text): string
    {
        return strtoupper((string)preg_replace('/\s+/u', '', str_replace('＋', '+', trim($text))));
    }

    private static function uniqueChoice(array $choices): array
    {
        return count($choices) === 1 ? array_values($choices) : [];
    }

    private static function capacity(string $number, string $unit): string
    {
        return $number . (strtoupper(substr($unit, 0, 1)) === 'T' ? 'TB' : 'GB');
    }

    private static function token(string $key): ?array
    {
        if (preg_match('/^(\d+)([GT]B?)$/D', $key, $m)) return ['capacity', self::capacity($m[1], $m[2])];
        if (preg_match('/^(\d+(?:\.\d+)?)(?:MM|毫米)$/uD', $key, $m)) {
            $size = strpos($m[1], '.') === false ? $m[1] : rtrim(rtrim($m[1], '0'), '.');
            return ['size', $size . 'MM'];
        }
        if (preg_match('/^(\d+)([GT]B?)?\+(\d+)([GT]B?)?$/D', $key, $m)) {
            return ['combo', self::capacity($m[1], $m[2] ?? '') . '+' . self::capacity($m[3], $m[4] ?? '')];
        }
        return null;
    }
}
