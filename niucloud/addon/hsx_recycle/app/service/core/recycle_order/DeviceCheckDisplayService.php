<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use think\facade\Db;

/** Read-only template presentation shared by customer, admin and internal services. */
class DeviceCheckDisplayService
{
    private const BASE_FIELDS = [
        'capacity' => '存储容量', 'color' => '机身颜色',
        'system_version' => '系统版本', 'warranty_info' => '保修信息',
    ];

    public function enrichDevices(array $devices, int $siteId): array
    {
        $templateIds = [];
        foreach ($devices as $device) {
            $templateId = $this->templateId($device);
            if ($templateId > 0) $templateIds[] = $templateId;
        }
        $fields = $this->loadFields($templateIds, $siteId);
        // Reuse the existing value/id alias precedence used by printing and export.
        $optionMaps = DeviceSummaryHelper::buildOptionLabelMap(array_keys($fields), [], $siteId);
        foreach ($devices as &$device) {
            $info = DeviceReadingArchive::decode($device['info'] ?? []);
            $meta = DeviceReadingArchive::decode($info['check_meta'] ?? []);
            $templateId = $this->templateId($device);
            $templateFields = $fields[$templateId] ?? [];
            $maps = $optionMaps[$templateId] ?? [];
            $items = DeviceReadingArchive::decode($meta['result_items'] ?? []);
            $itemsByKey = [];
            foreach ($items as &$item) {
                $item = DeviceReadingArchive::decode($item);
                $key = (string)($item['field_key'] ?? '');
                if ($key === '') continue;
                $field = $templateFields[$key] ?? [];
                $map = $maps[$key] ?? [];
                $values = $this->values($item['values'] ?? $item['value'] ?? [], !empty($map));
                $savedLabels = $this->values($item['labels'] ?? [], false);
                $labels = [];
                foreach ($values as $index => $value) {
                    $label = DeviceSummaryHelper::resolveDisplayValue($value, $map);
                    // A stored label is only usable when paired with this exact stored value.
                    if ($label === DeviceSummaryHelper::resolveDisplayValue($value) && count($values) === count($savedLabels)) {
                        $label = DeviceSummaryHelper::resolveDisplayValue($savedLabels[$index]);
                    }
                    if ($label !== '') $labels[] = $label;
                }
                $item['field_name'] = (string)($field['field_name'] ?? $item['field_name'] ?? self::BASE_FIELDS[$key] ?? $key);
                if ($labels) {
                    $item['labels'] = $labels;
                    if (empty($item['text'])) $item['text'] = $item['field_name'] . '：' . implode('、', $labels);
                }
                foreach (($item['option_items'] ?? []) as $index => $option) {
                    $option = DeviceReadingArchive::decode($option);
                    $value = $option['value'] ?? $values[$index] ?? null;
                    if ($value === null) continue;
                    $label = DeviceSummaryHelper::resolveDisplayValue($value, $map);
                    if ($label !== DeviceSummaryHelper::resolveDisplayValue($value) || empty($option['label'])) {
                        $option['label'] = $label;
                    }
                    $item['option_items'][$index] = $option;
                }
                $itemsByKey[$key] = $item;
            }
            unset($item);

            $signSummary = DeviceSummaryHelper::normalizeSummary($info['sign_summary'] ?? []);
            $keys = array_keys(self::BASE_FIELDS);
            foreach ($templateFields as $key => $field) {
                $config = DeviceReadingArchive::decode($field['extra_config'] ?? []);
                if ((int)($config['summary_visible'] ?? $config['show_in_summary'] ?? 0) === 1 || array_key_exists($key, $signSummary)) $keys[] = $key;
            }
            $summary = [];
            foreach (array_unique($keys) as $key) {
                $field = $templateFields[$key] ?? [];
                $item = $itemsByKey[$key] ?? [];
                $raw = null;
                foreach ([$info[$key] ?? null, $signSummary[$key] ?? null, $device[$key] ?? null, $item['values'] ?? $item['value'] ?? null] as $candidate) {
                    if (!$this->emptyValue($candidate)) { $raw = $candidate; break; }
                }
                if ($this->emptyValue($raw)) continue;
                $map = $maps[$key] ?? [];
                $label = DeviceSummaryHelper::resolveDisplayValue($raw, $map);
                $values = $this->values($raw, !empty($map));
                $resolved = !empty($field) && empty($map) && !in_array($field['component'] ?? '', ['radio', 'select', 'checkbox'], true);
                if (!$resolved) {
                    $resolved = true;
                    foreach ($values as $value) {
                        $plain = DeviceSummaryHelper::resolveDisplayValue($value);
                        if (is_numeric($plain) && !isset($map[$plain])) $resolved = false;
                    }
                }
                if (!$resolved && $this->values($item['values'] ?? $item['value'] ?? [], !empty($map)) === $values && !empty($item['labels'])) {
                    $label = implode('、', $item['labels']);
                    $resolved = !is_numeric($label);
                }
                $summary[] = [
                    'field_key' => $key,
                    'field_name' => (string)($field['field_name'] ?? $item['field_name'] ?? self::BASE_FIELDS[$key] ?? $key),
                    'component' => (string)($field['component'] ?? 'input'),
                    'value' => $raw, 'label' => $label,
                    'unit' => (string)($field['unit'] ?? ''), 'resolved' => $resolved,
                ];
            }
            $device['check_summary'] = $summary;
            if ($items) $meta['result_items'] = $items;
            if ($meta) {
                if (empty($meta['template_id']) && $templateId > 0) $meta['template_id'] = $templateId;
                $info['check_meta'] = $meta;
            }
            if ($info || array_key_exists('info', $device)) $device['info'] = $info;
        }
        unset($device);
        return $devices;
    }

    private function templateId(array $device): int
    {
        $id = (int)($device['check_template_id'] ?? 0);
        if ($id > 0) return $id;
        $info = DeviceReadingArchive::decode($device['info'] ?? []);
        $meta = DeviceReadingArchive::decode($info['check_meta'] ?? []);
        return (int)($meta['template_id'] ?? 0);
    }

    /** Supports both table-backed and imported schema_json templates, never a global fallback. */
    private function loadFields(array $ids, int $siteId): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) return [];
        $templates = Db::name('recycle_check_template')->where('site_id', $siteId)->whereIn('id', $ids)->field('id,schema_json')->select()->toArray();
        if (!$templates) return [];
        $fields = [];
        foreach ($templates as $template) $fields[(int)$template['id']] = [];
        $rows = Db::name('recycle_check_field')->where('site_id', $siteId)->whereIn('template_id', array_keys($fields))->select()->toArray();
        foreach ($rows as $field) $fields[(int)$field['template_id']][(string)$field['field_key']] = $field;
        foreach ($templates as $template) {
            $schema = DeviceReadingArchive::decode($template['schema_json'] ?? []);
            foreach (($schema['groups'] ?? []) as $group) {
                if (!is_array($group)) continue;
                foreach (($group['fields'] ?? []) as $field) {
                    if (!is_array($field) || empty($field['field_key'])) continue;
                    $fields[(int)$template['id']][(string)$field['field_key']] = $field;
                }
            }
        }
        return $fields;
    }

    private function emptyValue($value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function values($value, bool $split): array
    {
        if ($this->emptyValue($value)) return [];
        if (is_object($value)) $value = DeviceReadingArchive::decode($value);
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) $value = $decoded;
            elseif ($split && preg_match('/[,，]/u', $value)) $value = preg_split('/[,，]/u', $value);
        }
        if (!is_array($value)) return [$value];
        return array_keys($value) === array_keys(array_values($value)) ? $value : [$value];
    }
}
