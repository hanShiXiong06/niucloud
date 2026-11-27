<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

use addon\hsx_recycle\app\dict\device_query\DeviceQueryServiceDict;
use addon\hsx_recycle\app\dict\device_query\GkdtDeviceQueryServiceDict;

class DeviceQueryCatalogService
{
    public function defaultServices(): array
    {
        $services = [];
        foreach (DeviceQueryServiceDict::SERVICES as $service) {
            $services[(string)$service['code']] = $service;
        }
        foreach (GkdtDeviceQueryServiceDict::services() as $service) {
            $code = (string)$service['code'];
            $services[$code] = array_merge($services[$code] ?? [], $service);
        }

        return array_values($services);
    }

    public function serviceCodeFromEndpoint(string $endpoint): string
    {
        $map = $this->endpointMap();
        $endpoint = '/' . ltrim(trim($endpoint), '/');

        return $map[$endpoint] ?? str_replace(['/', '-'], ['_', '_'], trim($endpoint, '/'));
    }

    public function endpointFromServiceCode(string $serviceCode): string
    {
        $map = array_flip($this->endpointMap());

        return $map[$serviceCode] ?? '';
    }

    public function endpointMap(): array
    {
        $result = [];
        foreach (DeviceQueryServiceDict::PROVIDER_ENDPOINTS['3023_main'] as $serviceCode => $endpoint) {
            $result[(string)$endpoint] = (string)$serviceCode;
        }
        return $result;
    }

    public function defaultProviders(): array
    {
        return array_values(DeviceQueryServiceDict::PROVIDERS);
    }

    public function defaultMappings(): array
    {
        $serviceMap = [];
        foreach (DeviceQueryServiceDict::SERVICES as $service) {
            $serviceMap[(string)$service['code']] = $service;
        }

        $mappings = [];
        foreach (DeviceQueryServiceDict::PROVIDER_ENDPOINTS as $providerKey => $endpoints) {
            $provider = DeviceQueryServiceDict::PROVIDERS[$providerKey] ?? [];
            foreach ($endpoints as $serviceCode => $endpoint) {
                $service = $serviceMap[(string)$serviceCode] ?? [];
                if (empty($service)) {
                    continue;
                }
                $mappings[] = [
                    'service_code' => (string)$serviceCode,
                    'channel_key' => (string)$providerKey,
                    'enabled' => $providerKey === '3023_main' ? 1 : 0,
                    'endpoint_type' => (string)($provider['endpoint_type'] ?? 'path'),
                    'endpoint_value' => (string)$endpoint,
                    'query_param' => (string)(($provider['provider'] ?? '') === 'gkdt_query' ? 'code' : ($service['query_type'] ?? 'imei')),
                    'cost_price' => (float)($service['cost_price'] ?? 0),
                    'retry_on' => [410, 502, 503],
                    'switch_on_404' => 0,
                    'switch_on_no_data' => 0,
                ];
            }
        }
        $mappings = array_merge($mappings, GkdtDeviceQueryServiceDict::mappings(false));

        return $mappings;
    }

    public function providerServicePresets(): array
    {
        return [
            GkdtDeviceQueryServiceDict::PROVIDER_KEY => GkdtDeviceQueryServiceDict::presets(),
        ];
    }

    public function dictionaries(): array
    {
        return [
            'categories' => $this->toOptions(DeviceQueryServiceDict::CATEGORIES),
            'query_types' => $this->toOptions(DeviceQueryServiceDict::QUERY_TYPES),
            'result_handlers' => $this->toOptions(DeviceQueryServiceDict::RESULT_HANDLERS),
            'providers' => $this->defaultProviders(),
            'provider_service_presets' => $this->providerServicePresets(),
        ];
    }

    private function toOptions(array $dictionary): array
    {
        $result = [];
        foreach ($dictionary as $value => $label) {
            $result[] = ['value' => (string)$value, 'label' => (string)$label];
        }
        return $result;
    }
}
