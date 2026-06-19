<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core\ai;

use core\exception\CommonException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Log;

/**
 * AI 渠道适配层（云雾 / OpenAI 兼容）
 *
 * 只实现 OpenAI Chat Completions 一种形态：
 *  - chat()   -> POST {base}/chat/completions
 *  - models() -> GET  {base}/models
 *
 * 设计上保持低耦合：不依赖 ERP 业务表，构造时显式传入连接参数，
 * 便于将来被回收/商城复用，或抽成独立插件。
 */
class AiChannelService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct(string $baseUrl, string $apiKey, int $timeout = 60)
    {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
        $this->apiKey  = trim($apiKey);
        $this->timeout = $timeout > 0 ? $timeout : 60;
    }

    /**
     * 规范化 base_url：去尾斜杠，若未包含 /v1 则补上
     */
    protected function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '') {
            $url = 'https://yunwu.ai/v1';
        }
        // 已经带 /v1 或具体端点则原样使用，否则补 /v1
        if (!preg_match('#/v1($|/)#', $url)) {
            $url .= '/v1';
        }
        // 若用户填到了具体端点，回退到 /v1 根
        $url = preg_replace('#/chat/completions$#', '', $url);
        return $url;
    }

    protected function client(): Client
    {
        return new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout'  => $this->timeout,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    /**
     * 发起聊天补全（非流式）
     *
     * @param array  $messages OpenAI messages 数组
     * @param string $model    模型名
     * @param array  $options  可选参数（temperature 等）
     * @return array{content:string, usage:array, model:string, finish_reason:string, raw:array}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        if ($this->apiKey === '') {
            throw new CommonException('AI 密钥未配置，请先在「AI 配置」中填写');
        }
        if (empty($model)) {
            throw new CommonException('未指定模型');
        }

        $payload = array_merge([
            'model'    => $model,
            'messages' => array_values($messages),
            'stream'   => false,
        ], $options);

        try {
            $response = $this->client()->post('chat/completions', ['json' => $payload]);
            $body = (string)$response->getBody();
            $data = json_decode($body, true);
        } catch (GuzzleException $e) {
            Log::write('[hsx_ai] chat error: ' . $e->getMessage(), 'error');
            throw new CommonException('AI 服务请求失败：' . $this->friendlyError($e->getMessage()));
        }

        if (!is_array($data) || isset($data['error'])) {
            $msg = $data['error']['message'] ?? '返回数据异常';
            throw new CommonException('AI 服务返回错误：' . $msg);
        }

        $choice = $data['choices'][0] ?? [];
        return [
            'content'       => $choice['message']['content'] ?? '',
            'finish_reason' => $choice['finish_reason'] ?? '',
            'model'         => $data['model'] ?? $model,
            'usage'         => $data['usage'] ?? [],
            'raw'           => $data,
        ];
    }

    /**
     * 带工具的聊天（Function Calling 多轮循环）
     *
     * 流程：发起请求 → 若模型要求调用工具，则用 $executor 执行并把结果回灌 → 再问模型，
     * 直到模型给出最终自然语言回答或达到最大轮数。
     *
     * @param array    $messages 初始 messages（system+user）
     * @param string   $model    模型
     * @param array    $tools    OpenAI tools 定义数组
     * @param callable $executor function(string $name, array $args): array  执行工具并返回结果
     * @param array    $options  其它参数（temperature 等）
     * @param int      $maxRounds 最大工具轮数，防死循环
     * @return array{content:string, usage:array, model:string, rounds:int, tool_trace:array}
     */
    public function chatWithTools(array $messages, string $model, array $tools, callable $executor, array $options = [], int $maxRounds = 5): array
    {
        if ($this->apiKey === '') {
            throw new CommonException('AI 密钥未配置，请先在「AI 配置」中填写');
        }
        if (empty($model)) {
            throw new CommonException('未指定模型');
        }

        $messages = array_values($messages);
        $usageTotal = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $trace = [];
        $toolResults = [];

        for ($round = 1; $round <= $maxRounds; $round++) {
            $payload = array_merge([
                'model'    => $model,
                'messages' => $messages,
                'stream'   => false,
                'tools'    => $tools,
            ], $options);

            try {
                $response = $this->client()->post('chat/completions', ['json' => $payload]);
                $data = json_decode((string)$response->getBody(), true);
            } catch (GuzzleException $e) {
                Log::write('[hsx_ai] chatWithTools error: ' . $e->getMessage(), 'error');
                throw new CommonException('AI 服务请求失败：' . $this->friendlyError($e->getMessage()));
            }

            if (!is_array($data) || isset($data['error'])) {
                $msg = $data['error']['message'] ?? '返回数据异常';
                throw new CommonException('AI 服务返回错误：' . $msg);
            }

            // 累计用量
            foreach ($usageTotal as $k => $v) {
                $usageTotal[$k] = $v + (int)($data['usage'][$k] ?? 0);
            }

            $choice  = $data['choices'][0] ?? [];
            $message = $choice['message'] ?? [];
            $toolCalls = $message['tool_calls'] ?? [];

            // 没有工具调用 → 最终回答
            if (empty($toolCalls)) {
                return [
                    'content'      => $message['content'] ?? '',
                    'usage'        => $usageTotal,
                    'model'        => $data['model'] ?? $model,
                    'rounds'       => $round,
                    'tool_trace'   => $trace,
                    'tool_results' => $toolResults,
                ];
            }

            // 把助手这条带 tool_calls 的消息原样追加
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $message['content'] ?? '',
                'tool_calls' => $toolCalls,
            ];

            // 逐个执行工具，结果以 role:tool 回灌
            foreach ($toolCalls as $call) {
                $name = $call['function']['name'] ?? '';
                $argsRaw = $call['function']['arguments'] ?? '{}';
                $args = json_decode((string)$argsRaw, true);
                if (!is_array($args)) {
                    $args = [];
                }
                $result = $executor($name, $args);
                $trace[] = ['tool' => $name, 'args' => $args];
                $toolResults[] = ['tool' => $name, 'args' => $args, 'result' => $result];
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'] ?? '',
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        // 达到最大轮数仍未收敛
        return [
            'content'      => '（分析未能在限定步数内完成，请缩小问题范围后重试）',
            'usage'        => $usageTotal,
            'model'        => $model,
            'rounds'       => $maxRounds,
            'tool_trace'   => $trace,
            'tool_results' => $toolResults,
        ];
    }

    /**
     * 流式 + 工具的聊天（SSE）
     *
     * 工具轮：模型要求调用工具时，逐个执行（$executor 内部负责推进度状态），把结果回灌后再来一轮。
     * 回答轮：模型直接产出文本时，逐字通过 $emit('delta', 文本) 推给前端。
     *
     * @param callable $executor function(string $name, array $args): array  执行工具(并推状态)
     * @param callable $emit     function(string $event, mixed $data): void  推送 SSE 事件('delta' 等)
     * @return array{content:string, usage:array, tool_results:array}
     */
    public function streamWithTools(array $messages, string $model, array $tools, callable $executor, callable $emit, array $options = [], int $maxRounds = 6): array
    {
        if ($this->apiKey === '') {
            throw new CommonException('AI 密钥未配置');
        }
        if (empty($model)) {
            throw new CommonException('未指定模型');
        }

        $messages = array_values($messages);
        $usageTotal = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $toolResults = [];
        $finalContent = '';

        for ($round = 1; $round <= $maxRounds; $round++) {
            $payload = array_merge([
                'model'         => $model,
                'messages'      => $messages,
                'stream'        => true,
                'stream_options' => ['include_usage' => true],
            ], $options);
            if (!empty($tools)) {
                $payload['tools'] = $tools;
            }

            try {
                $response = $this->client()->post('chat/completions', ['json' => $payload, 'stream' => true]);
            } catch (GuzzleException $e) {
                Log::write('[hsx_ai] stream error: ' . $e->getMessage(), 'error');
                throw new CommonException('AI 服务请求失败：' . $this->friendlyError($e->getMessage()));
            }

            $contentRound = '';
            $tcMap = [];   // index => ['id'=>, 'name'=>, 'arguments'=>'']
            $done = false;
            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $buffer .= $body->read(2048);
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                    if ($line === '' || strpos($line, 'data:') !== 0) {
                        continue;
                    }
                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        $done = true;
                        break;
                    }
                    $json = json_decode($data, true);
                    if (!is_array($json)) {
                        continue;
                    }
                    if (isset($json['usage'])) {
                        foreach ($usageTotal as $k => $v) {
                            $usageTotal[$k] = $v + (int)($json['usage'][$k] ?? 0);
                        }
                    }
                    $delta = $json['choices'][0]['delta'] ?? [];
                    if (isset($delta['content']) && $delta['content'] !== '' && $delta['content'] !== null) {
                        $contentRound .= $delta['content'];
                        $emit('delta', $delta['content']);
                    }
                    if (!empty($delta['tool_calls'])) {
                        foreach ($delta['tool_calls'] as $tc) {
                            $idx = (int)($tc['index'] ?? 0);
                            if (!isset($tcMap[$idx])) {
                                $tcMap[$idx] = ['id' => '', 'name' => '', 'arguments' => ''];
                            }
                            if (!empty($tc['id'])) {
                                $tcMap[$idx]['id'] = $tc['id'];
                            }
                            if (!empty($tc['function']['name'])) {
                                $tcMap[$idx]['name'] = $tc['function']['name'];
                            }
                            if (isset($tc['function']['arguments'])) {
                                $tcMap[$idx]['arguments'] .= $tc['function']['arguments'];
                            }
                        }
                    }
                }
                if ($done) {
                    break;
                }
            }

            // 本轮要调用工具
            if (!empty($tcMap)) {
                ksort($tcMap);
                $toolCalls = [];
                foreach ($tcMap as $tc) {
                    $toolCalls[] = [
                        'id'       => $tc['id'],
                        'type'     => 'function',
                        'function' => ['name' => $tc['name'], 'arguments' => $tc['arguments'] ?: '{}'],
                    ];
                }
                $messages[] = ['role' => 'assistant', 'content' => $contentRound, 'tool_calls' => $toolCalls];

                foreach ($toolCalls as $call) {
                    $name = $call['function']['name'] ?? '';
                    $args = json_decode((string)$call['function']['arguments'], true);
                    if (!is_array($args)) {
                        $args = [];
                    }
                    $result = $executor($name, $args);
                    $toolResults[] = ['tool' => $name, 'args' => $args, 'result' => $result];
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $call['id'] ?? '',
                        'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }
                continue;
            }

            // 本轮是最终回答
            $finalContent = $contentRound;
            break;
        }

        return ['content' => $finalContent, 'usage' => $usageTotal, 'tool_results' => $toolResults];
    }

    /**
     * 拉取可用模型列表
     * @return array<int,string> 模型 id 列表
     */
    public function models(): array
    {
        if ($this->apiKey === '') {
            throw new CommonException('AI 密钥未配置');
        }
        try {
            $response = $this->client()->get('models');
            $data = json_decode((string)$response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::write('[hsx_ai] models error: ' . $e->getMessage(), 'error');
            throw new CommonException('拉取模型列表失败：' . $this->friendlyError($e->getMessage()));
        }

        $list = $data['data'] ?? [];
        $ids = [];
        foreach ($list as $item) {
            if (!empty($item['id'])) {
                $ids[] = (string)$item['id'];
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * 简单连通性测试：拿一句话试探
     * @return array{ok:bool, content:string}
     */
    public function ping(string $model): array
    {
        $res = $this->chat(
            [['role' => 'user', 'content' => '请只回复两个字：正常']],
            $model,
            ['temperature' => 0]
        );
        return ['ok' => $res['content'] !== '', 'content' => $res['content']];
    }

    protected function friendlyError(string $raw): string
    {
        if (stripos($raw, 'cURL error 28') !== false || stripos($raw, 'timed out') !== false) {
            return '请求超时，建议把 base_url 改为国内分站 https://yunwu.zeabur.app/v1 后重试';
        }
        if (stripos($raw, '401') !== false) {
            return '密钥无效或已过期（401）';
        }
        if (stripos($raw, '403') !== false) {
            return '无权限（403），请检查令牌分组与额度';
        }
        if (stripos($raw, '429') !== false) {
            return '请求过于频繁或额度不足（429）';
        }
        return mb_substr($raw, 0, 120);
    }
}
