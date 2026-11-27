<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\support;

use core\exception\CommonException;
use think\facade\Db;

/** Merchant address filters use Niucloud's area dictionary, never source ERP codes. */
final class MerchantRegion
{
    public static function apply($query, array $params, string $prefix = ''): void
    {
        if (!in_array($prefix, ['', 'm.'], true)) throw new \LogicException('Unsupported region query alias');
        $parent = 0;
        $ended = false;
        foreach (['province_id', 'city_id', 'district_id'] as $index => $field) {
            $raw = $params[$field] ?? '';
            if ($raw === '' || $raw === 0 || $raw === '0') {
                $ended = true;
                continue;
            }
            if ($ended || (!is_int($raw) && !is_string($raw)) || preg_match('/^[1-9][0-9]{0,9}$/D', (string)$raw) !== 1) {
                throw new CommonException('请从省份开始逐级选择有效的门店地区');
            }
            $id = (int)$raw;
            $area = Db::name('sys_area')->where('id', $id)->where('status', 1)->field('id,pid,level')->find();
            if (!$area || (int)$area['level'] !== $index + 1 || (int)$area['pid'] !== $parent) {
                throw new CommonException('门店地区不存在或上下级不对应，请重新选择');
            }
            $query->where($prefix . $field, $id);
            $parent = $id;
        }
    }

    /** One dictionary read per page instead of three reads for every merchant. */
    public static function present(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) foreach (['province_id', 'city_id', 'district_id'] as $field) {
            $id = (int)($row[$field] ?? 0);
            if ($id > 0) $ids[$id] = $id;
        }
        $names = $ids === [] ? [] : Db::name('sys_area')->whereIn('id', array_values($ids))->column('name', 'id');
        foreach ($rows as &$row) {
            $parts = [];
            foreach (['province', 'city', 'district'] as $field) {
                $id = (int)($row[$field . '_id'] ?? 0);
                $row[$field . '_id'] = $id;
                $name = (string)($names[$id] ?? '');
                $row[$field . '_name'] = $name;
                if ($name !== '') $parts[] = $name;
            }
            $row['region_text'] = implode(' / ', $parts);
        }
        unset($row);
        return $rows;
    }
}
