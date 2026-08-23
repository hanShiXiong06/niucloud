<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use addon\hsx_ai\app\contract\AiStreamingProviderInterface;
use addon\hsx_ai\app\model\AiCallLog;
use core\exception\CommonException;

final class AiGatewayService
{
    public function execute(int $siteId, array $request, bool $allowModelOverride = false, bool $allowDisabled = false): array
    {
        $context = $this->requestContext($siteId, $request, $allowModelOverride, $allowDisabled, false);
        $config = $context['config'];
        $sceneKey = $context['scene_key'];
        $providerId = $context['provider_id'];
        $provider = $context['provider'];
        $model = $context['model'];
        $providerRequest = $context['provider_request'];
        $responseMode = $context['response_mode'];
        $requestId = $context['request_id'];
        $promptHash = $context['prompt_hash'];
        $existing = AiCallLog::where([['site_id', '=', $siteId], ['request_id', '=', $requestId]])->findOrEmpty();
        $duplicate = $this->duplicateResult($existing, $promptHash);
        if ($duplicate !== null) return $duplicate;

        $now = time();
        $source = $context['source'];
        $operator = $context['operator'];
        $meta = $context['meta'];
        $log = $existing;
        if ($log->isEmpty()) {
            try {
                $log = AiCallLog::create([
                    'site_id' => $siteId,
                    'request_id' => $requestId,
                    'scene_key' => $sceneKey,
                    'source_plugin' => trim((string)($source['plugin'] ?? '')),
                    'source_type' => trim((string)($source['type'] ?? '')),
                    'source_id' => trim((string)($source['id'] ?? '')),
                    'operator_uid' => max(0, (int)($operator['id'] ?? 0)),
                    'operator_name' => trim((string)($operator['name'] ?? '')),
                    'provider_id' => $providerId,
                    'model' => $model,
                    'status' => 'pending',
                    'prompt_hash' => $promptHash,
                    'request_meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'started_at' => $now,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            } catch (\Throwable $e) {
                // 唯一索引可能被同一 request_id 的并发请求抢先写入。
                $log = AiCallLog::where([['site_id', '=', $siteId], ['request_id', '=', $requestId]])->findOrEmpty();
                if ($log->isEmpty()) throw $e;
                $duplicate = $this->duplicateResult($log, $promptHash);
                if ($duplicate !== null) return $duplicate;
            }
        }

        $started = microtime(true);
        $log->save(['status' => 'processing', 'started_at' => $now, 'update_at' => $now]);
        try {
            $result = (new AiProviderRegistry())->resolve((string)$provider['driver'])->chat($provider, $providerRequest);
            $usage = (array)$result['usage'];
            if ((int)$usage['total_tokens'] <= 0) {
                $usage['total_tokens'] = (int)$usage['prompt_tokens'] + (int)$usage['completion_tokens'];
            }
            $normalized = [
                'request_id' => $requestId,
                'scene_key' => $sceneKey,
                'provider_id' => $providerId,
                'model' => (string)$result['model'],
                'content' => (string)$result['content'],
                'tool_calls' => array_values((array)($result['tool_calls'] ?? [])),
                'data' => $responseMode === 'json' && empty($result['tool_calls'])
                    ? $this->jsonContent((string)$result['content'])
                    : null,
                'finish_reason' => (string)$result['finish_reason'],
                'usage' => $usage,
                'latency_ms' => max(0, (int)round((microtime(true) - $started) * 1000)),
                'status' => 'success',
            ];
            $storedResponse = $normalized;
            if (empty($config['log_content'])) {
                unset($storedResponse['content'], $storedResponse['data']);
            }
            $log->save([
                'status' => 'success',
                'response_meta_json' => json_encode($storedResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'prompt_tokens' => (int)$usage['prompt_tokens'],
                'completion_tokens' => (int)$usage['completion_tokens'],
                'total_tokens' => (int)$usage['total_tokens'],
                'latency_ms' => (int)$normalized['latency_ms'],
                'error_message' => '',
                'finished_at' => time(),
                'update_at' => time(),
            ]);
            $normalized['log_id'] = (int)$log->id;
            return $normalized;
        } catch (\Throwable $e) {
            $latency = max(0, (int)round((microtime(true) - $started) * 1000));
            $log->save([
                'status' => 'failed',
                'latency_ms' => $latency,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => time(),
                'update_at' => time(),
            ]);
            throw $e;
        }
    }

    /**
     * 面向交互界面的流式调用。业务插件的自动化决策仍应使用 execute，等待完整结果校验成功后再落业务数据。
     */
    public function stream(
        int $siteId,
        array $request,
        callable $emit,
        bool $allowModelOverride = false,
        bool $allowDisabled = false
    ): array {
        $context = $this->requestContext($siteId, $request, $allowModelOverride, $allowDisabled, true);
        $existing = AiCallLog::where([
            ['site_id', '=', $siteId],
            ['request_id', '=', $context['request_id']],
        ])->findOrEmpty();
        $duplicate = $this->duplicateResult($existing, $context['prompt_hash']);
        if ($duplicate !== null) {
            if ((string)($duplicate['content'] ?? '') !== '') {
                $emit(['type' => 'content', 'delta' => (string)$duplicate['content']]);
            }
            return $duplicate;
        }

        $providerAdapter = (new AiProviderRegistry())->resolve((string)$context['provider']['driver']);
        if (!$providerAdapter instanceof AiStreamingProviderInterface) {
            throw new CommonException('当前模型通道不支持流式输出');
        }

        $now = time();
        $log = $existing;
        if ($log->isEmpty()) {
            try {
                $log = AiCallLog::create([
                    'site_id' => $siteId,
                    'request_id' => $context['request_id'],
                    'scene_key' => $context['scene_key'],
                    'source_plugin' => trim((string)($context['source']['plugin'] ?? '')),
                    'source_type' => trim((string)($context['source']['type'] ?? '')),
                    'source_id' => trim((string)($context['source']['id'] ?? '')),
                    'operator_uid' => max(0, (int)($context['operator']['id'] ?? 0)),
                    'operator_name' => trim((string)($context['operator']['name'] ?? '')),
                    'provider_id' => $context['provider_id'],
                    'model' => $context['model'],
                    'status' => 'pending',
                    'prompt_hash' => $context['prompt_hash'],
                    'request_meta_json' => json_encode($context['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'started_at' => $now,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            } catch (\Throwable $e) {
                $log = AiCallLog::where([
                    ['site_id', '=', $siteId],
                    ['request_id', '=', $context['request_id']],
                ])->findOrEmpty();
                if ($log->isEmpty()) throw $e;
                $duplicate = $this->duplicateResult($log, $context['prompt_hash']);
                if ($duplicate !== null) return $duplicate;
            }
        }

        $started = microtime(true);
        $log->save(['status' => 'processing', 'started_at' => $now, 'update_at' => $now]);
        $emit([
            'type' => 'meta',
            'request_id' => $context['request_id'],
            'scene_key' => $context['scene_key'],
            'provider_id' => $context['provider_id'],
            'model' => $context['model'],
        ]);
        try {
            $result = $providerAdapter->stream($context['provider'], $context['provider_request'], $emit);
            $usage = (array)($result['usage'] ?? []);
            $usage += ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
            if ((int)$usage['total_tokens'] <= 0) {
                $usage['total_tokens'] = (int)$usage['prompt_tokens'] + (int)$usage['completion_tokens'];
            }
            $normalized = [
                'request_id' => $context['request_id'],
                'scene_key' => $context['scene_key'],
                'provider_id' => $context['provider_id'],
                'model' => (string)($result['model'] ?? $context['model']),
                'content' => (string)($result['content'] ?? ''),
                'reasoning_content' => (string)($result['reasoning_content'] ?? ''),
                'tool_calls' => array_values((array)($result['tool_calls'] ?? [])),
                'data' => $context['response_mode'] === 'json' && empty($result['tool_calls'])
                    ? $this->jsonContent((string)($result['content'] ?? ''))
                    : null,
                'finish_reason' => (string)($result['finish_reason'] ?? ''),
                'usage' => $usage,
                'first_token_ms' => max(0, (int)($result['first_token_ms'] ?? 0)),
                'latency_ms' => max(0, (int)round((microtime(true) - $started) * 1000)),
                'status' => 'success',
            ];
            $storedResponse = $normalized;
            if (empty($context['config']['log_content'])) {
                unset($storedResponse['content'], $storedResponse['reasoning_content'], $storedResponse['data']);
            }
            $log->save([
                'status' => 'success',
                'response_meta_json' => json_encode($storedResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'prompt_tokens' => (int)$usage['prompt_tokens'],
                'completion_tokens' => (int)$usage['completion_tokens'],
                'total_tokens' => (int)$usage['total_tokens'],
                'latency_ms' => (int)$normalized['latency_ms'],
                'error_message' => '',
                'finished_at' => time(),
                'update_at' => time(),
            ]);
            $normalized['log_id'] = (int)$log->id;
            return $normalized;
        } catch (\Throwable $e) {
            $log->save([
                'status' => 'failed',
                'latency_ms' => max(0, (int)round((microtime(true) - $started) * 1000)),
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => time(),
                'update_at' => time(),
            ]);
            throw $e;
        }
    }

    private function requestContext(int $siteId, array $request, bool $allowModelOverride, bool $allowDisabled, bool $stream): array
    {
        if ($siteId <= 0) throw new CommonException('AI请求缺少有效站点');
        $config = (new AiConfigService())->get($siteId);
        if (empty($config['enabled']) && !$allowDisabled) throw new CommonException('当前站点尚未启用 AI 能力');
        $sceneKey = trim((string)($request['scene_key'] ?? 'general')) ?: 'general';
        $scene = $this->scene($config, $sceneKey);
        $providerId = (string)($scene['provider_id'] ?: $config['default_provider_id']);
        if ($allowModelOverride && trim((string)($request['provider_id'] ?? '')) !== '') $providerId = trim((string)$request['provider_id']);
        $provider = $this->provider($config, $providerId);
        if (empty($provider['enabled'])) throw new CommonException('当前AI场景使用的模型通道已停用');
        if ($allowModelOverride && isset($request['timeout'])) {
            $provider['timeout'] = max(5, min(180, (int)$request['timeout']));
        }
        $model = (string)($scene['model'] ?: $provider['default_model'] ?: $config['default_model']);
        if ($allowModelOverride && trim((string)($request['model'] ?? '')) !== '') $model = trim((string)$request['model']);
        if ($model === '') throw new CommonException('当前AI场景尚未选择模型');
        $this->assertModelAvailable($provider, $model);
        $messages = $this->messages($request, (string)$scene['system_prompt']);
        if (!empty($config['redact_sensitive'])) {
            foreach ($messages as &$message) $message['content'] = $this->redact((string)$message['content']);
            unset($message);
        }
        $responseMode = (string)($request['response_mode'] ?? $scene['response_mode']);
        if (!in_array($responseMode, ['text', 'json'], true)) $responseMode = 'text';
        $providerRequest = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $allowModelOverride && isset($request['temperature'])
                ? max(0, min(2, (float)$request['temperature']))
                : (float)$scene['temperature'],
            'max_tokens' => $allowModelOverride && isset($request['max_tokens'])
                ? max(0, (int)$request['max_tokens'])
                : (int)$scene['max_tokens'],
            'response_mode' => $responseMode,
        ];
        if (!empty($request['_agent_internal']) && !empty($request['tools']) && is_array($request['tools'])) {
            $providerRequest['tools'] = array_values($request['tools']);
            $providerRequest['tool_choice'] = $request['tool_choice'] ?? 'auto';
        }
        $requestId = trim((string)($request['request_id'] ?? '')) ?: $this->requestId();
        if (strlen($requestId) > 100) throw new CommonException('AI请求ID不能超过100个字符');
        $promptHash = hash('sha256', json_encode([
            'scene_key' => $sceneKey,
            'provider_id' => $providerId,
            'model' => $model,
            'request' => $providerRequest,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $meta = [
            'message_count' => count($messages),
            'content_length' => array_sum(array_map(static fn(array $message): int => mb_strlen((string)$message['content']), $messages)),
            'response_mode' => $responseMode,
            'stream' => $stream,
        ];
        if (!empty($config['log_content'])) $meta['messages'] = $messages;
        return [
            'config' => $config,
            'scene_key' => $sceneKey,
            'provider_id' => $providerId,
            'provider' => $provider,
            'model' => $model,
            'provider_request' => $providerRequest,
            'response_mode' => $responseMode,
            'request_id' => $requestId,
            'prompt_hash' => $promptHash,
            'source' => is_array($request['source'] ?? null) ? $request['source'] : [],
            'operator' => is_array($request['operator'] ?? null) ? $request['operator'] : [],
            'meta' => $meta,
        ];
    }

    private function duplicateResult(AiCallLog $log, string $promptHash): ?array
    {
        if ($log->isEmpty()) return null;
        if ((string)$log->prompt_hash !== $promptHash) {
            throw new CommonException('相同AI请求ID对应了不同内容');
        }
        if (in_array((string)$log->status, ['pending', 'processing'], true)) {
            throw new CommonException('该AI请求正在处理中');
        }
        if ((string)$log->status !== 'success') return null;

        $stored = json_decode((string)$log->response_meta_json, true);
        if (!is_array($stored)) $stored = [];
        return array_merge([
            'request_id' => (string)$log->request_id,
            'scene_key' => (string)$log->scene_key,
            'provider_id' => (string)$log->provider_id,
            'model' => (string)$log->model,
            'content' => '',
            'data' => null,
            'finish_reason' => '',
            'tool_calls' => [],
            'usage' => [
                'prompt_tokens' => (int)$log->prompt_tokens,
                'completion_tokens' => (int)$log->completion_tokens,
                'total_tokens' => (int)$log->total_tokens,
            ],
            'latency_ms' => (int)$log->latency_ms,
        ], $stored, [
            'status' => 'duplicate',
            'log_id' => (int)$log->id,
        ]);
    }

    private function scene(array $config, string $sceneKey): array
    {
        foreach ($config['scenes'] as $scene) {
            if ((string)$scene['key'] !== $sceneKey) continue;
            if (empty($scene['enabled'])) throw new CommonException('AI场景已停用：' . $sceneKey);
            return $scene;
        }
        throw new CommonException('AI场景未注册：' . $sceneKey);
    }

    private function provider(array $config, string $providerId): array
    {
        foreach ($config['providers'] as $provider) {
            if ((string)$provider['id'] === $providerId) return $provider;
        }
        throw new CommonException('AI场景关联的模型通道不存在');
    }

    private function assertModelAvailable(array $provider, string $model): void
    {
        if ((array)$provider['models'] === []) return;
        foreach ($provider['models'] as $row) {
            if ((string)$row['id'] === $model && !empty($row['enabled'])) return;
        }
        throw new CommonException('模型不存在或已停用：' . $model);
    }

    private function messages(array $request, string $systemPrompt): array
    {
        $messages = [];
        $allowToolMessages = !empty($request['_agent_internal']);
        if ($systemPrompt !== '') $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        foreach (array_values(array_filter((array)($request['messages'] ?? []), 'is_array')) as $message) {
            $role = trim((string)($message['role'] ?? 'user'));
            $allowedRoles = $allowToolMessages
                ? ['system', 'user', 'assistant', 'tool']
                : ['system', 'user', 'assistant'];
            if (!in_array($role, $allowedRoles, true)) continue;
            $content = trim((string)($message['content'] ?? ''));
            if ($role === 'assistant' && $allowToolMessages && !empty($message['tool_calls'])) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                    'tool_calls' => array_values((array)$message['tool_calls']),
                ];
                continue;
            }
            if ($role === 'tool' && $allowToolMessages && $content !== '') {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => mb_substr(trim((string)($message['tool_call_id'] ?? '')), 0, 120),
                    'name' => mb_substr(trim((string)($message['name'] ?? '')), 0, 64),
                    'content' => $content,
                ];
                continue;
            }
            if ($content !== '') $messages[] = ['role' => $role, 'content' => $content];
        }
        $prompt = trim((string)($request['prompt'] ?? ''));
        if ($prompt !== '') $messages[] = ['role' => 'user', 'content' => $prompt];
        if ($messages === [] || count(array_filter($messages, static fn(array $message): bool => $message['role'] === 'user')) === 0) {
            throw new CommonException('AI请求缺少用户消息');
        }
        if (count($messages) > 100) throw new CommonException('单次AI请求最多允许100条消息');
        $length = array_sum(array_map(static fn(array $message): int => mb_strlen((string)($message['content'] ?? '')), $messages));
        if ($length > 200000) throw new CommonException('AI请求内容过长');
        return $messages;
    }

    private function jsonContent(string $content): array
    {
        $content = trim($content);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $content, $matches)) {
            $content = trim((string)$matches[1]);
        }
        $data = json_decode($content, true);
        if (!is_array($data)) throw new CommonException('模型未按要求返回有效JSON');
        return $data;
    }

    private function redact(string $content): string
    {
        $content = preg_replace('/(?<!\d)1[3-9]\d{9}(?!\d)/', '[手机号已脱敏]', $content) ?? $content;
        $content = preg_replace('/(?<!\d)\d{17}[\dXx](?!\d)/', '[身份证已脱敏]', $content) ?? $content;
        $content = preg_replace('/(?<!\d)\d{16,19}(?!\d)/', '[账户号码已脱敏]', $content) ?? $content;
        return $content;
    }

    private function requestId(): string
    {
        return 'AI' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
    }
}
