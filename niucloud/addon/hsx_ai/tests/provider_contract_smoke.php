<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/contract/AiProviderInterface.php';
require_once dirname(__DIR__) . '/app/contract/AiStreamingProviderInterface.php';
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
echo "hsx_ai provider contract smoke passed\n";
