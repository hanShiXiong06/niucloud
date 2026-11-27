<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

class DeviceQueryChannelSelector
{
    public function select(array $config, string $serviceCode, string $channelKey = ''): array
    {
        $channels = [];
        foreach ($config['channels'] ?? [] as $channel) {
            if (!empty($channel['enabled']) && !empty($channel['key'])) {
                $channels[(string)$channel['key']] = $channel;
            }
        }

        $candidates = [];
        foreach ($config['mappings'] ?? [] as $mapping) {
            if (empty($mapping['enabled']) || (string)($mapping['service_code'] ?? '') !== $serviceCode) {
                continue;
            }
            $key = (string)($mapping['channel_key'] ?? '');
            if ($key === '' || !isset($channels[$key])) {
                continue;
            }
            if ($channelKey !== '' && $key !== $channelKey) {
                continue;
            }

            $candidates[] = [
                'channel' => $channels[$key],
                'mapping' => $mapping,
            ];
        }

        usort($candidates, static function ($a, $b) {
            return (int)($b['channel']['priority'] ?? 0) <=> (int)($a['channel']['priority'] ?? 0);
        });

        return $candidates;
    }
}
