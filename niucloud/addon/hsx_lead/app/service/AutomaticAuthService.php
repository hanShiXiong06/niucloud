<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\auth\{CaptchaRecognizerContract,TwoCaptchaRecognizer};
use addon\hsx_lead\app\provider\{ProviderDefinition,ProviderException,ProviderRegistry};
use addon\hsx_lead\app\support\{Cipher,Data};
use core\exception\CommonException;
use think\facade\{Cache,Db};

/** Short-lived, operator-bound login workflow. No ERP password is stored in a connection. */
final class AutomaticAuthService
{
    private const TTL = 180;
    private const RETAIN = 240;
    private const POLL = 5;
    private const LEASE = 50;
    private const MAX_ATTEMPTS = 2;
    private const PURPOSE = 'hsx_lead.automatic_auth.v1';
    private ProviderRegistry $providers;
    private CaptchaRecognizerContract $recognizer;
    private CaptchaConfigService $config;

    public function __construct(private Context $context, ?ProviderRegistry $providers = null,
        ?CaptchaRecognizerContract $recognizer = null, ?CaptchaConfigService $config = null)
    {
        $this->providers = $providers ?? new ProviderRegistry();
        $this->recognizer = $recognizer ?? new TwoCaptchaRecognizer();
        $this->config = $config ?? new CaptchaConfigService($context, $this->recognizer);
    }

    /** Idempotent local task creation. This endpoint never submits a paid recognition request. */
    public function start(int $merchantId, #[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->context->manager();
        $key = Data::text($input, 'provider', 60, true);
        $definition = $this->providers->definition($key);
        (new ProviderChannelService($this->context->site))->assertEnabled($definition->key());
        $metadata = $definition->metadata();
        $capability = $metadata['interactive_auth'] ?? [];
        if (($capability['type'] ?? null) !== 'image_captcha' || ($capability['automatic_recognition'] ?? false) !== true
            || ($capability['code_length'] ?? null) !== 4) throw new CommonException('此ERP未启用普通图片验证码自动登录，请使用原授权方式');
        $credentials = $this->identity($definition, $input['credentials'] ?? null);
        if (!is_array($input['scope'] ?? [])) throw new CommonException('来源范围格式不正确');
        $scope = $definition->scope($input['scope'] ?? []);
        $password = Data::secret($input, 'password', 4096);
        $resumeOnly = ($input['resume_only'] ?? false) === true;
        if (!$resumeOnly && $password === '') throw new CommonException('请填写本次登录密码');
        $requestId = Data::text($input, 'request_id', 32, true);
        if (preg_match('/^[a-f0-9]{32}$/D', $requestId) !== 1) throw new CommonException('本次登录请求标识无效，请重新打开授权向导');
        $settings = $resumeOnly ? [] : $this->config->credentials();
        if (!$resumeOnly && (empty($settings['enabled']) || empty($settings['api_key']))) throw new CommonException('自动登录尚未配置，请先配置识别服务，或改用手动验证码');
        $fingerprint = hash('sha256', Data::json([$definition->key(), $credentials, $scope, $password]));
        return Db::transaction(function () use ($merchantId, $definition, $credentials, $scope, $password, $requestId, $fingerprint, $settings, $resumeOnly) {
            (new ProviderChannelService($this->context->site))->assertEnabled($definition->key(), true);
            $this->merchant($merchantId, true);
            $requestKey = $this->requestKey($merchantId, $requestId);
            $prior = $this->pointer($requestKey, $merchantId);
            if ($prior) {
                $state = $this->read((string) $prior['token'], $merchantId);
                $this->owner($state, $merchantId);
                $sameIdentity = $state['provider'] === $definition->key() && $state['credentials'] === $credentials && $state['scope'] === $scope;
                if (!$sameIdentity || (!$resumeOnly && ($prior['fingerprint'] ?? null) !== $fingerprint)) {
                    throw new CommonException('同一登录请求不能更换账号、密码或来源范围，请重新发起');
                }
                return $this->publicState($state, (string) $prior['token']);
            }
            if ($resumeOnly) throw new CommonException('没有找到本次登录申请，未创建收费任务；请重新输入密码后登录');
            $active = $this->pointer($this->activeKey($merchantId), $merchantId);
            if ($active && (int) ($active['expires_at'] ?? 0) > time()) {
                $state = $this->read((string) $active['token'], $merchantId);
                if (($state['status'] ?? null) === 'processing' && (int) $state['expires_at'] > time()) {
                    throw new CommonException('此商户已有自动登录正在处理，请继续查询原任务或等待结束，避免重复收费');
                }
            }
            $token = bin2hex(random_bytes(32));
            $now = time();
            $state = ['purpose' => self::PURPOSE, 'site' => $this->context->site, 'uid' => $this->context->uid,
                'merchant_id' => $merchantId, 'provider' => $definition->key(), 'credentials' => $credentials, 'scope' => $scope,
                'password' => $password, 'revision' => $this->revision($merchantId), 'config_revision' => (string) $settings['revision'],
                'status' => 'processing', 'stage' => 'preparing', 'message' => '正在准备自动登录；识别服务只接收验证码图片',
                'created_at' => $now, 'expires_at' => $now + self::TTL, 'attempts' => 0, 'next_poll_at' => 0,
                'lease' => '', 'lease_until' => 0, 'operation' => ''];
            $this->put($token, $state, $merchantId);
            $pointer = ['token' => $token, 'fingerprint' => $fingerprint, 'expires_at' => $state['expires_at']];
            $this->writePointer($requestKey, $pointer, $merchantId);
            $this->writePointer($this->activeKey($merchantId), $pointer, $merchantId);
            $this->context->audit('automatic_auth', $merchantId, 'started', ['provider' => $definition->key()]);
            return $this->publicState($state, $token);
        });
    }

