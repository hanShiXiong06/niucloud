<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

use addon\phone_shop\app\service\core\goods\CoreDeviceAttributeService;

/** 只消费质检已确认的展示值，不猜选项索引，不读取采集原文或用异常项推导成色。 */
final class InspectionGoodsAttributes
{
    private const KEYS = [
        'memory' => ['memory', 'storage', 'capacity', '内存', '容量', '存储', '存储容量', '内存容量'],
        'color' => ['color', 'device_color', '颜色', '机身颜色', '设备颜色', '配色'],
        'condition_grade' => ['condition_grade', 'condition', 'grade', '成色', '等级', '成色等级', '机器等级'],
        'battery_health' => ['battery', 'battery_health', 'battery_health_percent', '电池健康度', '电池效率', '电池健康', '电池容量百分比'],
        'warranty_expire_time' => ['warranty', 'warranty_info', 'warranty_expire_time', 'warranty_expire_date', '保修', '保修信息', '保修到期', '保修到期日', '保修到期时间'],
    ];

    public static function fromSnapshot($snapshot, array $hidden = []): array
    {
        $root = IntakeMaterialTask::payload($snapshot);
        $reports = [$root];
        foreach (['check_meta', 'report'] as $key) {
            $reports[] = IntakeMaterialTask::payload($root[$key] ?? []);
        }
        $raw = IntakeMaterialTask::payload($root['raw'] ?? []);
        $reports[] = IntakeMaterialTask::payload($raw['check_result_buyer'] ?? []);
        $values = [];
        foreach ($reports as $report) {
            foreach ($report as $name => $value) {
                self::collect($values, (string)$name, $value, $hidden);
            }
            foreach (['result_items', 'summary_fields'] as $key) {
                foreach ((array)($report[$key] ?? []) as $row) {
                    if (!is_array($row)) continue;
                    $name = trim((string)($row['field_name'] ?? $row['name'] ?? ''));
                    $fieldKey = trim((string)($row['field_key'] ?? ''));
                    if (in_array($name, $hidden, true) || in_array($fieldKey, $hidden, true)) continue;
                    $value = null;
                    foreach (['labels', 'label', 'value_name', 'value'] as $valueKey) {
                        $candidate = $row[$valueKey] ?? null;
                        if ($candidate === null || $candidate === '' || $candidate === []) continue;
                        if ($valueKey === 'value' && in_array($row['component'] ?? '', ['radio', 'select', 'checkbox'], true)) break;
                        $value = $candidate;
                        break;
                    }
                    if ($value === null && is_string($row['text'] ?? null) && $name !== '') {
                        $value = preg_replace('/^' . preg_quote($name, '/') . '\s*[：:]?\s*/u', '', $row['text']);
                    }
                    // 模板生成的 field_key 常是任意 ID；以明确的字段名为先。
                    self::collect($values, $name, $value, $hidden);
                    if (!self::target($name)) self::collect($values, $fieldKey, $value, $hidden);
                }
            }
        }
        $result = [];
        foreach ($values as $key => $candidates) {
            $candidates = array_values(array_unique($candidates, SORT_REGULAR));
            // 同一字段的结果矛盾时交给运营核对，不随意取第一条。
            if (count($candidates) === 1) $result[$key] = $candidates[0];
        }
        return $result;
    }

    private static function target(string $name): string
    {
        foreach (self::KEYS as $key => $names) if (in_array(trim($name), $names, true)) return $key;
        return '';
    }

    private static function collect(array &$values, string $name, $value, array $hidden): void
    {
        $key = self::target($name);
        if ($key === '' || in_array($name, $hidden, true)) return;
        if (is_array($value)) {
            // 多个选项不能拼接成一个确定属性；选项对象优先使用可读名称。
            $value = $value['label'] ?? $value['name'] ?? $value['text']
                ?? (count($value) === 1 ? reset($value) : null);
        }
        if (!is_scalar($value) || is_bool($value)) return;
        $value = trim((string)$value);
        if ($value === '' || in_array($value, ['未知', '未检测', '未填写', '--'], true)) return;
        $normalizer = new CoreDeviceAttributeService();
        if ($key === 'battery_health') {
            $value = $normalizer->normalizeBattery($value, false);
            if ($value < 0) return;
        } elseif ($key === 'warranty_expire_time') {
            $value = $normalizer->normalizeWarrantyExpire($value, false);
            if ($value <= 0) return;
        } elseif (in_array($key, ['color', 'condition_grade'], true) && is_numeric($value)) {
            return;
        }
        $values[$key][] = $value;
    }
}
