<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use think\facade\Db;

/**
 * 设备摘要(签收/代下单一次录入的质检摘要)统一处理助手。
 *
 * 统一参数契约：前端提交设备时携带 summary = { field_key: value }(由型号触发的质检模板摘要字段，最多 5 个)。
 * 后端所有接收处(AddDeviceHandler / SignHandler / RecycleOrderDeviceService)均用本助手落库，保证存储口径一致：
 *   - info.goods_category = 分类 id 路径
 *   - info[field_key]     = 各摘要值(平铺，供质检面板/列表反显)
 *   - info.sign_summary   = { field_key: value }(摘要原始映射，反显主数据源)
 *   - info.check_meta.battery / battery_num = 电池(若提供)
 *   - 设备保留列 capacity/color/system_version/warranty_info 从 summary 回填(无则回退顶层字段，兼容旧参数)
 */
class DeviceSummaryHelper
{
    /** 有独立设备列的"保留字段"，需同时写入设备列 */
    public const RESERVED_COLUMNS = ['capacity', 'color', 'system_version', 'warranty_info'];

    /**
     * 归一化 summary 为 { field_key: value }，剔除空值。
     * 兼容：关联数组 {field_key:value} / 列表 [{field_key,value}] / JSON 字符串。
     * @param mixed $summary
     * @return array
     */
    public static function normalizeSummary($summary): array
    {
        if (is_string($summary) && $summary !== '') {
            $decoded = json_decode($summary, true);
            $summary = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($summary) || empty($summary)) {
            return [];
        }

        $result = [];
        foreach ($summary as $key => $item) {
            if (is_array($item) && isset($item['field_key'])) {
                $fieldKey = (string)$item['field_key'];
                $value = $item['value'] ?? '';
            } else {
                $fieldKey = (string)$key;
                $value = $item;
            }
            if ($fieldKey === '') {
                continue;
            }
            if (is_array($value)) {
                $value = array_values(array_filter($value, static fn($v) => $v !== '' && $v !== null));
                if (empty($value)) {
                    continue;
                }
            } elseif ($value === '' || $value === null) {
                continue;
            }
            $result[$fieldKey] = $value;
        }
        return $result;
    }

    /**
     * 归一化分类 id 路径为字符串数组。
     * @param mixed $categoryPath
     * @param int $categoryId
     * @return array
     */
    public static function normalizeCategoryPath($categoryPath, int $categoryId): array
    {
        if (is_string($categoryPath) && $categoryPath !== '') {
            $decoded = json_decode($categoryPath, true);
            $categoryPath = is_array($decoded)
                ? $decoded
                : array_filter(array_map('trim', explode(',', $categoryPath)));
        }
        if ((!is_array($categoryPath) || empty($categoryPath)) && $categoryId > 0) {
            $categoryPath = [$categoryId];
        }
        return is_array($categoryPath) ? array_values(array_map('strval', $categoryPath)) : [];
    }

    /**
     * 合并设备 info：保留原 info，写入 goods_category + 平铺摘要 + sign_summary + 电池。
     * @param mixed $existingInfo 原 info(数组或 JSON 串)
     * @param array $categoryPath 分类 id 路径
     * @param array $summary 归一化后的 { field_key: value }
     * @param array $device 原始设备参数(取电池等)
     * @return array
     */
    public static function buildInfo($existingInfo, array $categoryPath, array $summary, array $device = []): array
    {
        if (is_string($existingInfo) && $existingInfo !== '') {
            $decoded = json_decode($existingInfo, true);
            $existingInfo = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($existingInfo)) {
            $existingInfo = [];
        }

        $existingInfo['goods_category'] = $categoryPath;

        // 平铺摘要 + 原始映射(反显主数据源)
        if (!empty($summary)) {
            foreach ($summary as $fieldKey => $val) {
                $existingInfo[$fieldKey] = $val;
            }
            $existingInfo['sign_summary'] = $summary;
        }

        // 电池写入 check_meta(兼容原逻辑)
        if (!isset($existingInfo['check_meta']) || !is_array($existingInfo['check_meta'])) {
            $existingInfo['check_meta'] = is_array($existingInfo['check_meta'] ?? null) ? $existingInfo['check_meta'] : [];
        }
        if (!empty($device['battery_health'])) {
            $existingInfo['check_meta']['battery'] = $device['battery_health'];
        }
        if (!empty($device['battery_cycle'])) {
            $existingInfo['check_meta']['battery_num'] = $device['battery_cycle'];
        }

        return $existingInfo;
    }

