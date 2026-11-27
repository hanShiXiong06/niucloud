<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

/** 副标题同步电池与保修事实，不把未知值解释成零电池、过保或售后承诺。 */
final class GoodsWarrantySubtitle
{
    public static function label(int $expire): string
    {
        return $expire > 0 ? '保修至 ' . date('Y-m-d', $expire) : '';
    }

    public static function batteryLabel(int $battery): string
    {
        return $battery >= 0 && $battery <= 100 ? '电池 ' . $battery . '%' : '';
    }

    /**
     * 只更新独立的规范保修片段；人工句子、其他保修说明和质检摘要均保留。
     * 规范日期保留完整，避免长摘要把刚填写的保修信息挤出副标题。
     */
    public static function compose(string $subtitle, int $expire, int $limit = 255): string
    {
        return self::composeFacts($subtitle, [self::label($expire)], false, $limit);
    }

    /** 保留 compose 的第三参长度语义；组合事实使用单独接口避免误读旧调用。 */
    public static function composeWithBattery(string $subtitle, int $expire, int $battery, int $limit = 255): string
    {
        return self::composeFacts($subtitle, [self::batteryLabel($battery), self::label($expire)], true, $limit);
    }

    private static function composeFacts(string $subtitle, array $facts, bool $syncBattery, int $limit): string
    {
        $limit = max(0, $limit);
        if ($limit === 0) return '';
        $parts = preg_split('/\s*·\s*/u', trim($subtitle));
        $kept = [];
        foreach (is_array($parts) ? $parts : [] as $part) {
            $part = trim($part);
            if ($part === '' || preg_match('/^保修至 \d{4}-\d{2}-\d{2}$/D', $part)) continue;
            if ($syncBattery && preg_match('/^电池(?:健康度|效率|健康)?\s*[:：]?\s*(?:100|[0-9]{1,2})\s*[%％]$/uD', $part)) continue;
            $kept[] = $part;
        }
        $base = implode(' · ', $kept);
        $label = implode(' · ', array_values(array_filter($facts, static fn(string $fact): bool => $fact !== '')));
        if ($label === '') return mb_substr($base, 0, $limit);
        if ($base === '') return mb_substr($label, 0, $limit);

        $available = $limit - mb_strlen($label) - 3;
        if ($available <= 0) return mb_substr($label, 0, $limit);
        $base = mb_substr($base, 0, $available);
        $base = preg_replace('/[\s·]+$/u', '', $base) ?? $base;
        return $base === '' ? $label : $base . ' · ' . $label;
    }
}
