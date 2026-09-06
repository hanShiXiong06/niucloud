<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/** 只读整理既有质检快照；不把自动生成的质检摘要当成人工备注，也不修改历史数据。 */
final class ErpInspectionPresentation
{
    public static function fromAsset(array $asset): array
    {
        $snapshot = self::decode($asset['qc_report'] ?? []);
        $report = self::decode($snapshot['report'] ?? []);
        $raw = self::decode($snapshot['raw'] ?? []);
        if ($report === []) {
            foreach (['report', 'check_result_buyer', 'check_result_seller', 'check_result'] as $key) {
                $candidate = self::decode($raw[$key] ?? []);
                if (!empty($candidate['result_items'])) { $report = $candidate; break; }
            }
        }
        $items = [];
        $counts = ['normal' => 0, 'general' => 0, 'abnormal' => 0, 'unknown' => 0];
        $generated = [];
        foreach ((array)($report['result_items'] ?? []) as $index => $row) {
            if (!is_array($row)) continue;
            $name = self::text($row['field_name'] ?? '');
            $text = self::text($row['text'] ?? '');
            $labels = array_values(array_filter(array_map([self::class, 'text'], (array)($row['labels'] ?? [])), static fn($v) => $v !== ''));
            $value = implode('、', $labels);
            if ($value === '') $value = self::text($row['value'] ?? '');
            if ($text !== '' && $name !== '' && str_starts_with($text, $name)) {
                $suffix = trim((string)preg_replace('/^[\s:：]+/u', '', mb_substr($text, mb_strlen($name))));
                if ($suffix !== '') $value = $suffix;
            }
            if ($name === '' && $text !== '') { $name = '质检项目'; $value = $text; }
            if ($name === '' || $value === '') continue;
            $severity = (string)($row['severity'] ?? 'unknown');
            if (!array_key_exists($severity, $counts)) $severity = 'unknown';
            $counts[$severity]++;
            $items[] = ['key' => (string)($row['field_key'] ?? $index) . ':' . $index, 'name' => $name, 'value' => $value, 'severity' => $severity];
            $generated[] = $text !== '' ? $text : $name . ':' . $value;
        }
        $notes = [];
        $sourceNote = self::text($raw['human_remark'] ?? $raw['check_remark'] ?? '');
        $quality = self::text($asset['quality_remark'] ?? '');
        $generatedText = self::comparable(implode(';', $generated));
        $qualityText = self::comparable($quality);
        // 兼容旧入库把完整质检摘要截断到500字后写到quality_remark的行为。
        $generatedQuality = count($items) >= 3 && mb_strlen($qualityText) >= 20
            && str_starts_with($generatedText, $qualityText);
        $duplicatedSourceNote = $quality !== '' && $sourceNote !== '' && str_starts_with($sourceNote, $quality);
        foreach (['回收补充说明' => $sourceNote, '设备补充说明' => ($generatedQuality || $duplicatedSourceNote) ? '' : $quality,
            '设备备注' => self::text($asset['remark'] ?? ''),
            '内部备注' => self::text($asset['remark_internal'] ?? '')] as $label => $value) {
            if ($value === '' || preg_match('/^(?:来源设备#\d+|外部来源设备|来源插件\s+[^；]+；来源设备ID\s+\d+)$/u', $value)) continue;
            if (in_array($value, array_column($notes, 'text'), true)) continue;
            $notes[] = ['label' => $label, 'text' => $value];
        }
        return ['items' => $items, 'counts' => $counts, 'count' => count($items), 'manual_notes' => $notes,
            'checked_at' => (int)($snapshot['inspector']['checked_at'] ?? $raw['check_at'] ?? 0),
            'legacy_text' => $items === [] ? self::legacyText($raw) : '',
            'source_plugin' => self::text($snapshot['source_plugin'] ?? $asset['source_plugin'] ?? '')];
    }

    private static function decode($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function text($value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private static function comparable(string $value): string
    {
        return (string)preg_replace('/[\s;；:：]+/u', '', $value);
    }

    private static function legacyText(array $raw): string
    {
        foreach (['check_result_buyer', 'check_result_seller', 'check_result'] as $key) {
            $value = self::text($raw[$key] ?? '');
            if ($value !== '' && self::decode($value) === []) return $value;
        }
        return '';
    }
}
