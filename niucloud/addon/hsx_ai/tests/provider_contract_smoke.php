<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/contract/AiProviderInterface.php';
require_once dirname(__DIR__) . '/app/contract/AiStreamingProviderInterface.php';
require_once dirname(__DIR__) . '/app/service/core/AiToolCallProtocolService.php';
require_once dirname(__DIR__) . '/app/provider/OpenAiCompatibleProvider.php';

use addon\hsx_ai\app\provider\OpenAiCompatibleProvider;

$baseUrl = trim((string)($argv[1] ?? 'http://127.0.0.1:18991'));
$providerConfig = [
    'base_url' => $baseUrl,
    'api_key' => 'Bearer test-token',
    'models_path' => '/v1/models',
    'chat_path' => '/v1/chat/completions',
    'timeout' => 5,
];
$provider = new OpenAiCompatibleProvider();
$models = $provider->models($providerConfig);
if (array_column($models, 'id') !== ['mock-json-model', 'mock-model']) {
    throw new RuntimeException('模型列表规范化失败');
}

$result = $provider->chat($providerConfig, [
    'model' => 'mock-json-model',
    'messages' => [['role' => 'user', 'content' => 'return json']],
    'temperature' => 0.2,
    'max_tokens' => 100,
    'response_mode' => 'json',
]);
if (($result['content'] ?? '') !== '{"ok":true}') {
    throw new RuntimeException('JSON模式响应解析失败');
}
if ((int)($result['usage']['total_tokens'] ?? 0) !== 12) {
    throw new RuntimeException('Token用量规范化失败');
}
$toolRequest = [
    'model' => 'mock-model',
    'messages' => [['role' => 'user', 'content' => 'use tool']],
    'tools' => [[
        'type' => 'function',
        'function' => [
            'name' => 'mock_search',
            'description' => 'search',
            'parameters' => ['type' => 'object', 'properties' => []],
        ],
    ]],
    'tool_choice' => 'auto',
];
$toolResult = $provider->chat($providerConfig, $toolRequest);
if (($toolResult['tool_calls'][0]['function']['name'] ?? '') !== 'mock_search') {
    throw new RuntimeException('非流式工具调用解析失败');
}
$events = [];
$streamResult = $provider->stream($providerConfig, [
    'model' => 'mock-model',
    'messages' => [['role' => 'user', 'content' => 'stream response']],
    'temperature' => 0.2,
    'max_tokens' => 100,
    'response_mode' => 'text',
], static function (array $event) use (&$events): void {
    $events[] = $event;
});
if (($streamResult['content'] ?? '') !== 'mock response') throw new RuntimeException('流式正文合并失败');
if (($streamResult['reasoning_content'] ?? '') !== 'thinking') throw new RuntimeException('流式推理内容合并失败');
if (array_column($events, 'type') !== ['reasoning', 'content', 'content']) throw new RuntimeException('流式事件顺序错误');
if ((int)($streamResult['usage']['total_tokens'] ?? 0) !== 12) throw new RuntimeException('流式Token用量规范化失败');
$toolEvents = [];
$streamToolResult = $provider->stream($providerConfig, $toolRequest, static function (array $event) use (&$toolEvents): void {
    $toolEvents[] = $event;
});
if (($streamToolResult['tool_calls'][0]['function']['name'] ?? '') !== 'mock_search') {
    throw new RuntimeException('流式工具调用合并失败');
}
if (($streamToolResult['tool_calls'][0]['function']['arguments'] ?? '') !== '{"query":"phone"}') {
    throw new RuntimeException('流式工具参数合并失败');
}
if ($toolEvents !== []) throw new RuntimeException('工具参数不应作为用户文本事件输出');
$textToolRequest = $toolRequest;
$textToolRequest['messages'][0]['content'] = 'use text tool';
$textToolEvents = [];
$textToolResult = $provider->stream($providerConfig, $textToolRequest, static function (array $event) use (&$textToolEvents): void {
    $textToolEvents[] = $event;
});
if (($textToolResult['tool_calls'][0]['function']['name'] ?? '') !== 'mock_search') {
    throw new RuntimeException('文本工具协议未转换为标准工具调用');
}
if ($textToolEvents !== []) throw new RuntimeException('文本工具协议不应泄漏到用户事件');
echo "hsx_ai provider contract smoke passed\n";