    /** One bounded step per request. Refreshing a task never re-submits an uncertain paid request. */
    public function advance(int $merchantId, #[\SensitiveParameter] string $token): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->context->manager();
        $claim = Db::transaction(function () use ($merchantId, $token) {
            $pending = $this->read($token, $merchantId);
            $this->owner($pending, $merchantId);
            $channelOpen = $this->channelOpen((string) $pending['provider'], true);
            $this->merchant($merchantId, true);
            $state = $this->read($token, $merchantId);
            $this->owner($state, $merchantId);
            if ($state['status'] !== 'processing') return ['public' => $this->publicState($state, $token)];
            if (!$channelOpen) return ['public' => $this->endForClosedChannel($token, $state)];
            if ((int) $state['expires_at'] <= time()) return ['public' => $this->end($token, $state, 'expired', '本次自动登录已过期，请重新登录或手动填写验证码')];
            if ($state['revision'] !== $this->revision($merchantId)) return ['public' => $this->end($token, $state, 'cancelled', 'ERP接入配置已变化，本次自动登录已停止，请重新核对账号')];
            if ($state['lease'] !== '' && (int) $state['lease_until'] > time()) return ['public' => $this->publicState($state, $token)];
            // A crashed query is safely repeatable. Creating a paid task or logging
            // in after a lost response is not repeatable and must stop instead.
            if ($state['lease'] !== '' && in_array($state['operation'], ['create', 'login'], true)) {
                return ['public' => $this->end($token, $state, 'failed', '上次识别提交或登录结果未确认，未重复申请；请核对后手动登录')];
            }
            if ((int) $state['next_poll_at'] > time()) return ['public' => $this->publicState($state, $token)];
            $state['operation'] = match ($state['stage']) {
                'preparing' => 'create','recognizing' => 'query','logging_in' => 'login',default => ''
            };
            if ($state['operation'] === '') return ['public' => $this->end($token, $state, 'failed', '自动登录状态异常，请使用手动验证码')];
            $state['lease'] = bin2hex(random_bytes(16));
            $state['lease_until'] = time() + self::LEASE;
            $this->put($token, $state, $merchantId);
            return ['state' => $state];
        });
        if (isset($claim['public'])) return $claim['public'];
        $state = $claim['state'];
        try {
            (new ProviderChannelService($this->context->site))->assertEnabled((string) $state['provider']);
            $settings = $this->config->credentials();
            if (empty($settings['enabled']) || empty($settings['api_key']) || (string) $settings['revision'] !== $state['config_revision']) {
                throw new CommonException('识别服务配置已变化或已关闭，本次自动登录已停止，请重新发起');
            }
            if ($state['operation'] === 'create') return $this->createRecognition($merchantId, $token, $state, $settings);
            if ($state['operation'] === 'query') return $this->queryRecognition($merchantId, $token, $state, $settings);
            return $this->login($merchantId, $token, $state);
        } catch (\Throwable $error) {
            if ($error instanceof ProviderException && $error->reason() === 'captcha_temporary' && $state['operation'] === 'query') {
                return $this->commit($merchantId, $token, $state, ['message' => '识别结果暂时无法查询，稍后继续查询原任务；不会重新提交收费任务', 'next_poll_at' => time() + 10]);
            }
            $message = $error instanceof ProviderException || $error instanceof CommonException
                ? $error->getMessage() : '自动登录未完成，未返回原始错误或凭据；请使用手动验证码';
            return $this->commit($merchantId, $token, $state, ['status' => 'failed', 'message' => $message]);
        }
    }

