<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

final class AiConfigService
{
    public const CONFIG_KEY = 'HSX_AI_CONFIG';
    public const SECRET_MASK = '******';

    public static function isBaiduDirectApiKey(string $apiKey): bool
    {
        return str_starts_with(trim($apiKey), 'bce-v3/');
    }

    public function get(int $siteId, bool $maskSecrets = false): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        $config = $this->normalize(is_array($value) ? $value : []);
        if ($maskSecrets) {
            foreach ($config['providers'] as &$provider) {
                $configured = $provider['api_key'] !== '' && $provider['api_key'] !== self::SECRET_MASK;
                $provider['api_key_configured'] = $configured ? 1 : 0;
                $provider['api_key_fingerprint'] = $configured ? substr(hash('sha256', (string)$provider['api_key']), 0, 12) : '';
                $provider['api_key'] = $configured ? self::SECRET_MASK : '';
            }
            unset($provider);
            foreach (['api_key', 'secret_id', 'secret_key'] as $field) {
                $configured = (string)($config['speech'][$field] ?? '') !== ''
                    && (string)$config['speech'][$field] !== self::SECRET_MASK;
                $config['speech'][$field . '_configured'] = $configured ? 1 : 0;
                $config['speech'][$field] = $configured ? self::SECRET_MASK : '';
            }
        }
        return $config;
    }

    public function save(int $siteId, array $data): array
    {
        $stored = $this->get($siteId);
        if (array_key_exists('providers', $data)) {
            $storedMap = [];
            foreach ($stored['providers'] as $provider) {
                $storedMap[(string)$provider['id']] = $provider;
            }
            $data['providers'] = $this->preserveProviderSecrets((array)$data['providers'], $storedMap);
        }
        if (array_key_exists('speech', $data)) {
            $data['speech'] = $this->preserveSpeechSecrets((array)$data['speech'], (array)($stored['speech'] ?? []));
        }

        $config = $this->normalize(array_replace($stored, $data));
        $this->validate($config);
        (new CoreConfigService())->setConfig($siteId, self::CONFIG_KEY, $config);
        return $this->get($siteId, true);
    }

    public function provider(int $siteId, string $providerId): array
    {
        $config = $this->get($siteId);
        foreach ($config['providers'] as $provider) {
            if ((string)$provider['id'] === $providerId) return $provider;
        }
        throw new CommonException('模型通道不存在：' . $providerId);
    }

    public function resolveProviderInput(int $siteId, array $input): array
    {
        $id = trim((string)($input['id'] ?? ''));
        $stored = [];
        if ($id !== '') {
            try {
                $stored = $this->provider($siteId, $id);
            } catch (\Throwable) {
                $stored = [];
            }
        }
        if (trim((string)($input['api_key'] ?? '')) === self::SECRET_MASK || trim((string)($input['api_key'] ?? '')) === '') {
            $storedKey = $this->normalizeSecret((string)($stored['api_key'] ?? ''));
            $input['api_key'] = $storedKey !== self::SECRET_MASK ? $storedKey : '';
        }
        $provider = $this->normalizeProvider(array_replace($stored, $input), 0);
        $this->validateProvider($provider, true);
        return $provider;
    }

    public function resolveSpeechInput(int $siteId, array $input): array
    {
        $stored = (array)($this->get($siteId)['speech'] ?? []);
        $speech = $this->preserveSpeechSecrets($input, $stored);
        return $this->normalizeSpeech(array_replace($stored, $speech));
    }

    private function normalize(array $data): array
    {
        $providers = [];
        foreach (array_values(array_filter((array)($data['providers'] ?? []), 'is_array')) as $index => $provider) {
            $providers[] = $this->normalizeProvider($provider, $index);
        }
        if ($providers === []) {
            $providers[] = $this->normalizeProvider([
                'id' => 'yunwu',
                'name' => '云雾 API',
                'driver' => 'openai_compatible',
                'base_url' => 'https://yunwu.ai',
                'models_path' => '/v1/models',
                'chat_path' => '/v1/chat/completions',
                'enabled' => 1,
                'timeout' => 60,
            ], 0);
        }

        $scenes = [];
        foreach (array_values(array_filter((array)($data['scenes'] ?? []), 'is_array')) as $index => $scene) {
            $scenes[] = $this->normalizeScene($scene, $index);
        }
        $hasGeneral = count(array_filter($scenes, static fn(array $scene): bool => $scene['key'] === 'general')) > 0;
        if (!$hasGeneral) {
            $scenes[] = $this->normalizeScene([
                'key' => 'general',
                'name' => '通用测试',
                'enabled' => 1,
                'provider_id' => (string)$providers[0]['id'],
                'system_prompt' => '你是业务系统中的AI助手。回答必须准确、简洁；缺少事实时明确说明，不得编造业务数据。',
            ], 0);
        }
        $hasMallAssistant = count(array_filter($scenes, static fn(array $scene): bool => $scene['key'] === 'phone_shop.customer_assistant')) > 0;
        if (!$hasMallAssistant) {
            $scenes[] = $this->normalizeScene([
                'key' => 'phone_shop.customer_assistant',
                'name' => '商城选机助手',
                'enabled' => 1,
                'provider_id' => (string)$providers[0]['id'],
                'system_prompt' => '你是本站商城的选机助手。你只能依据系统提供的本站商品事实，回答商品选购、价格、库存、成色、配置和购买相关问题。不得回答与本站业务无关的知识，不得猜测库存、价格或商品参数。没有合适商品时直接说明，并建议用户调整预算或需求。回答简洁真诚，推荐商品时说明推荐理由。',
                'temperature' => 0.2,
            ], count($scenes));
        }

        $defaultProviderId = trim((string)($data['default_provider_id'] ?? ''));
        $providerIds = array_column($providers, 'id');
        if ($defaultProviderId === '' || !in_array($defaultProviderId, $providerIds, true)) {
            $defaultProviderId = (string)$providers[0]['id'];
        }
        return [
            'enabled' => (int)!empty($data['enabled']),
            'default_provider_id' => $defaultProviderId,
            'default_model' => trim((string)($data['default_model'] ?? '')),
            'redact_sensitive' => (int)($data['redact_sensitive'] ?? 1) === 1 ? 1 : 0,
            'log_content' => (int)!empty($data['log_content']),
            'integrations' => $this->normalizeIntegrations((array)($data['integrations'] ?? [])),
            'speech' => $this->normalizeSpeech((array)($data['speech'] ?? [])),
            'providers' => $providers,
            'scenes' => $scenes,
        ];
    }

    private function normalizeIntegrations(array $integrations): array
    {
        $rows = [];
        foreach (array_values(array_filter($integrations, 'is_array')) as $integration) {
            $key = strtolower(trim((string)($integration['key'] ?? '')));
            $key = preg_replace('/[^a-z0-9_]/', '_', $key) ?: '';
            if ($key === '') continue;
            $rows[$key] = [
                'key' => $key,
                'enabled' => (int)!empty($integration['enabled']),
            ];
        }
        return array_values($rows);
    }

    private function normalizeSpeech(array $speech): array
    {
        return [
            'enabled' => (int)!empty($speech['enabled']),
            'provider' => in_array((string)($speech['provider'] ?? 'baidu'), ['baidu', 'tencent'], true)
                ? (string)($speech['provider'] ?? 'baidu')
                : 'baidu',
            'stt_enabled' => (int)($speech['stt_enabled'] ?? 1) === 1 ? 1 : 0,
            'tts_enabled' => (int)($speech['tts_enabled'] ?? 1) === 1 ? 1 : 0,
            'auto_read_default' => (int)($speech['auto_read_default'] ?? 0) === 1 ? 1 : 0,
            'api_key' => $this->normalizeSecret((string)($speech['api_key'] ?? '')),
            'baidu_auth_mode' => self::isBaiduDirectApiKey((string)($speech['api_key'] ?? '')) ? 'api_key' : 'access_token',
            'secret_id' => $this->normalizeSecret((string)($speech['secret_id'] ?? '')),
            'secret_key' => $this->normalizeSecret((string)($speech['secret_key'] ?? '')),
            'region' => trim((string)($speech['region'] ?? 'ap-shanghai')) ?: 'ap-shanghai',
            'stt_engine' => trim((string)($speech['stt_engine'] ?? '16k_zh')) ?: '16k_zh',
            'voice' => max(0, min(5118, (int)($speech['voice'] ?? 0))),
            'speed' => max(0, min(15, (int)($speech['speed'] ?? 5))),
            'pitch' => max(0, min(15, (int)($speech['pitch'] ?? 5))),
            'volume' => max(0, min(15, (int)($speech['volume'] ?? 5))),
            'tencent_voice' => max(0, (int)($speech['tencent_voice'] ?? 1001)),
            'tencent_speed' => max(-2, min(6, (float)($speech['tencent_speed'] ?? 0))),
            'tencent_volume' => max(-10, min(10, (float)($speech['tencent_volume'] ?? 0))),
        ];
    }

    private function normalizeProvider(array $provider, int $index): array
    {
        $id = strtolower(trim((string)($provider['id'] ?? 'provider_' . ($index + 1))));
        $id = preg_replace('/[^a-z0-9_]/', '_', $id) ?: ('provider_' . ($index + 1));
        $models = [];
        foreach (array_values(array_filter((array)($provider['models'] ?? []), 'is_array')) as $model) {
            $modelId = trim((string)($model['id'] ?? ''));
            if ($modelId === '') continue;
            $models[$modelId] = [
                'id' => $modelId,
                'name' => trim((string)($model['name'] ?? $modelId)) ?: $modelId,
                'owned_by' => trim((string)($model['owned_by'] ?? '')),
                'enabled' => (int)($model['enabled'] ?? 1) === 1 ? 1 : 0,
            ];
        }
        return [
            'id' => $id,
            'name' => trim((string)($provider['name'] ?? $id)) ?: $id,
            'driver' => trim((string)($provider['driver'] ?? 'openai_compatible')) ?: 'openai_compatible',
            'base_url' => rtrim(trim((string)($provider['base_url'] ?? '')), '/'),
            'api_key' => $this->normalizeSecret((string)($provider['api_key'] ?? '')),
            'models_path' => '/' . ltrim(trim((string)($provider['models_path'] ?? '/v1/models')), '/'),
            'chat_path' => '/' . ltrim(trim((string)($provider['chat_path'] ?? '/v1/chat/completions')), '/'),
            'timeout' => max(5, min(180, (int)($provider['timeout'] ?? 60))),
            'connect_timeout' => max(5, min(90, (int)($provider['connect_timeout'] ?? 30))),
            'ip_version' => in_array((string)($provider['ip_version'] ?? 'ipv4'), ['auto', 'ipv4', 'ipv6'], true)
                ? (string)($provider['ip_version'] ?? 'ipv4')
                : 'ipv4',
            'http_version' => in_array((string)($provider['http_version'] ?? '1.1'), ['auto', '1.1'], true)
                ? (string)($provider['http_version'] ?? '1.1')
                : '1.1',
            'enabled' => (int)($provider['enabled'] ?? 1) === 1 ? 1 : 0,
            'default_model' => trim((string)($provider['default_model'] ?? '')),
            'models' => array_values($models),
        ];
    }

    private function normalizeScene(array $scene, int $index): array
    {
        $key = strtolower(trim((string)($scene['key'] ?? 'scene_' . ($index + 1))));
        $key = preg_replace('/[^a-z0-9_.]/', '_', $key) ?: ('scene_' . ($index + 1));
        $responseMode = trim((string)($scene['response_mode'] ?? 'text'));
        if (!in_array($responseMode, ['text', 'json'], true)) $responseMode = 'text';
        return [
            'key' => $key,
            'name' => trim((string)($scene['name'] ?? $key)) ?: $key,
            'enabled' => (int)($scene['enabled'] ?? 1) === 1 ? 1 : 0,
            'provider_id' => trim((string)($scene['provider_id'] ?? '')),
            'model' => trim((string)($scene['model'] ?? '')),
            'system_prompt' => trim((string)($scene['system_prompt'] ?? '')),
            'temperature' => max(0, min(2, (float)($scene['temperature'] ?? 0.2))),
            'max_tokens' => max(0, min(128000, (int)($scene['max_tokens'] ?? 0))),
            'response_mode' => $responseMode,
        ];
    }

    private function validate(array $config): void
    {
        if (!empty($config['speech']['enabled'])) {
            $speech = (array)$config['speech'];
            $missing = (string)$speech['provider'] === 'tencent'
                ? ((string)$speech['secret_id'] === '' || (string)$speech['secret_key'] === '')
                : ((string)$speech['api_key'] === '' || (!self::isBaiduDirectApiKey((string)$speech['api_key']) && (string)$speech['secret_key'] === ''));
            if ($missing) {
                throw new CommonException('启用语音服务前需要填写当前服务商的访问密钥');
            }
        }
        $providerIds = [];
        $providerMap = [];
        foreach ($config['providers'] as $provider) {
            if (isset($providerIds[$provider['id']])) throw new CommonException('模型通道标识不能重复');
            $providerIds[$provider['id']] = true;
            $providerMap[$provider['id']] = $provider;
            $this->validateProvider($provider, !empty($config['enabled']) && !empty($provider['enabled']));
            $this->validateModel($provider, (string)$provider['default_model'], '通道“' . $provider['name'] . '”的默认模型');
        }
        $sceneKeys = [];
        foreach ($config['scenes'] as $scene) {
            if (isset($sceneKeys[$scene['key']])) throw new CommonException('AI场景标识不能重复');
            $sceneKeys[$scene['key']] = true;
            $sceneProviderId = (string)($scene['provider_id'] ?: $config['default_provider_id']);
            if (!isset($providerIds[$sceneProviderId])) {
                throw new CommonException('场景“' . $scene['name'] . '”关联的模型通道不存在');
            }
            if (!empty($config['enabled']) && !empty($scene['enabled']) && empty($providerMap[$sceneProviderId]['enabled'])) {
                throw new CommonException('场景“' . $scene['name'] . '”关联的模型通道已停用');
            }
            $this->validateModel($providerMap[$sceneProviderId], (string)$scene['model'], '场景“' . $scene['name'] . '”的模型');
            if (!empty($config['enabled']) && !empty($scene['enabled'])) {
                $resolvedModel = (string)($scene['model'] ?: $providerMap[$sceneProviderId]['default_model'] ?: $config['default_model']);
                if ($resolvedModel === '') {
                    throw new CommonException('场景“' . $scene['name'] . '”尚未选择模型');
                }
                $this->validateModel($providerMap[$sceneProviderId], $resolvedModel, '场景“' . $scene['name'] . '”的模型');
            }
        }
        if (!empty($config['enabled'])) {
            $enabled = array_values(array_filter($config['providers'], static fn(array $provider): bool => !empty($provider['enabled'])));
            if ($enabled === []) throw new CommonException('启用 AI 前至少需要启用一个模型通道');
            if (empty($providerMap[$config['default_provider_id']]['enabled'])) {
                throw new CommonException('启用 AI 前需要启用默认模型通道');
            }
        }
        $this->validateModel(
            $providerMap[$config['default_provider_id']],
            (string)$config['default_model'],
            '全局默认模型'
        );
    }

    private function validateProvider(array $provider, bool $requireSecret): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,59}$/', (string)$provider['id'])) {
            throw new CommonException('模型通道标识必须以字母开头，只能包含小写字母、数字和下划线');
        }
        $url = (string)$provider['base_url'];
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            throw new CommonException('模型通道 Base URL 必须是有效的 http 或 https 地址');
        }
        if ($requireSecret && (string)$provider['api_key'] === '') {
            throw new CommonException('模型通道“' . $provider['name'] . '”缺少 API Key');
        }
    }

    private function validateModel(array $provider, string $modelId, string $label): void
    {
        if ($modelId === '' || $provider['models'] === []) return;
        foreach ($provider['models'] as $model) {
            if ((string)$model['id'] === $modelId && !empty($model['enabled'])) return;
        }
        throw new CommonException($label . '不存在或已停用');
    }

    private function preserveProviderSecrets(array $providers, array $storedMap): array
    {
        foreach ($providers as &$provider) {
            if (!is_array($provider)) continue;
            $id = trim((string)($provider['id'] ?? ''));
            $apiKey = $this->normalizeSecret((string)($provider['api_key'] ?? ''));
            if ($apiKey === '' || $apiKey === self::SECRET_MASK) {
                $storedKey = $this->normalizeSecret((string)($storedMap[$id]['api_key'] ?? ''));
                $apiKey = $storedKey !== self::SECRET_MASK ? $storedKey : '';
            }
            $provider['api_key'] = $apiKey;
        }
        unset($provider);
        return $providers;
    }

    private function preserveSpeechSecrets(array $speech, array $stored): array
    {
        $provider = (string)($speech['provider'] ?? $stored['provider'] ?? 'baidu');
        $storedProvider = (string)($stored['provider'] ?? 'baidu');
        $providerChanged = $provider !== $storedProvider;
        foreach (['api_key', 'secret_id', 'secret_key'] as $field) {
            $value = $this->normalizeSecret((string)($speech[$field] ?? ''));
            if (!$providerChanged && ($value === '' || $value === self::SECRET_MASK)) {
                $storedValue = $this->normalizeSecret((string)($stored[$field] ?? ''));
                $value = $storedValue !== self::SECRET_MASK ? $storedValue : '';
            }
            $speech[$field] = $value;
        }
        if ($provider === 'tencent') {
            $speech['api_key'] = '';
        } else {
            $speech['secret_id'] = '';
            if (self::isBaiduDirectApiKey((string)$speech['api_key'])) $speech['secret_key'] = '';
        }
        return $speech;
    }

    private function normalizeSecret(string $apiKey): string
    {
        $apiKey = trim($apiKey);
        if (strlen($apiKey) >= 2) {
            $first = $apiKey[0];
            $last = $apiKey[strlen($apiKey) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $apiKey = trim(substr($apiKey, 1, -1));
            }
        }
        return preg_replace('/^Bearer\s+/i', '', $apiKey) ?: $apiKey;
    }
}
