<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\provider;

use addon\hsx_ai\app\contract\AiProviderInterface;
use addon\hsx_ai\app\contract\AiStreamingProviderInterface;
use core\exception\CommonException;

final class OpenAiCompatibleProvider implements AiProviderInterface, AiStreamingProviderInterface
{
    public function models(array $provider): array
    {
        $result = $this->request($provider, 'GET', (string)$provider['models_path']);
        $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
        $models = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '') continue;
            $models[$id] = [
                'id' => $id,
                'name' => trim((string)($row['name'] ?? $id)) ?: $id,
                'owned_by' => trim((string)($row['owned_by'] ?? '')),
            ];
        }
        ksort($models, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($models);
    }

    public function test(array $provider): array
    {
        $models = $this->models($provider);
        return [
            'connected' => true,
            'model_count' => count($models),
            'models' => array_slice($models, 0, 10),
        ];
    }

    public function chat(array $provider, array $request): array
    {
        $payload = $this->chatPayload($request, false);
        $result = $this->request($provider, 'POST', (string)$provider['chat_path'], $payload);
        $choice = is_array($result['choices'][0] ?? null) ? $result['choices'][0] : [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        $content = $message['content'] ?? '';
        if (is_array($content)) {
            $content = implode("\n", array_values(array_filter(array_map(
                static fn($item): string => is_array($item) ? (string)($item['text'] ?? '') : (string)$item,
                $content
            ))));
        }
        $content = trim((string)$content);
        if ($content === '') {
            throw new CommonException('模型没有返回可用内容');
        }
        $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
        return [
            'provider_request_id' => trim((string)($result['id'] ?? '')),
            'model' => trim((string)($result['model'] ?? $request['model'])),
            'content' => $content,
            'reasoning_content' => trim((string)($message['reasoning_content'] ?? '')),
            'finish_reason' => trim((string)($choice['finish_reason'] ?? '')),
            'first_token_ms' => 0,
            'usage' => [
                'prompt_tokens' => max(0, (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0)),
                'completion_tokens' => max(0, (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0)),
                'total_tokens' => max(0, (int)($usage['total_tokens'] ?? 0)),
            ],
        ];
    }

    public function stream(array $provider, array $request, callable $emit): array
    {
        $payload = $this->chatPayload($request, true);
        $payload['stream_options'] = ['include_usage' => true];
        $started = microtime(true);
        $firstTokenMs = 0;
        $buffer = '';
        $eventData = [];
        $content = '';
        $reasoning = '';
        $finishReason = '';
        $providerRequestId = '';
        $model = (string)$request['model'];
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $streamError = '';

        $consumeEvent = function () use (&$eventData, &$content, &$reasoning, &$finishReason, &$providerRequestId, &$model, &$usage, &$firstTokenMs, &$streamError, $started, $emit): void {
            if ($eventData === []) return;
            $raw = trim(implode("\n", $eventData));
            $eventData = [];
            if ($raw === '' || $raw === '[DONE]') return;
            $chunk = json_decode($raw, true);
            if (!is_array($chunk)) return;
            if (!empty($chunk['error'])) {
                $streamError = is_array($chunk['error'])
                    ? (string)($chunk['error']['message'] ?? json_encode($chunk['error'], JSON_UNESCAPED_UNICODE))
                    : (string)$chunk['error'];
                return;
            }
            if ($providerRequestId === '') $providerRequestId = trim((string)($chunk['id'] ?? ''));
            if (!empty($chunk['model'])) $model = trim((string)$chunk['model']);
            $choice = is_array($chunk['choices'][0] ?? null) ? $chunk['choices'][0] : [];
            $delta = is_array($choice['delta'] ?? null) ? $choice['delta'] : [];
            $reasoningDelta = $this->contentText($delta['reasoning_content'] ?? '');
            $contentDelta = $this->contentText($delta['content'] ?? '');
            if (($reasoningDelta !== '' || $contentDelta !== '') && $firstTokenMs <= 0) {
                $firstTokenMs = max(1, (int)round((microtime(true) - $started) * 1000));
            }
            if ($reasoningDelta !== '') {
                $reasoning .= $reasoningDelta;
                $emit(['type' => 'reasoning', 'delta' => $reasoningDelta]);
            }
            if ($contentDelta !== '') {
                $content .= $contentDelta;
                $emit(['type' => 'content', 'delta' => $contentDelta]);
            }
            if (!empty($choice['finish_reason'])) $finishReason = trim((string)$choice['finish_reason']);
            if (is_array($chunk['usage'] ?? null)) $usage = $this->normalizeUsage($chunk['usage']);
        };

        $consume = function (string $bytes) use (&$buffer, &$eventData, $consumeEvent): void {
            $buffer .= str_replace("\r\n", "\n", $bytes);
            while (($position = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $position), "\r");
                $buffer = substr($buffer, $position + 1);
                if ($line === '') {
                    $consumeEvent();
                    continue;
                }
                if (str_starts_with($line, 'data:')) $eventData[] = ltrim(substr($line, 5));
            }
        };

        $transport = $this->transport($provider, 'POST', (string)$provider['chat_path'], $payload, $consume);
        if ($buffer !== '') {
            $line = trim($buffer);
            if (str_starts_with($line, 'data:')) $eventData[] = ltrim(substr($line, 5));
        }
        $consumeEvent();
        if ($streamError !== '') throw new CommonException('模型通道错误：' . $streamError);
        if (trim($content) === '') {
            $fallback = json_decode((string)$transport['body'], true);
            $message = is_array($fallback['choices'][0]['message'] ?? null) ? $fallback['choices'][0]['message'] : [];
            $content = $this->contentText($message['content'] ?? '');
            $reasoning = $this->contentText($message['reasoning_content'] ?? $reasoning);
        }
        if (trim($content) === '') throw new CommonException('模型没有返回可用内容');
        if ((int)$usage['total_tokens'] <= 0) {
            $usage['total_tokens'] = (int)$usage['prompt_tokens'] + (int)$usage['completion_tokens'];
        }
        return [
            'provider_request_id' => $providerRequestId,
            'model' => $model,
            'content' => $content,
            'reasoning_content' => $reasoning,
            'finish_reason' => $finishReason,
            'first_token_ms' => $firstTokenMs,
            'usage' => $usage,
        ];
    }

    private function chatPayload(array $request, bool $stream): array
    {
        $payload = [
            'model' => (string)$request['model'],
            'messages' => array_values((array)$request['messages']),
            'temperature' => (float)($request['temperature'] ?? 0.2),
            'stream' => $stream,
        ];
        $maxTokens = (int)($request['max_tokens'] ?? 0);
        if ($maxTokens > 0) $payload['max_tokens'] = $maxTokens;
        if (($request['response_mode'] ?? 'text') === 'json') $payload['response_format'] = ['type' => 'json_object'];
        foreach (['top_p', 'presence_penalty', 'frequency_penalty', 'seed'] as $field) {
            if (array_key_exists($field, $request)) $payload[$field] = $request[$field];
        }
        return $payload;
    }

    private function contentText($content): string
    {
        if (!is_array($content)) return (string)$content;
        return implode('', array_values(array_filter(array_map(
            static fn($item): string => is_array($item) ? (string)($item['text'] ?? '') : (string)$item,
            $content
        ))));
    }

    private function normalizeUsage(array $usage): array
    {
        return [
            'prompt_tokens' => max(0, (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0)),
            'completion_tokens' => max(0, (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0)),
            'total_tokens' => max(0, (int)($usage['total_tokens'] ?? 0)),
        ];
    }

    private function request(array $provider, string $method, string $path, array $json = []): array
    {
        $transport = $this->transport($provider, $method, $path, $json);
        $result = json_decode((string)$transport['body'], true);
        if (!is_array($result)) throw new CommonException('模型通道返回的不是有效 JSON');
        if (!empty($result['error'])) {
            $message = is_array($result['error'])
                ? (string)($result['error']['message'] ?? json_encode($result['error'], JSON_UNESCAPED_UNICODE))
                : (string)$result['error'];
            throw new CommonException('模型通道错误：' . $message);
        }
        return $result;
    }

    /** @return array{body:string,status:int,info:array} */
    private function transport(
        array $provider,
        string $method,
        string $path,
        array $json = [],
        ?callable $onBytes = null,
        int $attempt = 0,
        array $previousDiagnostics = []
    ): array
    {
        $baseUrl = rtrim(trim((string)($provider['base_url'] ?? '')), '/');
        $apiKey = $this->normalizeApiKey((string)($provider['api_key'] ?? ''));
        if ($baseUrl === '' || $apiKey === '') {
            throw new CommonException('模型通道缺少 Base URL 或 API Key');
        }
        $url = $baseUrl . '/' . ltrim($path, '/');
        if (!function_exists('curl_init')) {
            throw new CommonException('服务器未安装 CURL 扩展，无法请求模型通道');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new CommonException('模型通道请求初始化失败');
        }
        $headers = [
            'Accept: ' . ($onBytes === null ? 'application/json' : 'text/event-stream'),
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        foreach ((array)($provider['headers'] ?? []) as $name => $value) {
            $name = trim((string)$name);
            $value = trim((string)$value);
            if ($name !== '' && $value !== '') $headers[] = $name . ': ' . $value;
        }
        $timeout = max(5, min(180, (int)($provider['timeout'] ?? 60)));
        $connectTimeout = max(5, min(90, (int)($provider['connect_timeout'] ?? 30)));
        $effectiveConnectTimeout = $attempt === 0 ? min(15, $connectTimeout) : $connectTimeout;
        $rawBody = '';
        $options = [
            CURLOPT_RETURNTRANSFER => $onBytes === null,
            CURLOPT_CONNECTTIMEOUT => min($effectiveConnectTimeout, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        $ipVersion = (string)($provider['ip_version'] ?? 'ipv4');
        if (defined('CURLOPT_IPRESOLVE')) {
            if ($ipVersion === 'ipv4' && defined('CURL_IPRESOLVE_V4')) $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
            if ($ipVersion === 'ipv6' && defined('CURL_IPRESOLVE_V6')) $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V6;
        }
        if ((string)($provider['http_version'] ?? '1.1') === '1.1'
            && defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_1_1')) {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($onBytes !== null) {
            $options[CURLOPT_WRITEFUNCTION] = static function ($curl, string $bytes) use (&$rawBody, $onBytes): int {
                $rawBody .= $bytes;
                $onBytes($bytes);
                return strlen($bytes);
            };
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        $info = curl_getinfo($curl);
        $status = (int)($info['http_code'] ?? 0);
        curl_close($curl);
        if ($body === false || $error !== '') {
            $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
            $diagnostic = sprintf(
                'profile=%s/%s, curl=%d, host=%s, ip=%s, dns=%.3fs, connect=%.3fs, tls=%.3fs, total=%.3fs',
                (string)($provider['ip_version'] ?? 'ipv4'),
                (string)($provider['http_version'] ?? '1.1'),
                $errno,
                $host,
                (string)($info['primary_ip'] ?? '-'),
                (float)($info['namelookup_time'] ?? 0),
                (float)($info['connect_time'] ?? 0),
                (float)($info['appconnect_time'] ?? 0),
                (float)($info['total_time'] ?? 0)
            );
            $diagnostics = array_merge($previousDiagnostics, [$diagnostic]);
            if ($this->canRetryConnection($errno, $info, $url, $attempt)) {
                $fallback = $provider;
                $fallback['ip_version'] = (string)($provider['ip_version'] ?? 'ipv4') === 'ipv4' ? 'auto' : 'ipv4';
                $fallback['http_version'] = (string)($provider['http_version'] ?? '1.1') === '1.1' ? 'auto' : '1.1';
                return $this->transport($fallback, $method, $path, $json, $onBytes, $attempt + 1, $diagnostics);
            }
            $hint = $errno === 28 ? '；请检查服务器443出口、防火墙和代理，或调整连接超时/IP协议' : '';
            throw new CommonException('请求模型通道失败：' . ($error ?: '网络异常') . ' [' . implode(' | ', $diagnostics) . ']' . $hint);
        }
        $bodyText = $onBytes === null ? (string)$body : $rawBody;
        $result = json_decode($bodyText, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($result)
                ? (string)($result['error']['message'] ?? $result['message'] ?? '')
                : '';
            throw new CommonException('模型通道 HTTP ' . $status . ($message !== '' ? '：' . $message : ''));
        }
        return ['body' => $bodyText, 'status' => $status, 'info' => $info];
    }

    private function normalizeApiKey(string $apiKey): string
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

    private function canRetryConnection(int $errno, array $info, string $url, int $attempt): bool
    {
        if ($attempt > 0 || !in_array($errno, [6, 7, 28, 35, 52, 56], true)) return false;
        if ((int)($info['http_code'] ?? 0) > 0) return false;
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme === 'https') {
            // TLS 尚未完成时 HTTP 请求体一定没有发出，可以安全切换线路重试。
            return (float)($info['appconnect_time'] ?? 0) <= 0;
        }
        return (float)($info['connect_time'] ?? 0) <= 0;
    }
}
