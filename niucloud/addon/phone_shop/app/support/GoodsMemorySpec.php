<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

/** 上架和筛选共用规格值的规范形式；不改变展示名或后台排序。 */
final class GoodsMemorySpec
{
    public const CONFIG_KEY = 'PHONE_SHOP_LISTING_MAPPING';

    public static function normalize(string $raw, array $aliases): string
    {
        $m = trim($raw);
        if ($m === '') return '';
        $key = mb_strtolower(str_replace(' ', '', $m));
        foreach ($aliases as $ak => $av) {
            if (mb_strtolower(str_replace(' ', '', (string)$ak)) === $key) return (string)$av;
        }
        $norm = str_replace(' ', '', $m);
        $norm = preg_replace_callback('/(\d+)\s*(tb|gb|g|t)\b/iu', function ($x) {
            $num = $x[1];
            $unit = strtoupper($x[2]);
            if ($unit === 'G') $unit = 'GB';
            if ($unit === 'T') $unit = 'TB';
            return $num . $unit;
        }, $norm);
        if (preg_match('/^\d+\+\d+$/', $norm)) {
            $norm = preg_replace('/(\d+)/', '$1GB', $norm);
        } elseif (preg_match('/^\d+$/', $norm)) {
            $norm .= 'GB';
        }
        return $norm;
    }
}
