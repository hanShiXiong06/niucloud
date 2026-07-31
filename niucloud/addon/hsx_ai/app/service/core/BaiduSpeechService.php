<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use core\exception\CommonException;
use think\facade\Cache;

final class BaiduSpeechService implements AiSpeechProviderInterface
{
    private const TOKEN_URL = 'https://aip.baidubce.com/oauth/2.0/token';
    private const STT_URL = 'https://vop.baidu.com/server_api';
    private const TTS_URL = 'https://tsn.baidu.com/text2audio';
    private array $config = [];
    private string $requestBody = '';

    public function configure(array $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function speechToText($file, int $siteId): array
    {
        $path = method_exists($file, 'getPathname') ? (string)$file->getPathname() : '';
        if ($path === '' || !is_file($path)) throw new CommonException('语音文件读取失败');
        $size = (int)filesize($path);
        if ($size <= 0 || $size > 10 * 1024 * 1024) throw new CommonException('语音文件不能为空且不能超过 10MB');
        $extension = strtolower((string)(method_exists($file, 'extension') ? $file->extension() : pathinfo($path, PATHINFO_EXTENSION)));
        $format = in_array($extension, ['pcm', 'wav', 'amr', 'm4a'], true) ? $extension : 'm4a';
        $audio = file_get_contents($path);
        if ($audio === false) throw new CommonException('语音文件读取失败');

        $data = [
            'format' => $format,
            'rate' => 16000,
            'channel' => 1,
            'cuid' => 'hsx_ai_site_' . $siteId,
            'dev_pid' => 1537,
            'speech' => base64_encode($audio),
            'len' => $size,
        ];
        if (!$this->usesDirectApiKey()) $data['token'] = $this->token($siteId);
        $response = $this->jsonRequest(self::STT_URL, $data, $this->usesDirectApiKey());
        if ((int)($response['err_no'] ?? -1) !== 0) {
            throw new CommonException('语音识别失败：' . (string)($response['err_msg'] ?? '未知错误'));
        }
        $text = trim(implode('', (array)($response['result'] ?? [])));
        if ($text === '') throw new CommonException('没有识别到有效语音，请靠近麦克风后重试');
        return ['text' => $text, 'provider' => 'baidu'];
    }

    public function textToSpeech(string $text, int $siteId): array
    {
        $text = trim(strip_tags($text));
        if ($text === '') throw new CommonException('朗读内容不能为空');
        if (mb_strlen($text) > 300) $text = mb_substr($text, 0, 300) . '。';
        $data = [
            'tex' => $text,
            'cuid' => 'hsx_ai_site_' . $siteId,
            'ctp' => 1,
            'lan' => 'zh',
            'spd' => (int)$this->config['speed'],
            'pit' => (int)$this->config['pitch'],
            'vol' => (int)$this->config['volume'],
            'per' => (int)$this->config['voice'],
            'aue' => 3,
        ];
        if (!$this->usesDirectApiKey()) $data['tok'] = $this->token($siteId);
        $this->requestBody = http_build_query($data);
        [$body, $contentType] = $this->request(
            self::TTS_URL,
            ['Content-Type: application/x-www-form-urlencoded'],
            $this->usesDirectApiKey()
        );
        if (stripos($contentType, 'audio/') === false) {
            $error = json_decode($body, true);
            throw new CommonException('语音合成失败：' . (string)($error['err_msg'] ?? $error['err_detail'] ?? '服务未返回音频'));
        }
        return [
            'audio_base64' => base64_encode($body),
            'mime_type' => 'audio/mpeg',
            'provider' => 'baidu',
        ];
    }

    public function test(int $siteId): array
    {
        return array_merge($this->textToSpeech('语音服务连接测试成功。', $siteId), [
            'message' => '百度智能云语音合成测试成功',
        ]);
    }

    private function usesDirectApiKey(): bool
    {
        return AiConfigService::isBaiduDirectApiKey((string)($this->config['api_key'] ?? ''));
    }

    private function token(int $siteId): string
    {
        $cacheKey = 'hsx_ai:baidu_speech_token:' . $siteId . ':' . substr(hash('sha256', (string)$this->config['api_key']), 0, 12);
        $cached = (string)Cache::get($cacheKey, '');
        if ($cached !== '') return $cached;
        $response = $this->oauthTokenResponse();
        $token = trim((string)($response['access_token'] ?? ''));
        if ($token === '') throw new CommonException('获取百度语音令牌失败：' . (string)($response['error_description'] ?? '请检查密钥'));
        Cache::set($cacheKey, $token, max(300, (int)($response['expires_in'] ?? 2592000) - 300));
        return $token;
    }

    private function oauthTokenResponse(): array
    {
        $fields = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => (string)$this->config['api_key'],
            'client_secret' => (string)$this->config['secret_key'],
        ]);
        $curl = curl_init(self::TOKEN_URL);
        if ($curl === false) throw new CommonException('无法初始化百度语音鉴权请求');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($curl);
        if ($body === false) {
            $message = curl_error($curl);
            curl_close($curl);
            throw new CommonException('连接百度语音鉴权服务失败：' . $message);
        }
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $response = json_decode((string)$body, true);
        if (!is_array($response)) throw new CommonException('百度语音鉴权返回了无法识别的数据');
        if ($status < 200 || $status >= 300) {
            throw new CommonException('百度语音鉴权失败：' . (string)($response['error_description'] ?? $response['error'] ?? ('HTTP ' . $status)));
        }
        return $response;
    }

    private function jsonRequest(string $url, array $data, bool $authorize = false): array
    {
        $this->requestBody = (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$body] = $this->request($url, ['Content-Type: application/json'], $authorize);
        $response = json_decode($body, true);
        if (!is_array($response)) throw new CommonException('语音服务返回了无法识别的数据');
        return $response;
    }

    /** @return array{0:string,1:string} */
    private function request(string $url, array $headers, bool $authorize = false): array
    {
        if ($authorize) $headers[] = 'Authorization: Bearer ' . (string)$this->config['api_key'];
        $body = $this->requestBody;
        $this->requestBody = '';
        $curl = curl_init($url);
        if ($curl === false) throw new CommonException('无法初始化语音服务请求');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: application/json, audio/*']),
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        if ($response === false) {
            $message = curl_error($curl);
            curl_close($curl);
            throw new CommonException('连接语音服务失败：' . $message);
        }
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);
        $payload = substr((string)$response, $headerSize);
        if ($status < 200 || $status >= 300) {
            $error = json_decode($payload, true);
            $detail = is_array($error) ? (string)($error['error_msg'] ?? $error['err_msg'] ?? $error['message'] ?? '') : '';
            throw new CommonException('百度语音服务 HTTP ' . $status . ($detail !== '' ? '：' . $detail : ''));
        }
        return [$payload, $contentType];
    }
}
