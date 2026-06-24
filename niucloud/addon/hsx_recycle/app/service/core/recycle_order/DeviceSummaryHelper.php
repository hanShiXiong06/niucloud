<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

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
}
