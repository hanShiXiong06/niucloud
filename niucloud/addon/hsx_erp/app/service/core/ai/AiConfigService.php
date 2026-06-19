<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core\ai;

use app\service\core\sys\CoreConfigService;

/**
 * AI 站点级配置（复用平台 CoreConfigService，配置 key = HSX_AI）
 *
 * 仅站点级：每个站点各自配置密钥/模型/开关。密钥只在后端读取，
 * 对前端输出时一律脱敏。
 */
class AiConfigService
{
    const CONFIG_KEY = 'HSX_AI';

    /**
     * 默认配置
     */
    public static function defaults(): array
    {
        return [
            'enabled'           => 0,
            'base_url'          => 'https://yunwu.ai/v1',
            'api_key'           => '',
            'default_model'     => 'gpt-4o-mini',
            'available_models'  => ['gpt-4o-mini', 'gpt-4o', 'deepseek-chat'],
            'timeout'           => 60,
            'daily_token_limit' => 0,
        ];
    }

    /**
     * 读取站点配置（原始，含真实密钥，仅后端内部调用）
     */
    public function getRaw(int $site_id): array
    {
        $info = (new CoreConfigService())->getConfig($site_id, self::CONFIG_KEY);
        $value = $info['value'] ?? [];
        return array_merge(self::defaults(), is_array($value) ? $value : []);
    }

    /**
     * 读取站点配置（脱敏，给前端用）
     */
    public function get(int $site_id): array
    {
        $config = $this->getRaw($site_id);
        $hasKey = $config['api_key'] !== '';
        $config['has_api_key'] = $hasKey;
        $config['api_key'] = $hasKey ? $this->mask($config['api_key']) : '';
        return $config;
    }

    /**
     * 保存站点配置
     * 规则：api_key 为空或仍是脱敏串（含 ****）时，保留原密钥不覆盖
     */
    public function save(int $site_id, array $data): bool
    {
        $old = $this->getRaw($site_id);

        $incomingKey = trim((string)($data['api_key'] ?? ''));
        if ($incomingKey === '' || strpos($incomingKey, '****') !== false) {
            $apiKey = $old['api_key'];
        } else {
            $apiKey = $incomingKey;
        }

        $models = $data['available_models'] ?? $old['available_models'];
        if (is_string($models)) {
            $models = array_values(array_filter(array_map('trim', explode(',', $models))));
        }

        $config = [
            'enabled'           => (int)($data['enabled'] ?? $old['enabled']),
            'base_url'          => trim((string)($data['base_url'] ?? $old['base_url'])) ?: self::defaults()['base_url'],
            'api_key'           => $apiKey,
            'default_model'     => trim((string)($data['default_model'] ?? $old['default_model'])),
            'available_models'  => array_values($models),
            'timeout'           => (int)($data['timeout'] ?? $old['timeout']),
            'daily_token_limit' => (int)($data['daily_token_limit'] ?? $old['daily_token_limit']),
        ];

        (new CoreConfigService())->setConfig($site_id, self::CONFIG_KEY, $config);
        return true;
    }

    /**
     * 用原始配置构造一个渠道实例
     */
    public function channel(int $site_id): AiChannelService
    {
        $config = $this->getRaw($site_id);
        return new AiChannelService($config['base_url'], $config['api_key'], (int)$config['timeout']);
    }

    protected function mask(string $key): string
    {
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', max(0, $len - 2)) . substr($key, -2);
        }
        return substr($key, 0, 3) . '****' . substr($key, -4);
    }
}
