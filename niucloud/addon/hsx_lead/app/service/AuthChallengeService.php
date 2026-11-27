<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\provider\{CredentialChallengeProviderContract,InteractiveAuthProviderContract,ProviderDefinition,ProviderException,ProviderRegistry};
use addon\hsx_lead\app\support\{Cipher,Data};
use core\exception\CommonException;
use think\facade\{Cache,Db};

/** Plugin-local interactive authorization. Secrets are short-lived encrypted cache state only. */
final class AuthChallengeService
{
    private const TTL = 180;
    private const MAX_STATE_BYTES = 65536;
    private const MAX_IMAGE_BYTES = 524288;
    private const MAX_STARTS = 5;
    private const RATE_SECONDS = 60;
    private const SMS_HOUR_SECONDS = 3600;
    private const SMS_MAX_HOURLY = 5;
    private const PURPOSE = 'hsx_lead.interactive_auth.v1';
    private ProviderRegistry $providers;

    public function __construct(private Context $context, ?ProviderRegistry $providers = null)
    {
        $this->providers = $providers ?? new ProviderRegistry();
    }

    public function begin(int $merchantId, #[\SensitiveParameter] array $input, ?int $deadline = null): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->context->connectionMerchant($merchantId);
        if ($deadline !== null && ($deadline <= time() || $deadline > time() + self::TTL)) {
            throw new CommonException('本次授权已过期，请重新登录');
        }
        $providerKey = Data::text($input, 'provider', 60, true);
        [$definition,$provider,$capability] = $this->capability($providerKey);
        $credentials = $this->identityCredentials($definition, $capability, $input['credentials'] ?? null);
        $binding = $this->binding($merchantId, $definition->key(), $credentials);
        $type = (string) $capability['type'];
        $mobileMask = $type === 'sms_code' ? $this->mobileMask($credentials) : '';
        $this->claimRate($merchantId, $binding, $type);
        (new ProviderChannelService($this->context->site))->assertEnabled($definition->key());
        // Sending a text is an explicit user action. The short cooldown claim
        // commits before this one request; an uncertain response is not retried.
        $challenge = $type === 'sms_code' ? $provider->beginAuthorizationFor($credentials) : $provider->beginAuthorization();
        if (!is_array($challenge['public'] ?? null) || !is_array($challenge['state'] ?? null)) {
            throw new ProviderException('schema_changed', 'ERP验证码授权返回结构变化，请重新获取');
        }
        $public = $type === 'image_captcha' ? ['captcha_image' => $this->image($challenge['public']['captcha_image'] ?? null)] : [
            'mobile_mask' => $mobileMask, 'retry_after' => self::RATE_SECONDS];
        $ttl = $challenge['public']['expires_in'] ?? self::TTL;
        if (!is_int($ttl) || $ttl < 1) throw new ProviderException('schema_changed', 'ERP验证码有效期格式变化');
        $expires = time() + min(self::TTL, $ttl);
        if ($deadline !== null) $expires = min($expires, $deadline);
        if ($expires <= time()) throw new CommonException('本次授权已过期，请重新登录');
        $payload = $binding + ['purpose' => self::PURPOSE, 'phase' => 'captcha', 'challenge_type' => $type, 'status' => 'ready', 'claim_nonce' => '',
            'expires_at' => $expires, 'private_state' => $challenge['state'], 'companies' => []];
        $this->boundedState($payload);
        $token = bin2hex(random_bytes(32));
        Db::transaction(function () use ($merchantId, $binding, $token, $payload, $expires) {
            $this->assertCurrent($merchantId, $binding, true);
            $this->put($token, $payload, $merchantId, $expires);
        });
        return ['status' => 'challenge', 'challenge_type' => $type, 'challenge_token' => $token, 'expires_at' => $expires,
            'expires_in' => max(0, $expires - time())] + $public;
    }

