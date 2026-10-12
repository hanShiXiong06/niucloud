<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device_export;

use addon\hsx_recycle\app\service\core\device\DeviceCatalogPolicy;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceReadingArchive;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
use think\facade\Db;

/** 导出只读解析：显式传入业务站点，队列中不依赖登录态，也不初始化/修改模板。 */
final class DeviceExportValueResolver
{
    private const FIELDS = ['color', 'package_type', 'capacity', 'system_version', 'warranty_info'];

    public function resolve(array $devices, int $siteId): array
    {
        $contexts = $templateIds = $nodeIds = [];
        foreach ($devices as $index => $device) {
            $info = DeviceReadingArchive::decode($device['info'] ?? []);
            $meta = DeviceReadingArchive::decode($info['check_meta'] ?? []);
            $templateId = (int)($device['check_template_id'] ?? 0);
            if ($templateId <= 0) $templateId = (int)($meta['template_id'] ?? 0);
            $nodeId = (int)($device['category_id'] ?? 0);
            $contexts[$index] = compact('info', 'meta', 'templateId', 'nodeId');
            if ($templateId > 0) $templateIds[] = $templateId;
            elseif ($nodeId > 0) $nodeIds[] = $nodeId;
        }
        $boundIds = $this->boundTemplateIds($nodeIds, $siteId);
        $maps = DeviceSummaryHelper::buildOptionLabelMap(array_merge($templateIds, array_values($boundIds)), self::FIELDS, $siteId);

        foreach ($devices as $index => &$device) {
            ['info' => $info, 'meta' => $meta, 'templateId' => $templateId, 'nodeId' => $nodeId] = $contexts[$index];
            $summary = DeviceSummaryHelper::normalizeSummary($info['sign_summary'] ?? []);
            $items = [];
            $snapshotMaps = [];
            foreach (DeviceReadingArchive::decode($meta['result_items'] ?? []) as $item) {
                $item = DeviceReadingArchive::decode($item);
                $key = (string)($item['field_key'] ?? '');
                if (!in_array($key, self::FIELDS, true)) continue;
                $items[$key] = $item;
                $pairs = [];
                foreach (DeviceReadingArchive::decode($item['option_items'] ?? []) as $option) {
                    $option = DeviceReadingArchive::decode($option);
                    $pairs[] = [$option['value'] ?? null, $option['label'] ?? null];
                }
                $values = $this->values($item['values'] ?? $item['value'] ?? []);
                $labels = $this->values($item['labels'] ?? $item['label'] ?? []);
                if (count($values) === count($labels)) {
                    foreach ($values as $i => $value) $pairs[] = [$value, $labels[$i]];
                }
                foreach ($pairs as [$value, $label]) {
                    if (!is_scalar($value) || !is_scalar($label)) continue;
                    $value = trim((string)$value);
                    $label = trim((string)$label);
                    if ($value !== '' && $label !== '') $snapshotMaps[$key][$value] = $label;
                }
            }
            foreach (self::FIELDS as $key) {
                // 保存的模板优先；同设备快照其次；仅未记录模板时才参考当前型号绑定。
                $map = ($maps[$templateId][$key] ?? []) + ($snapshotMaps[$key] ?? []);
                if ($templateId <= 0) $map += $maps[$boundIds[$nodeId] ?? 0][$key] ?? [];
                $item = $items[$key] ?? [];
                $raw = '';
                foreach ([$device[$key] ?? null, $info[$key] ?? null, $summary[$key] ?? null,
                    $item['values'] ?? $item['value'] ?? null, $item['labels'] ?? $item['label'] ?? null] as $candidate) {
                    if ($candidate !== null && $candidate !== '' && $candidate !== []) { $raw = $candidate; break; }
                }
                $device[$key] = DeviceSummaryHelper::resolveDisplayValue($raw, $map, ',');
            }
        }
        unset($device);
        return $devices;
    }

    /** 与打印相同：只用明确型号及允许继承的祖先绑定，不套全局模板或其他打印场景。 */
    private function boundTemplateIds(array $nodeIds, int $siteId): array
    {
        $nodeIds = array_values(array_unique(array_filter($nodeIds)));
        if (!$nodeIds) return [];
        $sites = DeviceCatalogPolicy::referenceSiteIds($siteId);
        $nodes = $visited = [];
        $pending = $nodeIds;
        // 按层批量读取，避免每台导出设备重复查祖先、模板和选项。
        for ($depth = 0; $depth < 10 && $pending; $depth++) {
            foreach ($pending as $id) $visited[$id] = true;
            $rows = Db::name('recycle_device_model_dict')->whereIn('site_id', $sites)
                ->whereIn('id', $pending)->field('id,site_id,pid')->select()->toArray();
            $pending = [];
            foreach ($rows as $row) {
                $nodes[(int)$row['id']] = $row;
                $parent = (int)$row['pid'];
                if ($parent > 0 && !isset($visited[$parent])) $pending[$parent] = $parent;
            }
        }
        if (!$nodes) return [];
        $bindings = Db::name('recycle_template_binding')->whereIn('site_id', $sites)
            ->where('target_type', 'model_dict')->whereIn('target_id', array_keys($nodes))
            ->where('scene_key', 'manual_device_label')->where('status', 1)
            ->field('site_id,target_id,check_template_id,inherit_enabled')->select()->toArray();
        $byOwner = [];
        foreach ($bindings as $binding) $byOwner[(int)$binding['site_id']][(int)$binding['target_id']] = $binding;
        $result = [];
        foreach ($nodeIds as $nodeId) {
            if (!isset($nodes[$nodeId])) continue;
            $owner = (int)$nodes[$nodeId]['site_id'];
            $current = $nodeId;
            for ($depth = 0; $depth < 10 && isset($nodes[$current]); $depth++) {
                $binding = $byOwner[$owner][$current] ?? [];
                if ((int)($binding['check_template_id'] ?? 0) > 0 && ($depth === 0 || !empty($binding['inherit_enabled']))) {
                    $result[$nodeId] = (int)$binding['check_template_id'];
                    break;
                }
                $current = (int)$nodes[$current]['pid'];
            }
        }
        if (!$result) return [];
        $validIds = Db::name('recycle_check_template')->whereIn('site_id', $sites)
            ->whereIn('id', array_values(array_unique($result)))->where('status', 1)->column('id');
        return array_filter($result, static fn(int $id): bool => in_array($id, $validIds));
    }

    private function values($value): array
    {
        if ($value === null || $value === '' || $value === []) return [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) $value = $decoded;
        }
        return is_array($value) ? array_values($value) : [$value];
    }
}
