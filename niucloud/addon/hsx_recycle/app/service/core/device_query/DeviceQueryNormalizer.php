<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

class DeviceQueryNormalizer
{
    public function normalize(string $serviceCode, array $rawData): array
    {
        $coverage = is_array($rawData['coverage'] ?? null) ? $rawData['coverage'] : [];
        $purchase = is_array($rawData['purchase'] ?? null) ? $rawData['purchase'] : [];
        $activation = is_array($rawData['activation'] ?? null) ? $rawData['activation'] : [];
        $manufacture = is_array($rawData['manufacture'] ?? null) ? $rawData['manufacture'] : [];

        $data = [
            'brand' => $rawData['brand'] ?? $this->guessBrand($serviceCode),
            'model' => $rawData['model'] ?? $rawData['model_name'] ?? '',
            'model_number' => $rawData['model_number'] ?? $rawData['modelnumber'] ?? '',
            'identifier' => $rawData['identifier'] ?? '',
            'capacity' => $rawData['capacity'] ?? '',
            'color' => $rawData['color'] ?? '',
            'sn' => $rawData['sn'] ?? $rawData['serial'] ?? '',
            'imei' => $rawData['imei'] ?? '',
            'imei2' => $rawData['imei2'] ?? '',
            'description' => $rawData['description'] ?? '',
            'locked' => $rawData['locked'] ?? null,
            'activated' => $rawData['activated'] ?? null,
            'pre_activated' => $rawData['pre_activated'] ?? $rawData['pre-activated'] ?? null,
            'activation_lock' => $rawData['activation_lock'] ?? $rawData['fmi'] ?? $rawData['locked'] ?? '',
            'network_lock' => $rawData['network_lock'] ?? $rawData['simlock'] ?? '',
            'coverage_status' => $rawData['coverage_status'] ?? $coverage['status'] ?? $rawData['coverage'] ?? '',
            'coverage_description' => $rawData['coverage_description'] ?? $coverage['description'] ?? '',
            'coverage_denied' => $rawData['coverage_denied'] ?? '',
            'coverage_date' => $rawData['coverage_date'] ?? $coverage['date'] ?? '',
            'coverage_days_remaining' => $rawData['coverage_days_remaining'] ?? $rawData['days_left'] ?? $coverage['days-remaining'] ?? 0,
            'purchase_date' => $rawData['purchase_date'] ?? $purchase['date'] ?? $activation['date'] ?? '',
            'purchase_type' => $rawData['purchase_type'] ?? '',
            'purchase_validated' => $rawData['purchase_validated'] ?? $purchase['validated'] ?? null,
            'apple_care' => $rawData['apple_care'] ?? $rawData['applecare'] ?? null,
            'apple_care_eligible' => $rawData['apple_care_eligible'] ?? $rawData['applecare-eligible'] ?? '',
            'registered' => $rawData['registered'] ?? '',
            'replaced' => $rawData['replaced'] ?? null,
            'loaner' => $rawData['loaner'] ?? '',
            'bright_star' => $rawData['bright_star'] ?? $rawData['brightstar'] ?? '',
            'maintenance' => $rawData['maintenance'] ?? false,
            'manufacturer' => $rawData['manufacturer'] ?? '',
            'manufacture_date' => $rawData['manufacture_date'] ?? $manufacture['date'] ?? '',
            'support' => $rawData['support'] ?? '',
            'image' => $rawData['image'] ?? '',
            'raw' => $rawData,
        ];

        if ($data['coverage_date'] !== '') {
            $data['coverage_date'] = $this->formatDateLike((string)$data['coverage_date']);
        }

        return $data;
    }

    private function guessBrand(string $serviceCode): string
    {
        if (str_starts_with($serviceCode, 'apple_')) {
            return 'Apple';
        }
        if (str_starts_with($serviceCode, 'huawei_')) {
            return 'Huawei';
        }
        if (str_starts_with($serviceCode, 'honor_')) {
            return 'Honor';
        }
        if (str_starts_with($serviceCode, 'xiaomi_')) {
            return 'Xiaomi';
        }
        if (str_starts_with($serviceCode, 'oppo_')) {
            return 'OPPO';
        }
        if (str_starts_with($serviceCode, 'vivo_')) {
            return 'vivo';
        }
        if (str_starts_with($serviceCode, 'samsung_')) {
            return 'Samsung';
        }

        return '';
    }

    private function formatDateLike(string $date): string
    {
        if ($date === '' || in_array($date, ['Expired', 'Active'], true)) {
            return $date;
        }

        $time = strtotime($date);
        if ($time === false) {
            return $date;
        }

        return date('Y-m-d', $time);
    }
}