    public function authorize(int $merchantId, string $providerKey, #[\SensitiveParameter] array $credentials,
        #[\SensitiveParameter] array $authorization): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->context->connectionMerchant($merchantId);
        [$definition,$provider,$capability] = $this->capability($providerKey);
        $credentials = $this->identityCredentials($definition, $capability, $credentials);
        $binding = $this->binding($merchantId, $definition->key(), $credentials);
        $token = Data::secret($authorization, 'challenge_token', 64);
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) throw new CommonException('授权校验凭证无效，请重新获取验证码');
        $claim = Db::transaction(function () use ($merchantId, $binding, $token) {
            $this->assertCurrent($merchantId, $binding, true);
            $payload = $this->read($token, $merchantId);
            $this->assertBinding($payload, $binding);
            if (($payload['status'] ?? null) !== 'ready') throw new CommonException('此授权校验已使用或正在处理，请重新获取验证码');
            $payload['status'] = 'claimed';
            $payload['claim_nonce'] = bin2hex(random_bytes(16));
            $this->put($token, $payload, $merchantId, (int) $payload['expires_at']);
            return $payload;
        });
        try {
            if (($claim['challenge_type'] ?? 'image_captcha') !== $capability['type']) throw new CommonException('验证码类型已变化，请重新获取验证码');
            $answer = $this->answer($claim, $capability, $authorization);
            // The provider's HTTP request deliberately happens after the claim commits.
            (new ProviderChannelService($this->context->site))->assertEnabled($definition->key());
            $result = $provider->authorize($credentials, $claim['private_state'], $answer);
            if (($result['status'] ?? null) === 'authorized') {
                $authorized = $this->authorizedCredentials($definition, $result['credentials'] ?? null, $credentials);
                $this->finish($merchantId, $binding, $token, $claim);
                return ['status' => 'authorized', 'credentials' => $authorized];
            }
            if (($result['status'] ?? null) !== 'choose_company' || !is_array($result['state'] ?? null)) {
                throw new ProviderException('schema_changed', 'ERP授权返回结构变化，请重新获取验证码');
            }
            $companies = $this->companies($result['public']['companies'] ?? null);
            $next = $binding + ['purpose' => self::PURPOSE, 'phase' => 'company', 'challenge_type' => $capability['type'], 'status' => 'ready', 'claim_nonce' => '',
                'expires_at' => $claim['expires_at'], 'private_state' => $result['state'], 'companies' => $companies];
            $this->boundedState($next);
            $nextToken = bin2hex(random_bytes(32));
            Db::transaction(function () use ($merchantId, $binding, $token, $claim, $nextToken, $next) {
                $this->assertCurrent($merchantId, $binding, true);
                $this->assertClaim($token, $merchantId, $binding, $claim);
                // Only the new opaque token is usable. The old claimed token cannot
                // be replayed even if a cache backend fails its best-effort deletion.
                $this->put($nextToken, $next, $merchantId, (int) $next['expires_at']);
                Cache::delete($this->cacheKey($token));
            });
            return ['status' => 'choose_company', 'public' => ['challenge_token' => $nextToken,
                'expires_at' => (int) $next['expires_at'], 'companies' => $companies]];
        } catch (\Throwable $error) {
            $this->invalidate($merchantId, $token, $claim);
            if ($error instanceof ProviderException || $error instanceof CommonException) throw $error;
            // Never chain transport/provider exceptions: they may contain credentials.
            throw new CommonException('ERP授权未完成，请重新获取验证码后再试');
        }
    }

    /** @return array{ProviderDefinition,InteractiveAuthProviderContract,array} */
    private function capability(string $key): array
    {
        $definition = $this->providers->definition($key);
        (new ProviderChannelService($this->context->site))->assertEnabled($definition->key());
        $metadata = $definition->metadata();
        $capability = $metadata['interactive_auth'] ?? null;
        $provider = $this->providers->get($definition->key());
        if (!is_array($capability) || !in_array($capability['type'] ?? null, ['image_captcha', 'sms_code'], true)
            || !$provider instanceof InteractiveAuthProviderContract) {
            throw new CommonException('此ERP不支持交互式验证码授权，请使用对应的接入方式');
        }
        if ($capability['type'] === 'sms_code' && !$provider instanceof CredentialChallengeProviderContract) {
            throw new CommonException('此ERP的短信授权能力尚未就绪，请联系管理员核对');
        }
        return [$definition, $provider, $capability];
    }

    private function identityCredentials(ProviderDefinition $definition, array $capability, #[\SensitiveParameter] mixed $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_array($input)) throw new CommonException('请填写ERP授权账号');
        $keys = $capability['credential_fields'] ?? null;
        $fields = $definition->metadata()['fields'] ?? [];
        if (!is_array($keys) || !\addon\hsx_lead\app\support\PhpRuntime::isList($keys) || !$keys || count($keys) > 10) throw new CommonException('ERP交互授权字段配置异常');
        $declared = [];
        foreach ($fields as $field) if (is_array($field) && is_string($field['key'] ?? null)) $declared[$field['key']] = $field;
        $identity = [];
        foreach ($keys as $key) {
            if (!is_string($key) || !isset($declared[$key]) || !empty($declared[$key]['secret']) || isset($identity[$key])) {
                throw new CommonException('ERP交互授权身份字段配置异常');
            }
            $field = $declared[$key];
            $identity[$key] = Data::text($input, $key, (int) ($field['max_length'] ?? 512), !empty($field['required']));
        }
        ksort($identity);
        return $identity;
    }

    private function binding(int $merchantId, string $provider, #[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $merchant = $this->context->connectionMerchant($merchantId);
        if ((int) $merchant['status'] !== 1) throw new CommonException('请先启用商户再授权ERP');
        return ['site' => $this->context->site, 'uid' => $this->context->uid, 'member_id' => $this->context->memberId, 'merchant_id' => $merchantId, 'provider' => $provider,
            'revision' => $this->revision($merchantId), 'identity_fingerprint' => hash('sha256', Data::json($credentials))];
    }

    private function revision(int $merchantId): string
    {
        $connection = Data::query('connection', $this->context->site)->where('merchant_id', $merchantId)->find();
        return hash('sha256', Data::json($connection ? array_intersect_key($connection,
            array_flip(['id', 'config_version', 'provider', 'expected_tenant_id', 'store_id'])) : []));
    }

    private function assertCurrent(int $merchantId, array $binding, bool $lock): void
    {
        // Follow the shared site -> merchant lock order at commit boundaries.
        (new ProviderChannelService($this->context->site))->assertEnabled((string) $binding['provider'], $lock);
        $merchant = $this->context->connectionMerchant($merchantId, $lock);
        if ((int) $merchant['status'] !== 1) throw new CommonException('商户已暂停，不能继续授权');
        if ($this->revision($merchantId) !== $binding['revision']) throw new CommonException('ERP接入配置已变化，请重新获取验证码');
    }

    private function assertBinding(#[\SensitiveParameter] array $payload, array $binding): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (($payload['purpose'] ?? null) !== self::PURPOSE || (int) ($payload['expires_at'] ?? 0) <= time()) {
            throw new CommonException('授权校验已过期，请重新获取验证码');
        }
        foreach ($binding as $key => $value) if (($payload[$key] ?? null) !== $value) {
            throw new CommonException('授权校验不属于当前账号或操作，请重新获取验证码');
        }
        if (!in_array($payload['phase'] ?? null, ['captcha', 'company'], true) || !is_array($payload['private_state'] ?? null)) {
            throw new CommonException('授权校验状态异常，请重新获取验证码');
        }
    }

    private function answer(#[\SensitiveParameter] array $claim, array $capability, #[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if ($claim['phase'] === 'company') {
            $company = Data::text($input, 'company_id', 100, true);
            $allowed = array_column($claim['companies'] ?? [], 'id');
            if (!in_array($company, $allowed, true)) throw new CommonException('请选择本次授权返回的公司，不能填写其他公司编号');
            return ['company_id' => $company];
        }
        if (isset($input['company_id']) && $input['company_id'] !== '') throw new CommonException('当前还未进入选择公司步骤，请重新授权');
        $answer = [];
        foreach ($capability['fields'] ?? [] as $field) {
            if (!is_array($field) || !is_string($field['key'] ?? null)) throw new CommonException('ERP授权字段配置异常');
            $key = $field['key'];
            $limit = (int) ($field['max_length'] ?? 4096);
            $value = !empty($field['secret']) ? Data::secret($input, $key, $limit) : Data::text($input, $key, $limit);
            if ($value === '' && !empty($field['required'])) throw new CommonException('请填写'.$field['label']);
            $answer[$key] = $value;
        }
        $code = Data::text($input, 'code', 64, true);
        $length = $capability['code_length'] ?? null;
        if (preg_match('/[\x00-\x20\x7f]/', $code) || ($length !== null && (!is_int($length) || $length < 1 || $length > 32 || strlen($code) !== $length))) {
            throw new CommonException(($capability['type'] ?? '') === 'sms_code' ? '请填写本次短信中的验证码' : '请填写本次图片中的验证码');
        }
        if (($capability['type'] ?? '') === 'sms_code' && preg_match('/^[0-9]+$/D', $code) !== 1) throw new CommonException('短信验证码只能包含数字');
        $answer['code'] = $code;
        return $answer;
    }

    private function authorizedCredentials(ProviderDefinition $definition, #[\SensitiveParameter] mixed $result, #[\SensitiveParameter] array $identity): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_array($result)) throw new ProviderException('schema_changed', 'ERP授权未返回有效凭据');
        foreach ($identity as $key => $value) if (($result[$key] ?? null) !== $value) throw new ProviderException('scope_mismatch', 'ERP授权账号与本次身份不一致');
        $token = $result['access_token'] ?? null;
        if (!is_string($token) || strlen($token) > 16384 || preg_match('/^[\x21-\x7e]+$/D', $token) !== 1) {
            throw new ProviderException('schema_changed', 'ERP授权未返回有效凭据');
        }
        return $identity + ['access_token' => $token] + $definition->authorizedServerCredentials($result);
    }

    private function companies(#[\SensitiveParameter] mixed $rows): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_array($rows) || !\addon\hsx_lead\app\support\PhpRuntime::isList($rows) || !$rows || count($rows) > 100) throw new ProviderException('schema_changed', 'ERP未返回可核对的公司列表');
        $safe = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new ProviderException('schema_changed', 'ERP公司列表格式变化');
            $id = Data::text($row, 'id', 100, true);
            $name = Data::text($row, 'name', 120, true);
            if (preg_match('/[\x00-\x1f\x7f]/', $id.$name) || isset($seen[$id])) throw new ProviderException('schema_changed', 'ERP公司列表存在异常或重复');
            $seen[$id] = true;
            $safe[] = ['id' => $id, 'name' => $name];
        }
        return $safe;
    }

    private function image(#[\SensitiveParameter] mixed $value): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_string($value) || strlen($value) > self::MAX_IMAGE_BYTES * 2
            || preg_match('#^data:image/(?:jpeg|png|gif|webp);base64,([A-Za-z0-9+/]+={0,2})$#D', $value, $match) !== 1) {
            throw new ProviderException('schema_changed', 'ERP未返回有效的验证码图片，请重新获取');
        }
        $raw = base64_decode($match[1], true);
        if (!is_string($raw) || $raw === '' || strlen($raw) > self::MAX_IMAGE_BYTES) throw new ProviderException('schema_changed', 'ERP验证码图片大小异常');
        return $value;
    }

    private function boundedState(#[\SensitiveParameter] array $state): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (strlen(Data::json($state)) > self::MAX_STATE_BYTES) throw new ProviderException('schema_changed', 'ERP临时授权状态超过安全大小');
    }

    private function read(#[\SensitiveParameter] string $token, int $merchantId): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $cipher = Cache::get($this->cacheKey($token));
        if (!is_string($cipher) || $cipher === '') throw new CommonException('授权校验已过期或已使用，请重新获取验证码');
        return Cipher::decrypt($cipher, $this->context->site, -$merchantId);
    }

    private function put(#[\SensitiveParameter] string $token, #[\SensitiveParameter] array $payload, int $merchantId, int $expires): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if ($expires <= time()) throw new CommonException('授权校验已过期，请重新获取验证码');
        if (!Cache::set($this->cacheKey($token), Cipher::encrypt($payload, $this->context->site, -$merchantId), max(1, $expires - time()))) {
            throw new CommonException('无法保存临时授权状态，请检查缓存配置');
        }
    }

    private function assertClaim(#[\SensitiveParameter] string $token, int $merchantId, array $binding, #[\SensitiveParameter] array $claim): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $current = $this->read($token, $merchantId);
        $this->assertBinding($current, $binding);
        if (($current['status'] ?? null) !== 'claimed' || !is_string($current['claim_nonce'] ?? null)
            || !hash_equals((string) $claim['claim_nonce'], $current['claim_nonce'])) {
            throw new CommonException('授权校验已变化，请重新获取验证码');
        }
    }

    private function finish(int $merchantId, array $binding, #[\SensitiveParameter] string $token, #[\SensitiveParameter] array $claim): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        Db::transaction(function () use ($merchantId, $binding, $token, $claim) {
            $this->assertCurrent($merchantId, $binding, true);
            $this->assertClaim($token, $merchantId, $binding, $claim);
            Cache::delete($this->cacheKey($token));
        });
    }

    private function invalidate(int $merchantId, #[\SensitiveParameter] string $token, #[\SensitiveParameter] array $claim): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        try {
            Db::transaction(function () use ($merchantId, $token, $claim) {
                Data::row('merchant', $this->context->site, $merchantId, true);
                $current = $this->read($token, $merchantId);
                if (($current['status'] ?? null) === 'claimed' && is_string($current['claim_nonce'] ?? null)
                    && hash_equals((string) $claim['claim_nonce'], $current['claim_nonce'])) Cache::delete($this->cacheKey($token));
            });
        } catch (\Throwable $ignored) { /* A claimed/expired entry is never reusable; TTL remains bounded. */
        }
    }

    private function cacheKey(#[\SensitiveParameter] string $token): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return 'hsx_lead.auth.'.hash('sha256', $token);
    }
    private function rateKey(array $binding): string
    {
        return 'hsx_lead.auth.rate.'.hash('sha256', Data::json(array_intersect_key($binding, array_flip(['site', 'uid', 'member_id', 'merchant_id', 'provider']))));
    }

    /** Cache get/set is not atomic. Serialize SMS claims across all sites and
     * actors in this application, not just one merchant. No HTTP under locks. */
    private function claimRate(int $merchantId, array $binding, string $type): void
    {
        $connection = null;
        $lock = '';
        if ($type === 'sms_code') {
            $connection = Db::connect();
            $scope = (string) $connection->getConfig('database').':'.(string) $connection->getConfig('prefix');
            $lock = 'lead_sms_auth:'.substr(hash('sha256', $scope.':'.$binding['provider'].':'.$binding['identity_fingerprint']), 0, 40);
            if ((int) ($connection->query('SELECT GET_LOCK(?,3) AS acquired', [$lock], true)[0]['acquired'] ?? 0) !== 1) {
                throw new CommonException('短信验证码正在申请，请稍后再试，不要重复点击');
            }
        }
        try {
            Db::transaction(function () use ($merchantId, $binding, $type): void {
                $this->assertCurrent($merchantId, $binding, true);
                $now = time();
                if ($type === 'sms_code') {
                    $key = 'hsx_lead.auth.sms.'.hash('sha256', Data::json(['provider' => $binding['provider'], 'identity_fingerprint' => $binding['identity_fingerprint']]));
                    $rate = Cache::get($key);
                    if (is_array($rate) && (int) ($rate['next_at'] ?? 0) > $now) throw new CommonException('请稍候再获取短信验证码；上次结果尚未确认时也不会自动重发');
                    if (!is_array($rate) || (int) ($rate['reset_at'] ?? 0) <= $now) $rate = ['count' => 0, 'reset_at' => $now + self::SMS_HOUR_SECONDS, 'next_at' => 0];
                    if ((int) ($rate['count'] ?? 0) >= self::SMS_MAX_HOURLY) throw new CommonException('该手机号本小时验证码申请次数已达上限，请稍后再试');
                    $rate['count'] = (int) ($rate['count'] ?? 0) + 1;
                    $rate['next_at'] = $now + self::RATE_SECONDS;
                    $ttl = max(1, max((int) $rate['reset_at'], (int) $rate['next_at']) - $now);
                } else {
                    $key = $this->rateKey($binding);
                    $rate = Cache::get($key);
                    if (!is_array($rate) || (int) ($rate['reset_at'] ?? 0) <= $now) $rate = ['count' => 0, 'reset_at' => $now + self::RATE_SECONDS];
                    if ((int) ($rate['count'] ?? 0) >= self::MAX_STARTS) throw new CommonException('验证码获取过于频繁，请稍后再试');
                    $rate['count'] = (int) $rate['count'] + 1;
                    $ttl = max(1, (int) $rate['reset_at'] - $now);
                }
                if (!Cache::set($key, $rate, $ttl)) throw new CommonException('无法保存授权校验状态，请检查缓存配置');
            });
        } finally {
            if ($connection !== null) try {
                $connection->query('SELECT RELEASE_LOCK(?) AS released', [$lock], true);
            } catch (\Throwable $ignored) { /* released on disconnect */
            }
        }
    }

    private function mobileMask(#[\SensitiveParameter] array $credentials): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $mobile = $credentials['username'] ?? null;
        if (!is_string($mobile) || preg_match('/^\+?[0-9]{7,20}$/D', $mobile) !== 1) throw new CommonException('请填写接收短信验证码的有效手机号');
        return substr($mobile, 0, 3).'****'.substr($mobile, -4);
    }
}
