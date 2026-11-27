<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\provider;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;

/** One source of truth for field validation, auth renewal and public onboarding capabilities. */
final class ProviderDefinition
{
    public function __construct(private array $definition)
    {
    }
    public function key(): string
    {
        return $this->definition['key'];
    }
    public function adapter(?TransportContract $transport = null): ProviderContract
    {
        $class = $this->definition['adapter'];
        return new $class($transport);
    }
    public function matches(string $key): bool
    {
        return $key === $this->key() || in_array($key, $this->definition['aliases'] ?? [], true);
    }
    public function metadata(): array
    {
        return array_intersect_key($this->definition, array_flip([
                    'key',
                    'name',
                    'auth_mode',
                    'auth_type',
                    'auth_modes',
                    'fields',
                    'scope_fields',
                    'renewal',
                    'notes',
                    'renderer',
                    'read_only',
                    'interactive_auth'
                ]));
    }
    public function renewable(#[\SensitiveParameter] array $credentials = []): bool
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return (bool) ($this->configuration($credentials)['renewal']['automatic'] ?? false);
    }
    /** Public capability only: never expose account, password or derived token. */
    public function renewalStatus(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $configuration = $this->configuration($credentials);
        $mode = (string) ($configuration['renewal']['mode'] ?? 'manual_token');
        $automatic = (bool) ($configuration['renewal']['automatic'] ?? false);
        if (!$automatic) {
            $interactive = $mode === 'interactive_login';
            $sms = ($this->definition['interactive_auth']['type'] ?? '') === 'sms_code';
            return ['automatic' => false, 'ready' => false, 'mode' => $mode,
                'status' => $interactive ? 'interactive_required' : 'manual_token',
                'message' => $interactive ? ($sms ? '该ERP需要短信验证码授权；授权失效后请重新收取验证码，不会自动发送短信或尝试密码续登。' : '该ERP需要验证码登录；当前会话失效后需重新验证，不能仅凭密码自动续登。') : '当前仅保存临时Token，无法自动续登；请切换为账号密码登录并验证启用。'];
        }
        $complete = true;
        foreach ($configuration['fields'] as $field) {
            if (!empty($field['required']) && (!is_string($credentials[$field['key']] ?? null) || $credentials[$field['key']] === '')) $complete = false;
        }
        return ['automatic' => true, 'ready' => $complete, 'mode' => $mode, 'status' => $complete ? 'automatic' : 'credentials_required',
            'message' => $complete ? '已保存登录资料；授权失效时自动重新登录、保存新授权并继续同步。' : '登录资料不完整，无法自动续登；请补齐账号密码并验证启用。'];
    }
    /** Opt-in only for adapters that read authenticated profile/HTML, never JWT claims. */
    public function verifiesIdentity(): bool
    {
        return ($this->definition['verified_identity'] ?? false) === true;
    }
    public function verifiedTenantRequired(): bool
    {
        return empty($this->definition['identity_tenant_optional']);
    }
    public function authMode(#[\SensitiveParameter] array $credentials = []): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (empty($this->definition['auth_modes'])) return (string) $this->definition['auth_mode'];
        $mode = Data::text($credentials, 'auth_mode', 32);
        if ($mode === '') {
            // Preserve explicit Token testing and existing encrypted Token connections.
            // Any account/password field takes precedence; incomplete passwords
            // must fail validation rather than falling back to a supplied Token.
            $mode = array_key_exists('username', $credentials) || array_key_exists('password', $credentials)
                ? 'password' : (array_key_exists('access_token', $credentials) ? 'token' : (string) $this->definition['auth_mode']);
        }
        foreach ($this->definition['auth_modes'] as $candidate) if ($candidate['key'] === $mode) return $mode;
        throw new CommonException('请选择当前ERP支持的登录方式');
    }
    private function configuration(#[\SensitiveParameter] array $credentials = []): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (empty($this->definition['auth_modes'])) return $this->definition;
        $mode = $this->authMode($credentials);
        foreach ($this->definition['auth_modes'] as $candidate) if ($candidate['key'] === $mode) return array_replace($this->definition, $candidate);
        throw new CommonException('当前ERP登录方式配置无效');
    }
    public function loginRequired(#[\SensitiveParameter] array $credentials, int $pageCount): bool
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!empty($this->definition['verify_before_sync']) && $pageCount === 0) return true;
        if (!$this->renewable($credentials)) return false;
        $expires = (int) ($credentials['expires_at'] ?? 0);
        return empty($credentials['access_token']) || ($expires > 0 && $expires < time() + 60) || ($expires <= 0 && $pageCount === 0);
    }
    public function grade(array $safe): string
    {
        $value = $safe[$this->definition['grade_field']] ?? '';
        return is_scalar($value) ? mb_substr((string) $value, 0, 100) : '';
    }
    public function storeName(array $safe): string
    {
        $value = $safe[$this->definition['store_name_field'] ?? ''] ?? '';
        return is_scalar($value) ? mb_substr((string) $value, 0, 120) : '';
    }

    /** Missing/empty secrets may be reused only when the explicit non-secret identity is unchanged. */
    public function credentials(#[\SensitiveParameter] array $input, #[\SensitiveParameter] array $saved = [], bool $trustedAuthorization = false): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $config = $this->configuration($input);
        $serverFields = $this->serverCredentialFields();
        if (!$trustedAuthorization) foreach ($serverFields as $field) {
            if (array_key_exists($field['key'], $input)) throw new CommonException('ERP认证资料由授权服务核验，不能手工提交或修改；请重新完成验证码授权');
        }
        $multi = !empty($this->definition['auth_modes']);
        $result = $multi ? ['auth_mode' => $this->authMode($input)] : [];
        $reuse = !$multi || $saved === [] || $this->authMode($saved) === $result['auth_mode'];
        foreach ($config['fields'] as $field) {
            if ($field['secret']) continue;
            $key = $field['key'];
            $result[$key] = Data::text($input, $key, $field['max_length'], !empty($field['required']));
            if (!empty($field['identity']) && $result[$key] !== ($saved[$key] ?? '')) $reuse = false;
        }
        foreach ($config['fields'] as $field) {
            if (!$field['secret']) continue;
            $key = $field['key'];
            $value = Data::secret($input, $key, $field['max_length']);
            if ($value === '' && $reuse) $value = (string) ($saved[$key] ?? '');
            if ($value === '' && !empty($field['required'])) throw new CommonException('请填写'.$field['label'].'；更换账号时不能沿用原密钥');
            $result[$key] = $value;
        }
        if ($serverFields) {
            $sameSecrets = $reuse;
            foreach ($config['fields'] as $field) if (!empty($field['secret'])
                && ($result[$field['key']] ?? '') !== ($saved[$field['key']] ?? '')) $sameSecrets = false;
            if (!$trustedAuthorization && !$sameSecrets) {
                foreach ($serverFields as $field) if (!empty($field['required'])) throw new CommonException('请先完成当前ERP的验证码授权；更换账号或授权Token后不能沿用原认证资料');
            }
            $result += $this->authorizedServerCredentials($trustedAuthorization ? $input : ($sameSecrets ? $saved : []));
        }
        return $result;
    }

    /** Only callers holding an actual provider authorization result may use
     * these fields. They are deliberately absent from all public metadata. */
    public function authorizedServerCredentials(#[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $safe = [];
        foreach ($this->serverCredentialFields() as $field) {
            $key = $field['key'];
            $required = !empty($field['required']);
            $limit = (int) ($field['max_length'] ?? 512);
            $value = !empty($field['secret']) ? Data::secret($input, $key, $limit) : Data::text($input, $key, $limit, $required);
            if ($required && $value === '') throw new CommonException('ERP授权未返回完整认证资料，请重新验证');
            if ($value !== '') $safe[$key] = $value;
        }
        return $safe;
    }

    private function serverCredentialFields(): array
    {
        $fields = $this->definition['server_credential_fields'] ?? [];
        if (!is_array($fields) || !\addon\hsx_lead\app\support\PhpRuntime::isList($fields) || count($fields) > 20) throw new CommonException('ERP服务端认证字段配置异常');
        $seen = array_fill_keys(['auth_mode', 'access_token', 'token', 'username', 'password', 'code', 'captcha'], true);
        foreach (array_merge([$this->definition], $this->definition['auth_modes'] ?? []) as $config) foreach ($config['fields'] ?? [] as $field) if (is_array($field)
            && is_string($field['key'] ?? null))$seen[$field['key']] = true;
        foreach ($fields as $field) {
            if (!is_array($field) || !is_string($field['key'] ?? null) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $field['key']) !== 1
                || isset($seen[$field['key']])
                    || !is_int($field['max_length'] ?? null)
                    || $field['max_length'] < 1
                    || $field['max_length'] > 16384) throw new CommonException('ERP服务端认证字段配置异常');
            $seen[$field['key']] = true;
        }
        return $fields;
    }
    public function scope(#[\SensitiveParameter] array $input, #[\SensitiveParameter] array $credentials = []): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $scope = [];
        foreach ($this->configuration($credentials)['scope_fields'] as $field) $scope[$field['key']] = Data::text($input, $field['key'], $field['max_length'], !empty($field['required']));
        return $scope + ['tenant_id' => '', 'store_id' => ''];
    }
    public function publicCredentials(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $values = empty($this->definition['auth_modes']) ? [] : ['auth_mode' => $this->authMode($credentials)];
        $configured = [];
        foreach ($this->configuration($credentials)['fields'] as $field) {
            $key = $field['key'];
            if (isset($credentials[$key]) && $credentials[$key] !== '') $configured[] = $key;
            if (!$field['secret']) $values[$key] = (string) ($credentials[$key] ?? '');
        }
        return ['credentials' => $values, 'configured_fields' => $configured];
    }
    public function identityFingerprint(#[\SensitiveParameter] array $credentials): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $identity = empty($this->definition['auth_modes']) ? [] : ['auth_mode' => $this->authMode($credentials)];
        foreach ($this->configuration($credentials)['fields'] as $field) if (!empty($field['identity'])) $identity[$field['key']] = $credentials[$field['key']] ?? '';
        return hash('sha256', Data::json($identity));
    }
}
