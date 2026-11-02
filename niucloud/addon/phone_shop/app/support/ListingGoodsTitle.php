<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

/** 自动上架标题；只消费明确的设备信息，不推测容量、颜色或屏幕尺寸。 */
final class ListingGoodsTitle
{
    public const DEFAULT_TEMPLATE = '{brand} {model} {size} {memory} {color}';

    public static function isDefaultTemplate(string $template): bool
    {
        return in_array(self::clean($template), [self::DEFAULT_TEMPLATE, '{brand} {model} {memory} {color}'], true);
    }

    public static function isModelTitle(string $title, string $model): bool
    {
        return self::key($title) === self::key($model);
    }

    public static function compose(string $brand, string $model, string $memory, string $color, $size = ''): string
    {
        $model = self::clean($model);
        $parts = [];
        if (($brand = self::clean($brand)) !== '' && !self::contains($model, $brand)) $parts[] = $brand;
        if ($model !== '') $parts[] = $model;
        $screenSize = self::screenSize($model, $size);
        if ($screenSize !== '' && !preg_match('/\d+(?:\.\d+)?\s*(?:英寸|寸|inches|inch|in\b|["″])/iu', $model)) $parts[] = $screenSize;
        foreach ([$memory, $color] as $part) {
            $part = self::clean($part);
            if ($part !== '' && !self::contains(implode(' ', $parts), $part)) $parts[] = $part;
        }
        return self::clean(implode(' ', $parts));
    }

    /** iPad 尺寸来自已有明确字段；“iPad 11”不能被擅自解释成 11 英寸。 */
    public static function screenSize(string $model, $size): string
    {
        if (!preg_match('/ipad/iu', $model) || !is_scalar($size)) return '';
        $raw = trim((string)$size);
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(?:英寸|寸|inches|inch|in|["″])?$/iu', $raw, $matches)) return '';
        $number = (float)$matches[1];
        if ($number < 5 || $number > 20) return '';
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.') . '英寸';
    }

    private static function contains(string $title, string $part): bool
    {
        return self::key($part) !== '' && str_contains(self::key($title), self::key($part));
    }

    private static function key(string $text): string
    {
        $text = strtolower((string)preg_replace('/\s+/u', '', $text));
        $text = str_replace(['gb', 'tb', '＋'], ['g', 't', '+'], $text);
        return (string)preg_replace('/(\d+)g?\+(\d+)g?/', '$1g+$2g', $text);
    }

    private static function clean(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', trim($text)));
    }
}
