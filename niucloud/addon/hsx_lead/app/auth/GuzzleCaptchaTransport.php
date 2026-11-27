<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\auth;

use addon\hsx_lead\app\provider\ProviderException;
use GuzzleHttp\{Client, ClientInterface, HandlerStack};

/** Separate from ERP transport: never accepts a site URL, cookies, credentials or arbitrary tasks. */
final class GuzzleCaptchaTransport implements CaptchaTransportContract
{
    private const METHODS = ['createTask', 'getTaskResult', 'getBalance'];
    private const MAX_BYTES = 65536;
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client(['handler' => HandlerStack::create()]);
    }

    public function post(string $method, #[\SensitiveParameter] array $body): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!in_array($method, self::METHODS, true)) {
            throw new ProviderException('captcha_invalid_request', '未允许的识别服务方法');
        }
        $this->validateBody($method, $body);
        try {
            $response = $this->client->request('POST', 'https://api.2captcha.com/'.$method, [
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'json' => $body, 'verify' => true, 'allow_redirects' => false, 'cookies' => false,
                'http_errors' => false, 'debug' => false, 'stream' => true,
                'connect_timeout' => 5, 'timeout' => 12, 'read_timeout' => 12,
            ]);
            $stream = $response->getBody();
            try {
                if ($response->getStatusCode() !== 200) throw new \RuntimeException('Non-200 response');
                $json = '';
                while (!$stream->eof()) {
                    $chunk = $stream->read(8192);
                    if ($chunk === '' && !$stream->eof()) throw new \RuntimeException('Incomplete response');
                    $json .= $chunk;
                    if (strlen($json) > self::MAX_BYTES) throw new \RuntimeException('Response limit');
                }
            } finally {
                $stream->close();
            }
            return self::decode($json);
        } catch (\Throwable $error) {
            // A timed-out create may already have incurred a charge. Never silently resubmit.
            throw new ProviderException($method === 'createTask' ? 'captcha_submit_unknown' : 'captcha_temporary',
                $method === 'createTask' ? '识别任务提交结果待核实，请勿重复提交；可改为人工填写验证码'
                    : '识别服务暂未响应，可稍后查询原任务或改为人工填写验证码');
        }
    }

    private function validateBody(string $method, #[\SensitiveParameter] array $body): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $allowed = match ($method) {
            'createTask' => ['clientKey', 'task'], 'getTaskResult' => ['clientKey', 'taskId'], default => ['clientKey']
        };
        if (array_diff(array_keys($body), $allowed) !== [] || array_diff($allowed, array_keys($body)) !== [] ||
            !is_string($body['clientKey']) || preg_match('/^[a-fA-F0-9]{32}$/D', $body['clientKey']) !== 1) {
            throw new ProviderException('captcha_invalid_request', '识别服务请求参数不合法');
        }
        if ($method === 'getTaskResult' && (!is_int($body['taskId']) || $body['taskId'] <= 0)) {
            throw new ProviderException('captcha_invalid_request', '识别任务标识不合法');
        }
        if ($method !== 'createTask') return;
        $task = $body['task'];
        $fields = ['type', 'body', 'case', 'numeric', 'minLength', 'maxLength'];
        if (!is_array($task) || array_diff(array_keys($task), $fields) !== [] || array_diff($fields, array_keys($task)) !== [] ||
            $task['type'] !== 'ImageToTextTask' || !is_string($task['body']) || strlen($task['body']) > 133336 ||
            preg_match('/^[A-Za-z0-9+\/]+={0,2}$/D', $task['body']) !== 1 ||
            $task['case'] !== true || $task['numeric'] !== 0 || $task['minLength'] !== 4 || $task['maxLength'] !== 4) {
            throw new ProviderException('captcha_invalid_request', '仅允许已核验的四位图片识别请求');
        }
    }

    private static function decode(#[\SensitiveParameter] string $json): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        // Preserve numeric balance/cost lexemes instead of making money out of binary floats.
        json_decode($json, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        $exact = preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+\-]?[0-9]+)?/',
            static fn (array $match): string => strpbrk($match[0], '.eE') === false ? $match[0] : '"'.$match[0].'"', $json);
        $decoded = json_decode($exact ?? '', true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($decoded) || \addon\hsx_lead\app\support\PhpRuntime::isList($decoded)) throw new \RuntimeException('Invalid response shape');
        return $decoded;
    }
}