    /**
     * 取保留字段的设备列值：优先 summary，其次顶层 device(兼容旧参数)。
     * @param array $summary
     * @param array $device
     * @return array { capacity, color, system_version, warranty_info }
     */
    public static function reservedColumns(array $summary, array $device = []): array
    {
        $cols = [];
        foreach (self::RESERVED_COLUMNS as $key) {
            $val = $summary[$key] ?? ($device[$key] ?? '');
            if (is_array($val)) {
                $val = implode(',', $val);
            }
            $cols[$key] = (string)$val;
        }
        return $cols;
    }

    /**
     * 批量构建质检模板字段「选项值 → 选项文案」映射。
     *
     * 背景：select/radio/checkbox 类字段提交的是 option_value(数字 id)，
     * 写入设备保留列(capacity/color 等)后导出会显示成数字，需按模板回译成 label。
     * input/number 等自由输入字段不存在选项，自然不会命中映射，原样保留。
     *
     * @param array $templateIds 设备的 check_template_id 集合
     * @param array $fieldKeys   需要回译的字段 key,如 ['color','capacity','system_version','warranty_info']
     * @param int   $siteId      站点 id(可选,>0 时附加过滤)
     * @return array [templateId][fieldKey][optionValue] => optionLabel
     */
    public static function buildOptionLabelMap(array $templateIds, array $fieldKeys, int $siteId = 0): array
    {
        $templateIds = array_values(array_unique(array_filter(array_map('intval', $templateIds), static fn($v) => $v > 0)));
        $fieldKeys = array_values(array_filter(array_map('strval', $fieldKeys), static fn($v) => $v !== ''));
        if (empty($templateIds) || empty($fieldKeys)) {
            return [];
        }

        // 1) 取目标模板下、指定 key 的字段
        $fieldQuery = Db::name('recycle_check_field')
            ->whereIn('template_id', $templateIds)
            ->whereIn('field_key', $fieldKeys);
        if ($siteId > 0) {
            $fieldQuery->where('site_id', '=', $siteId);
        }
        $fields = $fieldQuery->field('id,template_id,field_key')->select()->toArray();
        if (empty($fields)) {
            return [];
        }

        // field_id => [template_id, field_key]
        $fieldMeta = [];
        foreach ($fields as $f) {
            $fieldMeta[(int)$f['id']] = ['template_id' => (int)$f['template_id'], 'field_key' => (string)$f['field_key']];
        }

        // 2) 取这些字段的全部选项
        $options = Db::name('recycle_check_option')
            ->whereIn('field_id', array_keys($fieldMeta))
            ->field('field_id,option_value,option_label')
            ->select()->toArray();

        // 3) 组装 [templateId][fieldKey][optionValue] => label
        $map = [];
        foreach ($options as $opt) {
            $meta = $fieldMeta[(int)$opt['field_id']] ?? null;
            if ($meta === null) {
                continue;
            }
            $value = (string)($opt['option_value'] ?? '');
            $label = (string)($opt['option_label'] ?? '');
            if ($value === '' || $label === '') {
                continue;
            }
            $map[$meta['template_id']][$meta['field_key']][$value] = $label;
        }
        return $map;
    }

    /**
     * 用映射把单个保留列的存储值回译成展示文案。
     * 多值(逗号分隔)逐个回译;命中映射则换 label,否则原样保留。
     *
     * @param string $rawValue   存储值
     * @param array  $valueLabel option_value => option_label
     * @return string
     */
    public static function resolveReservedValue(string $rawValue, array $valueLabel): string
    {
        if ($rawValue === '' || empty($valueLabel)) {
            return $rawValue;
        }
        // 去掉防科学计数法的制表符前缀再判断,回填后保持原样
        $parts = explode(',', $rawValue);
        $resolved = array_map(static function ($part) use ($valueLabel) {
            $key = trim($part);
            return $valueLabel[$key] ?? $part;
        }, $parts);
        return implode(',', $resolved);
    }
}
