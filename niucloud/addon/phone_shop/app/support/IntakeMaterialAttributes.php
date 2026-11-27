<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

use core\exception\AdminException;

/** 资料待办复用商城字典；不创建规格、成色或商品参数，不涉及交易字段。 */
final class IntakeMaterialAttributes
{
    public static function arrayValue($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function templates(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['attr_id'] = (int)$row['attr_id'];
            $row['fields'] = self::arrayValue($row['attr_value_format'] ?? []);
            unset($row['attr_value_format']);
        }
        unset($row);
        return $rows;
    }

    /** 规格按当前商品分类读取；参数颜色只从已选模板读取，不混入其他品类。 */
    public static function options(array $catalog, array $attrIds): array
    {
        $options = ['memory_group' => [], 'device_color' => [], 'condition_grade' => []];
        foreach ($catalog['spec_groups'] ?? [] as $group) {
            $label = trim((string)($group['label'] ?? ''));
            $key = preg_match('/颜色|配色|色彩/u', $label) ? 'device_color'
                : (preg_match('/内存|容量|存储|规格/u', $label) ? 'memory_group' : '');
            if ($key === '') continue;
            foreach ($group['items'] ?? [] as $item) $options[$key][] = trim((string)($item['item_value'] ?? ''));
        }
        foreach ($catalog['grades'] ?? [] as $grade) {
            if ((int)($grade['status'] ?? 1) === 1) $options['condition_grade'][] = trim((string)($grade['grade_name'] ?? ''));
        }
        foreach ($catalog['templates'] ?? [] as $template) {
            if (!in_array((int)$template['attr_id'], array_map('intval', $attrIds), true)) continue;
            foreach ($template['fields'] ?? [] as $field) {
                $name = trim((string)($field['attr_value_name'] ?? ''));
                $key = preg_match('/^(设备)?(颜色|配色|色彩)$/u', $name) ? 'device_color'
                    : (preg_match('/^(内存|容量|存储容量|内存规格)$/u', $name) ? 'memory_group' : '');
                if ($key === '' || !in_array($field['type'] ?? '', ['radio', 'checkbox'], true)) continue;
                foreach ($field['child'] ?? [] as $item) $options[$key][] = trim((string)($item['name'] ?? ''));
            }
        }
        foreach ($options as &$values) $values = array_values(array_unique(array_filter($values, static fn($v) => $v !== '')));
        unset($values);
        return $options;
    }

    /** 仅复用分类中唯一匹配的属性值，256G/256GB 视为同一容量；不创建字典项。 */
    public static function matchKnownOptions(array $values, array $catalog): array
    {
        $normalize = static function (string $key, string $value): string {
            $value = mb_strtolower(preg_replace('/\s+/u', '', $value));
            return $key === 'memory_group' ? str_replace(['gb', 'tb'], ['g', 't'], $value) : $value;
        };
        foreach (self::options($catalog, []) as $key => $options) {
            $value = trim((string)($values[$key] ?? ''));
            if ($value === '' || in_array($value, $options, true)) continue;
            $matches = array_values(array_filter($options, static fn(string $option): bool => $normalize($key, $option) === $normalize($key, $value)));
            if (count($matches) === 1) $values[$key] = $matches[0];
        }
        return $values;
    }

    /** 新值必须来自字典；未匹配的原值允许原样保留，不做历史数据清洗。 */
    public static function validateChoices(array $data, array $goods, array $catalog, array $attrIds): void
    {
        $options = self::options($catalog, $attrIds);
        foreach (['memory_group' => '容量 / 规格', 'device_color' => '颜色', 'condition_grade' => '成色'] as $key => $label) {
            if (!array_key_exists($key, $data)) continue;
            if (!is_scalar($data[$key]) && $data[$key] !== null) throw new AdminException($label . '格式不正确');
            $value = trim((string)$data[$key]);
            if ($value === '' || $value === trim((string)($goods[$key] ?? '')) || in_array($value, $options[$key], true)) continue;
            throw new AdminException('【' . $label . '】请从当前商城已配置选项中选择；选项可能已变更，请刷新后重试');
        }
    }

