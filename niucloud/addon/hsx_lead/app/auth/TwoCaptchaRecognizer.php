<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\auth;

use addon\hsx_lead\app\provider\ProviderException;

/** Official API v2 ImageToTextTask only. No polling loop, callback, promotion ID or ERP data. */
final class TwoCaptchaRecognizer implements CaptchaRecognizerContract
{
    private CaptchaTransportContract $transport;
    private const MESSAGES = [
        'captcha_invalid_key' => '识别服务密钥无效，请管理员核对',
        'captcha_zero_balance' => '识别服务余额不足，请充值或改为人工填写验证码',
        'captcha_ip_not_allowed' => '识别服务未允许当前服务器IP，请管理员核对服务商白名单',
        'captcha_no_slot' => '识别服务当前繁忙，本次未受理，请改为人工填写验证码',
        'captcha_unsolvable' => '这张验证码未能识别，请改为人工填写或重新获取图片',
        'captcha_not_found' => '原识别任务不存在或已过期，请改为人工填写验证码',
        'captcha_invalid_image' => '验证码图片格式或大小不符合识别要求，请重新获取',
        'captcha_invalid_request' => '识别服务请求参数不合法',
        'captcha_schema_changed' => '识别服务返回格式变化，请改为人工填写并联系管理员',
        'captcha_submit_unknown' => '识别任务提交结果待核实，请勿重复提交；可改为人工填写验证码',
        'captcha_temporary' => '识别服务暂未完成处理，请稍后查询原任务或改为人工填写验证码',
    ];
    private const ERRORS = [
        'ERROR_KEY_DOES_NOT_EXIST' => 'captcha_invalid_key',
        'ERROR_ZERO_BALANCE' => 'captcha_zero_balance',
        'ERROR_IP_NOT_ALLOWED' => 'captcha_ip_not_allowed', 'ERROR_IP_BLOCKED' => 'captcha_ip_not_allowed',
        'ERROR_NO_SLOT_AVAILABLE' => 'captcha_no_slot',
        'ERROR_CAPTCHA_UNSOLVABLE' => 'captcha_unsolvable', 'ERROR_BAD_DUPLICATES' => 'captcha_unsolvable',
        'ERROR_NO_SUCH_CAPCHA_ID' => 'captcha_not_found',
        'ERROR_ZERO_CAPTCHA_FILESIZE' => 'captcha_invalid_image', 'ERROR_TOO_BIG_CAPTCHA_FILESIZE' => 'captcha_invalid_image',
        'ERROR_IMAGE_TYPE_NOT_SUPPORTED' => 'captcha_invalid_image',
    ];

    public function __construct(?CaptchaTransportContract $transport = null)
    {
        $this->transport = $transport ?? new GuzzleCaptchaTransport();
    }

    public function create(#[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $dataUri, int $length = 4): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->length($length);
        $body = ['clientKey' => $this->key($apiKey), 'task' => ['type' => 'ImageToTextTask', 'body' => $this->image($dataUri),
            'case' => true, 'numeric' => 0, 'minLength' => $length, 'maxLength' => $length]];
        $response = $this->call('createTask', $body);
        $this->assertSuccess($response, true);
        try {
            return $this->taskId($response['taskId'] ?? null);
        } catch (ProviderException $error) {
            $this->fail('captcha_submit_unknown');
        }
    }

    public function result(#[\SensitiveParameter] string $apiKey, string $taskId, int $length = 4): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->length($length);
        $id = $this->taskId($taskId);
        $response = $this->call('getTaskResult', ['clientKey' => $this->key($apiKey), 'taskId' => (int) $id]);
        $this->assertSuccess($response);
        if (($response['status'] ?? null) === 'processing') return ['status' => 'processing', 'cost' => null];
        $solution = $response['solution'] ?? null;
        $text = is_array($solution) ? ($solution['text'] ?? null) : null;
        if (($response['status'] ?? null) !== 'ready' || !is_string($text) || preg_match('/^[A-Za-z0-9]{4}$/D', $text) !== 1) {
            $this->fail('captcha_schema_changed');
        }
        return ['status' => 'ready', 'text' => $text, 'cost' => $this->decimal($response['cost'] ?? null)];
    }