    public function cancel(int $merchantId, #[\SensitiveParameter] string $token): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->context->manager();
        return Db::transaction(function () use ($merchantId, $token) {
            $this->merchant($merchantId, true);
            $state = $this->read($token, $merchantId);
            $this->owner($state, $merchantId);
            if ($state['status'] !== 'processing') return $this->publicState($state, $token);
            return $this->end($token, $state, 'cancelled', '已停止本站自动登录；已提交的识别任务可能计费，取消不等于服务商退款');
        });
    }

    private function createRecognition(int $merchantId, string $token, #[\SensitiveParameter] array $state, #[\SensitiveParameter] array $settings): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if ((int) $state['attempts'] >= self::MAX_ATTEMPTS) throw new CommonException('已达到本次自动识别上限，请使用手动验证码');
        $challenge = (new AuthChallengeService($this->context, $this->providers))->begin($merchantId,
            ['provider' => $state['provider'], 'credentials' => $state['credentials']], (int) $state['expires_at']);
        $this->config->reserveAttempt($state['config_revision']);
        // Recheck cancellation before the irreversible API call. This short claim
        // is committed before HTTP; the counter is conservatively not refunded.
        $canSubmit = Db::transaction(function () use ($merchantId, $token, $state) {
            (new ProviderChannelService($this->context->site))->assertEnabled((string) $state['provider'], true);
            $this->merchant($merchantId, true);
            $current = $this->read($token, $merchantId);
            $this->owner($current, $merchantId);
            return $this->sameClaim($current, $state) && $current['revision'] === $this->revision($merchantId)
                && (int) $current['expires_at'] > time();
        });
        if (!$canSubmit) return $this->current($merchantId, $token);
        (new ProviderChannelService($this->context->site))->assertEnabled((string) $state['provider']);
        $taskId = $this->recognizer->create($settings['api_key'], $challenge['captcha_image'], 4);
        return $this->commit($merchantId, $token, $state, ['stage' => 'recognizing', 'recognition_task' => $taskId,
            'challenge_token' => $challenge['challenge_token'], 'expires_at' => min((int) $state['expires_at'], (int) $challenge['expires_at']),
            'attempts' => (int) $state['attempts'] + 1, 'next_poll_at' => time() + self::POLL, 'message' => '正在识别验证码，请稍候；无需重复点击登录']);
    }

    private function queryRecognition(int $merchantId, string $token, #[\SensitiveParameter] array $state, #[\SensitiveParameter] array $settings): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        (new ProviderChannelService($this->context->site))->assertEnabled((string) $state['provider']);
        $result = $this->recognizer->result($settings['api_key'], (string) ($state['recognition_task'] ?? ''), 4);
        if (($result['status'] ?? null) === 'processing') return $this->commit($merchantId, $token, $state,
            ['message' => '验证码仍在识别中，正在等待原任务结果', 'next_poll_at' => time() + self::POLL]);
        if (($result['status'] ?? null) !== 'ready' || !is_string($result['text'] ?? null)
            || preg_match('/^[A-Za-z0-9]{4}$/D', $result['text']) !== 1) throw new CommonException('验证码识别结果格式不正确，请使用手动验证码');
        return $this->commit($merchantId, $token, $state, ['stage' => 'logging_in', 'answer' => $result['text'],
            'next_poll_at' => time() + self::POLL, 'message' => '验证码已识别，正在准备登录并核对来源公司']);
    }

