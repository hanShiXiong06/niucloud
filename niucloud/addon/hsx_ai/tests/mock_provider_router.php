<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authorization = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
if ($authorization !== 'Bearer test-token') {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'invalid token']]);
    return;
}

$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($path === '/v1/models') {
    echo json_encode([
        'data' => [
            ['id' => 'mock-model', 'owned_by' => 'hsx'],
            ['id' => 'mock-json-model', 'owned_by' => 'hsx'],
        ],
    ]);
    return;
}

if ($path === '/v1/chat/completions') {
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload) || empty($payload['model']) || empty($payload['messages'])) {
        http_response_code(422);
        echo json_encode(['error' => ['message' => 'invalid payload']]);
        return;
    }
    $jsonMode = ($payload['response_format']['type'] ?? '') === 'json_object';
    $lastMessage = (array)end($payload['messages']);
    $toolMode = !empty($payload['tools'])
        && ($lastMessage['role'] ?? '') === 'user'
        && str_contains((string)($lastMessage['content'] ?? ''), 'use tool');
    $textToolMode = !empty($payload['tools'])
        && ($lastMessage['role'] ?? '') === 'user'
        && str_contains((string)($lastMessage['content'] ?? ''), 'use text tool');
    $toolResultMode = ($lastMessage['role'] ?? '') === 'tool';
    if (!empty($payload['stream'])) {
        header('Content-Type: text/event-stream; charset=utf-8');
        if ($textToolMode) {
            $chunks = [
                ['id' => 'chatcmpl-stream-text-tool', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['content' => '<｜tool▁calls▁begin｜>function<｜tool'], 'finish_reason' => null]]],
                ['id' => 'chatcmpl-stream-text-tool', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['content' => '▁sep｜>mock_search\n```json\n{"query":"phone"}'], 'finish_reason' => null]]],
                ['id' => 'chatcmpl-stream-text-tool', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['content' => '\n```<｜tool▁call▁end｜><｜tool▁calls▁end｜>'], 'finish_reason' => 'stop']]],
                ['id' => 'chatcmpl-stream-text-tool', 'model' => (string)$payload['model'], 'choices' => [], 'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12]],
            ];
        } elseif ($toolMode) {
            $chunks = [
                ['id' => 'chatcmpl-stream-tool', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_mock', 'type' => 'function', 'function' => ['name' => 'mock_', 'arguments' => '{"query":"']]]], 'finish_reason' => null]]],
                ['id' => 'chatcmpl-stream-tool', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['name' => 'search', 'arguments' => 'phone"}']]]], 'finish_reason' => 'tool_calls']]],
                ['id' => 'chatcmpl-stream-tool', 'model' => (string)$payload['model'], 'choices' => [], 'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12]],
            ];
        } else {
            $chunks = [
                ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['reasoning_content' => 'thinking'], 'finish_reason' => null]]],
                ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['content' => $toolResultMode ? 'tool result ' : 'mock '], 'finish_reason' => null]]],
                ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['content' => $toolResultMode ? 'accepted' : 'response'], 'finish_reason' => 'stop']]],
                ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [], 'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12]],
            ];
        }
        foreach ($chunks as $chunk) echo 'data: ' . json_encode($chunk) . "\n\n";
        echo "data: [DONE]\n\n";
        return;
    }
    $message = ['role' => 'assistant', 'content' => $jsonMode ? '{"ok":true}' : ($toolResultMode ? 'tool result accepted' : 'mock response')];
    $finishReason = 'stop';
    if ($textToolMode) {
        $message = [
            'role' => 'assistant',
            'content' => '<｜tool▁calls▁begin｜>function<｜tool▁sep｜>mock_search\n```json\n{"query":"phone"}\n```<｜tool▁call▁end｜><｜tool▁calls▁end｜>',
        ];
        $finishReason = 'stop';
    } elseif ($toolMode) {
        $message = [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_mock',
                'type' => 'function',
                'function' => ['name' => 'mock_search', 'arguments' => '{"query":"phone"}'],
            ]],
        ];
        $finishReason = 'tool_calls';
    }
    echo json_encode([
        'id' => 'chatcmpl-mock',
        'model' => (string)$payload['model'],
        'choices' => [[
            'message' => $message,
            'finish_reason' => $finishReason,
        ]],
        'usage' => [
            'prompt_tokens' => 8,
            'completion_tokens' => 4,
            'total_tokens' => 12,
        ],
    ]);
    return;
}

http_response_code(404);
echo json_encode(['error' => ['message' => 'not found']]);
