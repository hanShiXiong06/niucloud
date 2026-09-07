<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\model\third_party\DeviceQueryResult;
use core\exception\CommonException;

/** 设备采集证据存于现有 info JSON。原文不参与备注拼接，不作为人工质检结论。 */
class DeviceReadingArchive
{
    public static function decode($value): array
    {
        if (is_array($value)) return $value;
        if (is_object($value)) {
            $decoded = json_decode(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', true);
            return is_array($decoded) ? $decoded : [];
        }
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function identityKeys(array $device): array
    {
        $values = [];
        foreach (['imei', 'imei2', 'sn', 'serial_number'] as $key) {
            $value = strtoupper(trim((string)($device[$key] ?? '')));
            if ($value !== '') $values[] = $value;
        }
        return array_values(array_unique($values));
    }

    public static function batteryHealth(array $raw): array
    {
        $battery = is_array($raw['battery'] ?? null) ? $raw['battery'] : [];
        foreach ([
            'battery.health_percent' => $battery['health_percent'] ?? null,
            'battery_health' => $raw['battery_health'] ?? null,
            'battery.calculated_health_percent' => $battery['calculated_health_percent'] ?? null,
        ] as $source => $value) {
            $value = trim(rtrim(trim((string)$value), '%'));
            if (!preg_match('/^\d+(\.\d+)?$/D', $value)) continue;
            $number = (float)$value;
            if (!is_finite($number) || ($source !== 'battery.calculated_health_percent' && $number > 100)) continue;
            return ['value' => (int)min(100, round($number)), 'source' => $source];
        }
        return ['value' => null, 'source' => ''];
    }

    /** 已存原文不可被后续摘要/质检编辑覆盖；外部原文仅从本站查询台账加载。 */
    public static function merge(array $existing, array $incoming, array $device, int $siteId): array
    {
        if ($existing === [] && $incoming === []) return [];
        $result = $existing ?: ['version' => 1];
        $keys = self::identityKeys($device);
        $raw = self::decode($incoming['local']['raw'] ?? []);
        if ($raw !== [] && empty($result['local'])) {
            if (strlen(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') > 131072) {
                throw new CommonException('本地设备原始数据超过 128KB，请检查设备服务返回内容');
            }
            $rawKeys = self::identityKeys(self::decode($raw['identity'] ?? $raw));
            if ($keys === [] || array_intersect($keys, $rawKeys) === []) {
                throw new CommonException('设备原始数据的 IMEI/SN 与当前设备不一致，请重新读取正确设备');
            }
            $normalized = self::decode($incoming['local']['normalized'] ?? []);
            $health = self::batteryHealth($raw);
            $normalized['battery_health'] = $health['value'] === null ? '' : (string)$health['value'];
            $normalized['battery_health_source'] = $health['source'];
            $result['local'] = ['raw' => $raw, 'normalized' => $normalized, 'saved_at' => time()];
        }
        // 即使原文已保存，也不能把它悄悄挂到另一台设备上。
        if (!empty($result['local']['raw'])) {
            $storedRaw = $result['local']['raw'];
            if (array_intersect($keys, self::identityKeys(self::decode($storedRaw['identity'] ?? $storedRaw))) === []) {
                throw new CommonException('当前串号与已保存的设备采集记录不一致，请核对设备，不要修改成另一台设备的串号');
            }
        }
        if (!empty($result['local'])) {
            $match = self::decode($incoming['model_match'] ?? ($result['model_match'] ?? []));
            $strategy = (string)($match['strategy'] ?? 'manual');
            if ((int)($match['category_id'] ?? 0) !== (int)($device['category_id'] ?? 0)) $strategy = 'manual';
            $result['model_match'] = [
                'strategy' => in_array($strategy, ['manual', 'alias', 'exact', 'unmatched'], true) ? $strategy : 'manual',
                'category_id' => (int)($device['category_id'] ?? 0),
                'model' => (string)($device['model'] ?? ''),
                'candidates' => array_values(array_filter((array)($match['candidates'] ?? []), 'is_string')),
            ];
        }
        $records = (array)($result['external_queries'] ?? []);
        $known = array_column($records, 'query_record_id');
        foreach ((array)($incoming['external_queries'] ?? []) as $reference) {
            if (!is_array($reference)) throw new CommonException('外部查询记录格式不正确，请重新读取查询结果');
            $id = (int)($reference['query_record_id'] ?? 0);
            if ($id <= 0 || in_array($id, $known, true)) continue;
            $record = DeviceQueryResult::where([['site_id', '=', $siteId], ['id', '=', $id]])->findOrEmpty();
            if ($siteId <= 0 || $record->isEmpty()) throw new CommonException('外部查询记录不存在或不属于本站，请重新核对查询记录');
            $data = $record->toArray();
            if (!in_array(strtoupper(trim((string)$data['query_code'])), $keys, true)) {
                throw new CommonException('保修等外部查询的串号与当前设备不一致，不能保存到这台设备');
            }
            $response = self::decode($data['raw_response'] ?? []);
            $records[] = [
                'query_record_id' => $id, 'query_code' => $data['query_code'],
                'service_name' => $data['api_name'], 'queried_at' => $data['create_at'],
                'status' => (int)$data['status'], 'data' => self::decode($data['query_result'] ?? []),
                'raw_response' => $response,
            ];
            $known[] = $id;
        }
        foreach ($records as $record) {
            if (!in_array(strtoupper(trim((string)($record['query_code'] ?? ''))), $keys, true)) {
                throw new CommonException('当前串号与已保存的外部查询记录不一致，请核对设备');
            }
        }
        if ($records !== []) $result['external_queries'] = $records;
        if (strlen(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') > 1048576) {
            throw new CommonException('设备采集记录超过 1MB，请联系管理员检查外部查询返回内容');
        }
        return $result;
    }
}
