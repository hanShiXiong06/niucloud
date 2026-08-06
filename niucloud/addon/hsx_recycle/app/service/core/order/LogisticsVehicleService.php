<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use core\exception\CommonException;

/** 物流车交付订单的字段校验与预计到达时间计算。 */
class LogisticsVehicleService
{
    public function prepareOrderData(int $siteId, array $data, ?int $createdAt = null): array
    {
        if ((int)($data['delivery_type'] ?? 0) !== (int)RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE) {
            return $data;
        }

        $config = (new OrderSubmitConfigService())->getConfig($siteId);
        if (empty($config['delivery_modes']['logistics_vehicle'])) {
            throw new CommonException('物流车配送暂未开启');
        }

        $fields = [
            'logistics_name' => ['物流名称', 100],
            'logistics_vehicle_no' => ['车牌号', 50],
            'logistics_contact_name' => ['联系人', 50],
            'logistics_contact_mobile' => ['联系电话', 30],
            'logistics_pickup_address' => ['取货地点', 255],
        ];
        foreach ($fields as $field => [$label, $length]) {
            $value = mb_substr(trim((string)($data[$field] ?? '')), 0, $length);
            if ($value === '') {
                throw new CommonException('请填写' . $label);
            }
            $data[$field] = $value;
        }

        $data['express_company'] = '';
        $data['express_no'] = '';
        $data['use_express'] = 0;
        $data['logistics_eta_at'] = $this->calculateEta(
            (array)($config['logistics_vehicle'] ?? []),
            $createdAt ?? time()
        );

        return $data;
    }

    public function calculateEta(array $config, int $createdAt): int
    {
        $mode = (string)($config['arrival_mode'] ?? 'half_day');
        $cutoff = $this->normalizeTime((string)($config['morning_cutoff'] ?? '12:00'), '12:00');
        $sameDayTime = $this->normalizeTime((string)($config['same_day_time'] ?? '16:00'), '16:00');
        $nextDayTime = $this->normalizeTime((string)($config['next_day_time'] ?? '09:00'), '09:00');
        $day = date('Y-m-d', $createdAt);

        if ($mode === 'half_day' && $createdAt <= strtotime($day . ' ' . $cutoff . ':59')) {
            $sameDayEta = (int)strtotime($day . ' ' . $sameDayTime . ':00');
            if ($sameDayEta > $createdAt) {
                return $sameDayEta;
            }
        }

        return (int)strtotime('+1 day', strtotime($day . ' ' . $nextDayTime . ':00'));
    }

    private function normalizeTime(string $value, string $fallback): string
    {
        $value = trim($value);
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $fallback;
    }
}
