<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

use addon\hsx_recycle\app\model\third_party\DeviceQueryResult;
use think\facade\Log;

class DeviceQueryResultRecorder
{
    public function record(array $payload): void
    {
        try {
            $service = $payload['service'] ?? [];
            $channel = $payload['channel'] ?? [];
            $mapping = $payload['mapping'] ?? [];
            $providerResult = $payload['provider_result'] ?? [];
            $price = $payload['price'] ?? [];
            $status = !empty($payload['success']) ? 1 : 0;

            DeviceQueryResult::saveQueryResult([
                'site_id' => (int)($payload['site_id'] ?? 0),
                'query_code' => (string)($payload['query_code'] ?? ''),
                'query_type' => (string)($payload['query_type'] ?? $service['query_type'] ?? 'other'),
                'api_endpoint' => (string)($mapping['endpoint_value'] ?? $payload['service_code'] ?? ''),
                'api_name' => (string)($service['name'] ?? $payload['service_code'] ?? ''),
                'query_result' => $payload['normalized_result'] ?? [],
                'raw_response' => [
                    'raw' => $providerResult['raw_response'] ?? [],
                    'meta' => [
                        'service_code' => (string)($payload['service_code'] ?? ''),
                        'service_name' => (string)($service['name'] ?? ''),
                        'channel_key' => (string)($channel['key'] ?? ''),
                        'channel_name' => (string)($channel['name'] ?? ''),
                        'provider' => (string)($channel['provider'] ?? ''),
                        'endpoint_type' => (string)($mapping['endpoint_type'] ?? ''),
                        'endpoint_value' => (string)($mapping['endpoint_value'] ?? ''),
                        'third_code' => (int)($providerResult['third_code'] ?? -1),
                        'third_cost' => (float)($price['third_cost'] ?? 0),
                        'cost_price' => (float)($price['cost_price'] ?? 0),
                        'saved_cost' => (float)($price['saved_cost'] ?? 0),
                        'balance' => (float)($price['balance'] ?? 0),
                        'from_cache' => (int)(bool)($payload['from_cache'] ?? false),
                    ],
                ],
                'status' => $status,
                'cost_amount' => (float)($price['third_cost'] ?? 0),
                'response_time' => (int)($payload['duration'] ?? 0),
                'error_code' => (string)($providerResult['third_code'] ?? ''),
                'error_message' => $status ? '' : (string)($providerResult['message'] ?? $payload['error_msg'] ?? '查询失败'),
                'remark' => $this->buildRemark($payload, $price, $status),
            ]);
        } catch (\Exception $e) {
            Log::warning('保存设备查询结果失败: ' . $e->getMessage());
        }
    }

    private function buildRemark(array $payload, array $price, int $status): string
    {
        $channel = $payload['channel'] ?? [];
        $service = $payload['service'] ?? [];
        $parts = [
            !empty($payload['from_cache']) ? '本地缓存' : '设备查询',
            (string)($service['name'] ?? $payload['service_code'] ?? ''),
            (string)($channel['name'] ?? ''),
            '扣费:' . number_format((float)($price['third_cost'] ?? 0), 3),
        ];
        if (!$status) {
            $parts[] = '失败';
        }

        $remark = implode(' | ', array_filter($parts, static fn($item) => $item !== ''));

        return function_exists('mb_substr') ? mb_substr($remark, 0, 255) : substr($remark, 0, 255);
    }
}
