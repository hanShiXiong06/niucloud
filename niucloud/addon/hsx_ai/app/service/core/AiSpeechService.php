<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use core\exception\CommonException;

final class AiSpeechService
{
    public function speechToText(int $siteId, $file): array
    {
        $config = $this->savedConfig($siteId, 'stt_enabled');
        return $this->provider((string)$config['provider'])->configure($config)->speechToText($file, $siteId);
    }

    public function textToSpeech(int $siteId, string $text): array
    {
        $config = $this->savedConfig($siteId, 'tts_enabled');
        return $this->provider((string)$config['provider'])->configure($config)->textToSpeech($text, $siteId);
    }

    public function test(int $siteId, array $input): array
    {
        $config = (new AiConfigService())->resolveSpeechInput($siteId, $input);
        $this->validateCredentials($config);
        $startedAt = microtime(true);
        return array_merge(
            $this->provider((string)$config['provider'])->configure($config)->test($siteId),
            ['latency_ms' => (int)round((microtime(true) - $startedAt) * 1000)]
        );
    }

    private function savedConfig(int $siteId, string $capability): array
    {
        $config = (array)((new AiConfigService())->get($siteId)['speech'] ?? []);
        if (empty($config['enabled']) || empty($config[$capability])) {
            throw new CommonException('本站暂未启用该语音能力');
        }
        $this->validateCredentials($config);
        return $config;
    }

    private function validateCredentials(array $config): void
    {
        if ((string)($config['provider'] ?? '') === 'tencent') {
            if (trim((string)($config['secret_id'] ?? '')) === '' || trim((string)($config['secret_key'] ?? '')) === '') {
                throw new CommonException('请填写腾讯云 SecretId 和 SecretKey');
            }
            return;
        }
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '' || (!AiConfigService::isBaiduDirectApiKey($apiKey) && trim((string)($config['secret_key'] ?? '')) === '')) {
            throw new CommonException('请填写百度智能云 API Key 和 Secret Key');
        }
    }

    private function provider(string $provider): AiSpeechProviderInterface
    {
        if ($provider === 'baidu') return new BaiduSpeechService();
        if ($provider === 'tencent') return new TencentSpeechService();
        throw new CommonException('当前语音服务商暂不支持');
    }
}