    private function login(int $merchantId, string $token, #[\SensitiveParameter] array $state): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        (new ProviderChannelService($this->context->site))->assertEnabled((string) $state['provider']);
        try {
            $result = (new ConnectionService($this->context, $this->providers))->probe($merchantId,
                ['provider' => $state['provider'], 'credentials' => $state['credentials'], 'scope' => $state['scope'],
                    'authorization' => ['challenge_token' => $state['challenge_token'], 'password' => $state['password'], 'code' => $state['answer']]]);
        } catch (ProviderException $error) {
            if ($error->reason() !== 'invalid_captcha' || (int) $state['attempts'] >= self::MAX_ATTEMPTS
                || (int) $state['expires_at'] < time() + 30) throw $error;
            return $this->commit($merchantId, $token, $state, ['stage' => 'preparing', 'answer' => '', 'challenge_token' => '', 'recognition_task' => '',
                'next_poll_at' => time() + self::POLL, 'message' => '本次验证码未通过，正在更换新图重试最后一次']);
        }
        return $this->commit($merchantId, $token, $state, ['status' => 'completed', 'result' => $result,
            'message' => !empty($result['authorization_required']) ? '请从本次返回的候选公司中选择，再完成验证' : '登录及库存首屏验证成功，请核对后确认启用']);
    }

