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
    if (!empty($payload['stream'])) {
        header('Content-Type: text/event-stream; charset=utf-8');
        $chunks = [
            ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['reasoning_content' => 'thinking'], 'finish_reason' => null]]],
            ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['content' => 'mock '], 'finish_reason' => null]]],
            ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [['delta' => ['content' => 'response'], 'finish_reason' => 'stop']]],
            ['id' => 'chatcmpl-stream', 'model' => (string)$payload['model'], 'choices' => [], 'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12]],
        ];
        foreach ($chunks as $chunk) echo 'data: ' . json_encode($chunk) . "\n\n";
        echo "data: [DONE]\n\n";
        return;
    }
    echo json_encode([
        'id' => 'chatcmpl-mock',
        'model' => (string)$payload['model'],
        'choices' => [[
            'message' => ['role' => 'assistant', 'content' => $jsonMode ? '{"ok":true}' : 'mock response'],
            'finish_reason' => 'stop',
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
