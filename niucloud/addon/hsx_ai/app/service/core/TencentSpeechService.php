<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use core\exception\CommonException;

final class TencentSpeechService implements AiSpeechProviderInterface
{
    private array $config = [];

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
        if ($size <= 0 || $size > 3 * 1024 * 1024) throw new CommonException('腾讯云短语音文件不能为空且不能超过 3MB');
        $extension = strtolower((string)(method_exists($file, 'extension') ? $file->extension() : pathinfo($path, PATHINFO_EXTENSION)));
        $format = in_array($extension, ['wav', 'pcm', 'ogg-opus', 'speex', 'silk', 'mp3', 'm4a', 'aac', 'amr'], true) ? $extension : 'm4a';
        $audio = file_get_contents($path);
        if ($audio === false) throw new CommonException('语音文件读取失败');
        $response = $this->cloudRequest('asr', 'asr.tencentcloudapi.com', 'SentenceRecognition', '2019-06-14', [
            'EngSerViceType' => (string)($this->config['stt_engine'] ?? '16k_zh'),
            'SourceType' => 1,
            'VoiceFormat' => $format,
            'Data' => base64_encode($audio),
            'DataLen' => $size,
        ]);
        $text = trim((string)($response['Result'] ?? ''));
        if ($text === '') throw new CommonException('没有识别到有效语音，请靠近麦克风后重试');
        return ['text' => $text, 'provider' => 'tencent'];
    }

    public function textToSpeech(string $text, int $siteId): array
    {
        $text = trim(strip_tags($text));
        if ($text === '') throw new CommonException('朗读内容不能为空');
        if (mb_strlen($text) > 150) $text = mb_substr($text, 0, 150);
        $response = $this->cloudRequest('tts', 'tts.tencentcloudapi.com', 'TextToVoice', '2019-08-23', [
            'Text' => $text,
            'SessionId' => 'hsx_ai_' . $siteId . '_' . bin2hex(random_bytes(8)),
            'Volume' => (float)($this->config['tencent_volume'] ?? 0),
            'Speed' => (float)($this->config['tencent_speed'] ?? 0),
            'ProjectId' => 0,
            'ModelType' => 1,
            'VoiceType' => (int)($this->config['tencent_voice'] ?? 1001),
            'PrimaryLanguage' => 1,
            'SampleRate' => 16000,
            'Codec' => 'mp3',
        ]);
        $audio = trim((string)($response['Audio'] ?? ''));
        if ($audio === '') throw new CommonException('腾讯云语音合成未返回音频');
        return ['audio_base64' => $audio, 'mime_type' => 'audio/mpeg', 'provider' => 'tencent'];
    }

    public function test(int $siteId): array
    {
        return array_merge($this->textToSpeech('语音服务连接测试成功。', $siteId), [
            'message' => '腾讯云语音合成测试成功',
        ]);
    }

    private function cloudRequest(string $service, string $host, string $action, string $version, array $data): array
    {
        $contentType = 'application/json; charset=utf-8';
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) throw new CommonException('腾讯云语音请求编码失败');
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);
        $canonicalHeaders = "content-type:{$contentType}\nhost:{$host}\nx-tc-action:" . strtolower($action) . "\n";
        $signedHeaders = 'content-type;host;x-tc-action';
        $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n" . hash('sha256', $payload);
        $scope = $date . '/' . $service . '/tc3_request';
        $stringToSign = "TC3-HMAC-SHA256\n{$timestamp}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $secretKey = (string)$this->config['secret_key'];
        $secretDate = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);
        $authorization = 'TC3-HMAC-SHA256 Credential=' . (string)$this->config['secret_id'] . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
        $headers = [
            'Authorization: ' . $authorization,
            'Content-Type: ' . $contentType,
            'Host: ' . $host,
            'X-TC-Action: ' . $action,
            'X-TC-Timestamp: ' . $timestamp,
            'X-TC-Version: ' . $version,
        ];
        $region = trim((string)($this->config['region'] ?? ''));
        if ($region !== '') $headers[] = 'X-TC-Region: ' . $region;

        $curl = curl_init('https://' . $host . '/');
        if ($curl === false) throw new CommonException('无法初始化腾讯云语音请求');
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($curl);
        if ($body === false) {
            $message = curl_error($curl);
            curl_close($curl);
            throw new CommonException('连接腾讯云语音服务失败：' . $message);
        }
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) throw new CommonException('腾讯云语音服务返回了无法识别的数据');
        $response = (array)($decoded['Response'] ?? []);
        if (!empty($response['Error'])) {
            throw new CommonException('腾讯云语音错误：' . (string)($response['Error']['Message'] ?? $response['Error']['Code'] ?? '未知错误'));
        }
        if ($status < 200 || $status >= 300) throw new CommonException('腾讯云语音 HTTP ' . $status);
        return $response;
    }
}
