<?php

namespace addon\hsx_phone_query\app\service\admin\hsx_phone_query_config;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryConfigDict;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;

/**
 * 手机查询服务商配置服务层
 */
class HsxPhoneQueryConfigService extends BaseAdminService
{
    private CoreConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new CoreConfigService();
    }

    /**
     * 兼容旧列表页：配置实际保存在 sys_config 中。
     */
    public function getPage(array $where = []): array
    {
        $config = $this->getConfig();
        $gkdt = $this->getChannel($config, 'gkdt_main');

        return [
            'data' => [[
                'id' => 1,
                'site_id' => $this->site_id,
                'appid' => $gkdt['appid'] ?? '',
                'Secret' => $gkdt['secret'] ?? '',
            ]],
            'total' => 1,
        ];
    }

    public function getInfo(int $id): array
    {
        return $this->getPage()['data'][0];
    }

    public function add(array $data)
    {
        $this->saveGkdtConfig($data);
        return 1;
    }

    public function edit(int $id, array $data): bool
    {
        $this->saveGkdtConfig($data);
        return true;
    }

    public function del(int $id): bool
    {
        $config = $this->getConfig();
        foreach ($config['channels'] as &$channel) {
            if (($channel['key'] ?? '') === 'gkdt_main') {
                $channel['appid'] = '';
                $channel['secret'] = '';
            }
        }

        return (bool)$this->configService->setConfig($this->site_id, HsxPhoneQueryConfigDict::CONFIG_KEY, $config);
    }

    /**
     * 获取完整服务商配置。
     */
    public function getProviderConfig(): array
    {
        $config = $this->getConfig();
        $config['setup_status'] = $this->getSetupStatus($config);
        return $config;
    }

    /**
     * 保存完整服务商配置。
     */
    public function saveProviderConfig(array $data): bool
    {
        $config = $this->normalizeConfig($data);
        return (bool)$this->configService->setConfig($this->site_id, HsxPhoneQueryConfigDict::CONFIG_KEY, $config);
    }

    private function saveGkdtConfig(array $data): bool
    {
        $config = $this->getConfig();
        $config['default_channel_key'] = 'gkdt_main';
        foreach ($config['channels'] as &$channel) {
            if (($channel['key'] ?? '') === 'gkdt_main') {
                $channel['enabled'] = 1;
                $channel['appid'] = (string)($data['appid'] ?? '');
                $channel['secret'] = (string)($data['Secret'] ?? $data['secret'] ?? '');
            } else {
                $channel['enabled'] = 0;
            }
        }

        return (bool)$this->configService->setConfig($this->site_id, HsxPhoneQueryConfigDict::CONFIG_KEY, $config);
    }

    private function getConfig(): array
    {
        $saved = $this->configService->getConfigValue($this->site_id, HsxPhoneQueryConfigDict::CONFIG_KEY);
        $saved = is_array($saved) ? $saved : [];

        return $this->normalizeConfig($saved);
    }

    private function getChannel(array $config, string $key): array
    {
        foreach ($config['channels'] ?? [] as $channel) {
            if (($channel['key'] ?? '') === $key) {
                return $channel;
            }
        }

        return [];
    }

    private function normalizeConfig(array $data): array
    {
        return HsxPhoneQueryConfigDict::normalizeConfig($data);
    }

    private function getSetupStatus(array $config): array
    {
        $readyChannels = [];
        $activeChannel = [];
        foreach ($config['channels'] ?? [] as $channel) {
            if (HsxPhoneQueryConfigDict::isChannelReady($channel)) {
                if ((string)($channel['key'] ?? '') === (string)($config['default_channel_key'] ?? '')) {
                    $activeChannel = [
                        'key' => $channel['key'],
                        'name' => $channel['name'],
                    ];
                }
                $readyChannels[] = [
                    'key' => $channel['key'],
                    'name' => $channel['name'],
                ];
            }
        }

        return [
            'ready' => !empty($readyChannels),
            'active_channel' => $activeChannel,
            'ready_channels' => $readyChannels,
            'message' => empty($readyChannels) ? '请至少配置并启用一个服务商密钥' : '已启用单一服务商渠道，可以发起查询',
        ];
    }
}