    /** 按商城参数 ID 校验并重建名称，防止伪造名称、跨站模板或已删除选项写入。 */
    public static function normalizeParameters(array $data, array $goods, array $catalog): array
    {
        if (($data['attr_ids'] ?? null) === null && ($data['attr_format'] ?? null) === null) return [];
        if (!is_array($data['attr_ids'] ?? null) || !is_array($data['attr_format'] ?? null)) throw new AdminException('商品参数格式不正确，请重新加载');
        foreach ($data['attr_ids'] as $id) if (!is_scalar($id)) throw new AdminException('商品参数模板格式不正确');
        $ids = array_values(array_unique(array_map('intval', $data['attr_ids'])));
        $templates = array_column($catalog['templates'] ?? [], null, 'attr_id');
        $oldIds = array_map('intval', self::arrayValue($goods['attr_ids'] ?? []));
        $oldRows = self::arrayValue($goods['attr_format'] ?? []);
        foreach ($ids as $id) {
            if ($id <= 0 || (!isset($templates[$id]) && !in_array($id, $oldIds, true))) throw new AdminException('商品参数模板不存在或不属于本站，请重新选择');
        }
        $definitions = [];
        foreach ($ids as $id) {
            foreach ($templates[$id]['fields'] ?? [] as $field) $definitions[$id . ':' . $field['attr_value_id']] = $field;
        }
        $saved = []; $seen = [];
        foreach ($data['attr_format'] as $row) {
            if (!is_array($row)) throw new AdminException('商品参数内容格式不正确');
            if (!is_scalar($row['attr_id'] ?? null) || !is_scalar($row['attr_value_id'] ?? null)) throw new AdminException('商品参数标识格式不正确');
            $attrId = (int)($row['attr_id'] ?? 0);
            if ($attrId > 0 && !in_array($attrId, $ids, true)) throw new AdminException('商品参数不属于所选模板，请重新核对');
            $fieldId = (string)($row['attr_value_id'] ?? '');
            $key = $attrId . ':' . $fieldId;
            if (isset($seen[$key])) throw new AdminException('商品参数重复，请重新加载');
            $seen[$key] = true;
            $definition = $definitions[$key] ?? null;
            if ($definition === null) {
                // 非本次模板定义中的原有参数只允许原样保留，不能借此新增任意字段。
                $same = false;
                foreach ($oldRows as $old) if ($old == $row) { $same = true; break; }
                if (!$same) throw new AdminException('商品参数已移除或不属于所选模板，请重新核对');
                $saved[] = $row;
                continue;
            }
            $type = (string)($definition['type'] ?? 'text');
            $value = $row['attr_child_value_id'] ?? '';
            if ($type === 'text') {
                $text = $row['attr_child_value_name'] ?? '';
                if (!is_scalar($text) && $text !== null) throw new AdminException('文本参数格式不正确');
                $text = trim((string)$text);
                if (mb_strlen($text) > 30) throw new AdminException('【' . $definition['attr_value_name'] . '】最多填写 30 个字符');
                $value = '';
            } elseif (in_array($type, ['radio', 'checkbox'], true)) {
                $children = [];
                foreach ($definition['child'] ?? [] as $child) $children[(string)$child['id']] = (string)$child['name'];
                if ($type === 'checkbox') {
                    if (!is_array($value)) throw new AdminException('多选参数格式不正确');
                    foreach ($value as $id) if (!is_scalar($id)) throw new AdminException('多选参数格式不正确');
                    $value = array_values(array_unique(array_map('strval', $value)));
                    $text = [];
                    foreach ($value as $id) {
                        if (!array_key_exists($id, $children)) throw new AdminException('【' . $definition['attr_value_name'] . '】选项已变更，请重新选择');
                        $text[] = $children[$id];
                    }
                } else {
                    if (!is_scalar($value) && $value !== null) throw new AdminException('单选参数格式不正确');
                    $value = (string)$value;
                    if ($value !== '' && !array_key_exists($value, $children)) throw new AdminException('【' . $definition['attr_value_name'] . '】选项已变更，请重新选择');
                    $text = $value === '' ? '' : $children[$value];
                    $name = trim((string)$definition['attr_value_name']);
                    $baseKey = preg_match('/^(设备)?(颜色|配色|色彩)$/u', $name) ? 'device_color'
                        : (preg_match('/^(内存|容量|存储容量|内存规格)$/u', $name) ? 'memory_group' : '');
                    $baseValue = $baseKey === '' ? '' : ($data[$baseKey] ?? $goods[$baseKey] ?? '');
                    if (!is_scalar($baseValue) && $baseValue !== null) throw new AdminException('资料格式不正确，请重新核对');
                    $baseValue = trim((string)$baseValue);
                    if ($text !== '' && $baseValue !== '' && $text !== $baseValue) throw new AdminException('【' . $name . '】筛选属性与模板参数不一致，请统一后保存');
                }
            } else throw new AdminException('暂不支持该商品参数类型，请在商品编辑页核对');
            $saved[] = [
                'attr_id' => $attrId, 'attr_value_id' => $definition['attr_value_id'],
                'attr_value_name' => (string)$definition['attr_value_name'], 'type' => $type,
                'sort' => (int)($definition['sort'] ?? 0),
                'attr_child_value_id' => $value, 'attr_child_value_name' => $text,
            ];
        }
        return ['attr_ids' => $ids, 'attr_format' => $saved];
    }
}