    private function commit(int $merchantId, string $token, #[\SensitiveParameter] array $claim, #[\SensitiveParameter] array $changes): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return Db::transaction(function () use ($merchantId, $token, $claim, $changes) {
            $channelOpen = $this->channelOpen((string) $claim['provider'], true);
            $this->merchant($merchantId, true);
            $state = $this->read($token, $merchantId);
            $this->owner($state, $merchantId);
            if (!$this->sameClaim($state, $claim)) return $this->publicState($state, $token);
            if (!$channelOpen) return $this->endForClosedChannel($token, $state);
            if ((int) $state['expires_at'] <= time()) return $this->end($token, $state, 'expired', '本次自动登录已过期，请重新验证');
            if ($state['revision'] !== $this->revision($merchantId)) return $this->end($token, $state, 'cancelled', 'ERP接入配置已变化，本次结果未采用');
            $settings = $this->config->credentials();
            if (empty($settings['enabled']) || (string) $settings['revision'] !== $state['config_revision']) {
                return $this->end($token, $state, 'cancelled', '识别服务配置已变化，本次结果未采用，请重新核对后登录');
            }
            $state = array_replace($state, $changes, ['lease' => '', 'lease_until' => 0, 'operation' => '']);
            if ($state['status'] !== 'processing') $state = $this->stripSecrets($state);
            $this->put($token, $state, $merchantId);
            if ($state['status'] !== 'processing') $this->context->audit('automatic_auth', $merchantId, $state['status'],
                ['provider' => $state['provider'], 'attempts' => (int) $state['attempts']]);
            return $this->publicState($state, $token);
        });
    }

    /** Closed channels stop existing short-lived authorization tasks without another HTTP call. */
    private function channelOpen(string $provider, bool $lockSite = false): bool
    {
        try {
            (new ProviderChannelService($this->context->site))->assertEnabled($provider, $lockSite);
            return true;
        } catch (ProviderException $error) {
            if ($error->reason() === 'channel_disabled') return false;
            throw $error;
        }
    }

    private function endForClosedChannel(string $token, #[\SensitiveParameter] array $state): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return $this->end($token, $state, 'cancelled', '该ERP登录渠道已关闭，本次自动登录已停止；不会继续提交识别或登录。已提交的识别任务可能计费。');
    }

    private function end(string $token, #[\SensitiveParameter] array $state, string $status, string $message): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $state = $this->stripSecrets(array_replace($state, ['status' => $status, 'message' => $message, 'lease' => '', 'lease_until' => 0, 'operation' => '']));
        $this->put($token, $state, (int) $state['merchant_id']);
        $this->context->audit('automatic_auth', (int) $state['merchant_id'], $status, ['provider' => $state['provider'], 'attempts' => (int) $state['attempts']]);
        return $this->publicState($state, $token);
    }

    private function stripSecrets(#[\SensitiveParameter] array $state): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        unset($state['password'],$state['answer'],$state['challenge_token'],$state['recognition_task']);
        return $state;
    }

    private function current(int $merchantId, string $token): array
    {
        $state = $this->read($token, $merchantId);
        $this->owner($state, $merchantId);
        return $this->publicState($state, $token);
    }

    private function publicState(#[\SensitiveParameter] array $state, #[\SensitiveParameter] string $token): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $status = $state['status'];
        if ($status === 'processing' && (int) $state['expires_at'] <= time()) $status = 'expired';
        $public = ['task_token' => $token, 'status' => $status, 'stage' => $state['stage'], 'message' => $status === 'expired'
            ? '本次自动登录已过期，请重新登录或手动填写验证码' : $state['message'], 'expires_at' => (int) $state['expires_at'],
            'retry_after' => max(self::POLL, (int) $state['next_poll_at'] - time()), 'manual_available' => true];
        if ($status === 'completed' && isset($state['result'])) $public['result'] = $state['result'];
        return $public;
    }

    private function identity(ProviderDefinition $definition, #[\SensitiveParameter] mixed $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_array($input)) throw new CommonException('请填写ERP授权账号');
        $metadata = $definition->metadata();
        $fields = [];
        foreach ($metadata['fields'] as $field) $fields[$field['key']] = $field;
        $result = [];
        foreach ($metadata['interactive_auth']['credential_fields'] as $key) {
            $field = $fields[$key] ?? null;
            if (!$field || !empty($field['secret'])) throw new CommonException('ERP自动登录身份配置异常');
            $result[$key] = Data::text($input, $key, (int) $field['max_length'], !empty($field['required']));
        }
        return $result;
    }

    private function owner(#[\SensitiveParameter] array $state, int $merchantId): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (($state['purpose'] ?? null) !== self::PURPOSE || ($state['site'] ?? null) !== $this->context->site
            || ($state['uid'] ?? null) !== $this->context->uid || ($state['merchant_id'] ?? null) !== $merchantId) {
            throw new CommonException('自动登录任务不属于当前站点、商户或操作员');
        }
    }

    private function merchant(int $id, bool $lock): array
    {
        $merchant = Data::row('merchant', $this->context->site, $id, $lock);
        if ((int) $merchant['status'] !== 1) throw new CommonException('商户已暂停，不能继续自动登录');
        return $merchant;
    }

    private function revision(int $merchantId): string
    {
        $row = Data::query('connection', $this->context->site)->where('merchant_id', $merchantId)->find();
        return hash('sha256', Data::json($row ? array_intersect_key($row, array_flip(['id', 'config_version', 'provider', 'expected_tenant_id', 'store_id'])) : []));
    }

    private function sameClaim(#[\SensitiveParameter] array $state, #[\SensitiveParameter] array $claim): bool
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return $state['status'] === 'processing' && $state['lease'] !== '' && hash_equals($state['lease'], $claim['lease']);
    }

    private function read(#[\SensitiveParameter] string $token, int $merchantId): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) throw new CommonException('自动登录任务标识无效');
        $cipher = Cache::get($this->taskKey($token));
        if (!is_string($cipher) || $cipher === '') throw new CommonException('自动登录任务已过期或已清理，请重新登录；不会自动重建收费任务');
        return Cipher::decrypt($cipher, $this->context->site, -$merchantId);
    }

    private function put(#[\SensitiveParameter] string $token, #[\SensitiveParameter] array $state, int $merchantId): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (strlen(Data::json($state)) > 131072) throw new CommonException('自动登录临时资料超过安全上限，请使用手动验证码');
        $retention = $state['status'] === 'processing' ? 0 : self::RETAIN;
        if (!Cache::set($this->taskKey($token), Cipher::encrypt($state, $this->context->site, -$merchantId),
            max(1, (int) $state['expires_at'] - time() + $retention))) throw new CommonException('自动登录状态无法保存，请检查缓存；不要重复提交识别');
    }

    private function pointer(string $key, int $merchantId): array
    {
        $value = Cache::get($key);
        return is_string($value) && $value !== '' ? Cipher::decrypt($value, $this->context->site, -$merchantId) : [];
    }

    private function writePointer(string $key, #[\SensitiveParameter] array $value, int $merchantId): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!Cache::set($key, Cipher::encrypt($value, $this->context->site, -$merchantId), self::TTL + self::RETAIN)) {
            throw new CommonException('自动登录请求无法保存，请检查缓存配置');
        }
    }

    private function taskKey(#[\SensitiveParameter] string $token): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return 'hsx_lead_auto_auth_'.hash('sha256', $token);
    }
    private function activeKey(int $merchantId): string
    {
        return 'hsx_lead_auto_auth_active_'.$this->context->site.'_'.$merchantId;
    }
    private function requestKey(int $merchantId, string $requestId): string
    {
        return 'hsx_lead_auto_auth_request_'.hash('sha256', $this->context->site.'|'.$this->context->uid.'|'.$merchantId.'|'.$requestId);
    }
}
