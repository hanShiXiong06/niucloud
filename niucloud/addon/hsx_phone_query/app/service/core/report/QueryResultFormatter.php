<?php

namespace addon\hsx_phone_query\app\service\core\report;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryResultDict;

/**
 * 查询结果中文展示格式化服务。
 */
class QueryResultFormatter
{
    public function format($info, array $context = []): array
    {
        $data = $this->normalizeInfo($info);
        $fields = $this->buildFields($data);
        $title = $this->pickFirst($data, HsxPhoneQueryResultDict::titleKeys()) ?: (string)($context['type_name'] ?? '查询报告');
        $capacity = $this->pickFirst($data, HsxPhoneQueryResultDict::capacityKeys());
        $color = $this->pickFirst($data, HsxPhoneQueryResultDict::colorKeys());
        $image = $this->pickFirst($data, HsxPhoneQueryResultDict::imageKeys());

        return [
            'title' => $title,
            'subtitle' => implode(' / ', array_values(array_filter([$capacity, $color]))) ?: (string)($context['type_name'] ?? ''),
            'image' => $image,
            'summary' => $this->buildSummary($data, $context),
            'status_tags' => $this->buildStatusTags($data),
            'fields' => $fields,
            'summary_text' => $this->buildSummaryText($data, $context),
        ];
    }

    public function normalizeInfo($info): array
    {
        if (is_array($info)) {
            return $info;
        }
        if (!is_string($info) || trim($info) === '') {
            return [];
        }

        $decoded = json_decode($info, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function stringify($value, string $path = ''): string
    {
        if (!$this->hasValue($value)) {
            return '--';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $key => $item) {
                $childPath = $this->joinPath($path, (string)$key);
                if ($this->isAssocArray($item)) {
                    $nested = $this->stringify($item, $childPath);
                    if ($nested !== '--') {
                        $parts[] = $this->label($childPath) . '：' . $nested;
                    }
                    continue;
                }

                if ($this->hasValue($item)) {
                    $parts[] = $this->label($childPath) . '：' . HsxPhoneQueryResultDict::formatValue($childPath, $item);
                }
            }
            return implode('，', $parts) ?: '--';
        }

        return HsxPhoneQueryResultDict::formatValue($path, $value) ?: '--';
    }

    private function buildFields(array $data): array
    {
        $fields = [];
        foreach ($this->flatten($data) as $item) {
            $path = $item['path'];
            if (in_array($path, HsxPhoneQueryResultDict::hiddenDetailPaths(), true)) {
                continue;
            }
            if (!$this->hasValue($item['value'])) {
                continue;
            }

            $fields[] = [
                'key' => $path,
                'label' => $this->label($path),
                'value' => $item['value'],
                'display_value' => HsxPhoneQueryResultDict::formatValue($path, $item['value']),
                'type' => $this->isImageUrl($item['value']) ? 'image' : 'text',
            ];
        }

        return $fields;
    }

    private function buildSummary(array $data, array $context): array
    {
        $summary = [];
        if (!empty($context['type_name'])) {
            $summary[] = ['label' => '查询项目', 'value' => (string)$context['type_name']];
        }

        foreach (HsxPhoneQueryResultDict::summaryPaths() as $path) {
            $value = $this->getByPath($data, $path);
            if ($this->hasValue($value)) {
                $summary[] = [
                    'label' => $this->label($path),
                    'value' => is_array($value) ? $this->stringify($value, $path) : HsxPhoneQueryResultDict::formatValue($path, $value),
                ];
            }
        }

        if (!empty($context['create_time'])) {
            $summary[] = ['label' => '查询时间', 'value' => (string)$context['create_time']];
        }

        return array_values($summary);
    }

    private function buildStatusTags(array $data): array
    {
        $candidates = [
            ['path' => 'coverage.status', 'label' => '保修状态'],
            ['path' => 'activationlock.locked', 'label' => '查找设备锁'],
            ['path' => 'activationlock.lost', 'label' => '丢失模式'],
            ['path' => 'simlock', 'label' => '网络锁'],
            ['path' => 'blacklist', 'label' => '黑名单'],
            ['path' => 'mdm', 'label' => '监管锁'],
        ];

        $tags = [];
        foreach ($candidates as $candidate) {
            $value = $this->getByPath($data, $candidate['path']);
            if (!$this->hasValue($value)) {
                continue;
            }
            $displayValue = is_array($value) ? $this->stringify($value, $candidate['path']) : HsxPhoneQueryResultDict::formatValue($candidate['path'], $value);
            $tags[] = [
                'label' => $candidate['label'],
                'value' => $displayValue,
                'tone' => $this->resolveTone($candidate['path'], $displayValue),
            ];
        }

        return $tags;
    }

    private function buildSummaryText(array $data, array $context): string
    {
        $parts = [];
        foreach ($this->buildSummary($data, $context) as $item) {
            if (($item['label'] ?? '') === '查询项目') {
                continue;
            }
            $parts[] = $item['label'] . '：' . $item['value'];
            if (count($parts) >= 4) {
                break;
            }
        }

        if (empty($parts)) {
            foreach ($this->buildFields($data) as $field) {
                $parts[] = $field['label'] . '：' . $field['display_value'];
                if (count($parts) >= 4) {
                    break;
                }
            }
        }

        return mb_substr(implode('  ', $parts), 0, 120, 'UTF-8');
    }

    private function flatten(array $data, string $prefix = ''): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $path = $this->joinPath($prefix, (string)$key);
            if (is_array($value) && $this->isAssocArray($value)) {
                $rows = array_merge($rows, $this->flatten($value, $path));
            } else {
                $rows[] = ['path' => $path, 'value' => $value];
            }
        }

        return $rows;
    }

    private function label(string $path): string
    {
        $labels = HsxPhoneQueryResultDict::fieldLabels();
        if (isset($labels[$path])) {
            return $labels[$path];
        }

        $last = str_contains($path, '.') ? substr($path, strrpos($path, '.') + 1) : $path;
        return $labels[$last] ?? $last;
    }

    private function pickFirst(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->getByPath($data, $key);
            if ($this->hasValue($value)) {
                return is_array($value) ? $this->stringify($value, $key) : (string)$value;
            }
        }

        return '';
    }

    private function getByPath(array $data, string $path)
    {
        $current = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function joinPath(string $prefix, string $key): string
    {
        return $prefix === '' ? $key : $prefix . '.' . $key;
    }

    private function hasValue($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }

    private function isAssocArray(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function isImageUrl($value): bool
    {
        return is_string($value) && preg_match('/^(https?:\/\/|\/static\/|\/upload\/|\/addon\/)/i', $value);
    }

    private function resolveTone(string $path, string $value): string
    {
        $text = mb_strtolower($value, 'UTF-8');
        if (str_contains($text, '未开启') || str_contains($text, '正常') || str_contains($text, '保修中')) {
            return 'success';
        }
        if (str_contains($text, '已开启') || str_contains($text, '丢失') || str_contains($text, '黑名单')) {
            return 'danger';
        }
        if (str_contains($text, '已过保') || str_contains($text, '过期')) {
            return 'warning';
        }

        return 'neutral';
    }
}