    public function balance(#[\SensitiveParameter] string $apiKey): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $response = $this->call('getBalance', ['clientKey' => $this->key($apiKey)]);
        $this->assertSuccess($response);
        $balance = $this->decimal($response['balance'] ?? null);
        if ($balance === null) $this->fail('captcha_schema_changed');
        return $balance;
    }

    private function key(#[\SensitiveParameter] string $key): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (preg_match('/^[a-fA-F0-9]{32}$/D', $key) !== 1) $this->fail('captcha_invalid_key');
        return $key;
    }

    private function length(int $length): void
    {
        if ($length !== 4) $this->fail('captcha_invalid_request');
    }

    private function image(#[\SensitiveParameter] string $uri): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (strlen($uri) > 133400 || preg_match('/^data:(image\/(?:jpeg|png|gif));base64,([A-Za-z0-9+\/]+={0,2})$/D', $uri, $match) !== 1) {
            $this->fail('captcha_invalid_image');
        }
        $bytes = base64_decode($match[2], true);
        if (!is_string($bytes) || base64_encode($bytes) !== $match[2] || strlen($bytes) < 100 || strlen($bytes) > 100000) {
            $this->fail('captcha_invalid_image');
        }
        $magic = match ($match[1]) {
            'image/jpeg' => str_starts_with($bytes, "\xff\xd8\xff"),
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),
            'image/gif' => str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a'),
        };
        try {
            $size = @getimagesizefromstring($bytes);
        } catch (\Throwable $error) {
            $this->fail('captcha_invalid_image');
        }
        if (!$magic || !is_array($size) || ($size['mime'] ?? null) !== $match[1] ||
            ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1 || $size[0] > 600 || $size[1] > 600) {
            $this->fail('captcha_invalid_image');
        }
        return $match[2];
    }

    private function taskId(mixed $id): string
    {
        if ((!is_int($id) && !is_string($id)) || preg_match('/^[1-9][0-9]{0,18}$/D', (string) $id) !== 1 ||
            strlen((string) $id) > strlen((string) PHP_INT_MAX) ||
            (strlen((string) $id) === strlen((string) PHP_INT_MAX) && strcmp((string) $id, (string) PHP_INT_MAX) > 0)) {
            $this->fail('captcha_invalid_request');
        }
        return (string) $id;
    }

    private function decimal(mixed $value): ?string
    {
        if (is_int($value)) $value = (string) $value;
        if (is_float($value)) {
            if (!is_finite($value)) return null;
            // Other transport implementations may decode the official JSON number as a float.
            // Accept only its stable ordinary decimal form; never round it into apparent precision.
            try {
                $value = json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                return null;
            }
        }
        return is_string($value) && preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,8})?$/D', $value) === 1 ? $value : null;
    }

    private function call(string $method, #[\SensitiveParameter] array $body): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        try {
            return $this->transport->post($method, $body);
        } catch (ProviderException $error) {
            $reason = $error->reason();
            if (!isset(self::MESSAGES[$reason])) $reason = $method === 'createTask' ? 'captcha_submit_unknown' : 'captcha_temporary';
            $this->fail($reason);
        } catch (\Throwable $error) {
            $this->fail($method === 'createTask' ? 'captcha_submit_unknown' : 'captcha_temporary');
        }
    }

    private function assertSuccess(#[\SensitiveParameter] array $response, bool $submitting = false): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_int($response['errorId'] ?? null) || $response['errorId'] < 0) {
            $this->fail($submitting ? 'captcha_submit_unknown' : 'captcha_schema_changed');
        }
        if ($response['errorId'] === 0) return;
        $code = $response['errorCode'] ?? null;
        $this->fail(is_string($code) ? (self::ERRORS[$code] ?? 'captcha_temporary') : 'captcha_temporary');
    }

    private function fail(string $reason): void
    {
        throw new ProviderException($reason, self::MESSAGES[$reason]);
    }
}
