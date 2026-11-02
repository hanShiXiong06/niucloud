<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\provider;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;

/** One source of truth for field validation, auth renewal and public onboarding capabilities. */
final class ProviderDefinition
{
    public function __construct(private array $definition) {}
    public function key(): string { return $this->definition['key']; }
    public function adapter(?TransportContract $transport=null): ProviderContract
    {
        $class=$this->definition['adapter'];
        return new $class($transport);
    }
    public function matches(string $key): bool { return $key===$this->key() || in_array($key,$this->definition['aliases']??[],true); }
    public function metadata(): array
    {
        return array_intersect_key($this->definition,array_flip(['key','name','auth_mode','auth_type','auth_modes','fields','scope_fields','renewal','notes','renderer','read_only','interactive_auth']));
    }
    public function renewable(#[\SensitiveParameter] array $credentials=[]): bool
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return (bool)($this->configuration($credentials)['renewal']['automatic']??false);
    }
    /** Opt-in only for adapters that read authenticated profile/HTML, never JWT claims. */
    public function verifiesIdentity(): bool { return ($this->definition['verified_identity']??false)===true; }
    public function verifiedTenantRequired(): bool { return empty($this->definition['identity_tenant_optional']); }
    public function authMode(#[\SensitiveParameter] array $credentials=[]): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (empty($this->definition['auth_modes'])) return (string)$this->definition['auth_mode'];
        $mode=Data::text($credentials,'auth_mode',32);
        if ($mode==='') {
            // Preserve explicit Token testing and existing encrypted Token connections.
            // Any account/password field takes precedence; incomplete passwords
            // must fail validation rather than falling back to a supplied Token.
            $mode=array_key_exists('username',$credentials)||array_key_exists('password',$credentials)
                ? 'password' : (array_key_exists('access_token',$credentials) ? 'token' : (string)$this->definition['auth_mode']);
        }
        foreach($this->definition['auth_modes'] as $candidate) if ($candidate['key']===$mode) return $mode;
        throw new CommonException('请选择当前ERP支持的登录方式');
    }
    private function configuration(#[\SensitiveParameter] array $credentials=[]): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (empty($this->definition['auth_modes'])) return $this->definition;
        $mode=$this->authMode($credentials);
        foreach($this->definition['auth_modes'] as $candidate) if ($candidate['key']===$mode) return array_replace($this->definition,$candidate);
        throw new CommonException('当前ERP登录方式配置无效');
    }
    public function loginRequired(#[\SensitiveParameter] array $credentials,int $pageCount): bool
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!empty($this->definition['verify_before_sync']) && $pageCount===0) return true;
        if (!$this->renewable($credentials)) return false;
        $expires=(int)($credentials['expires_at']??0);
        return empty($credentials['access_token']) || ($expires>0 && $expires<time()+60) || ($expires<=0 && $pageCount===0);
    }
    public function grade(array $safe): string
    {
        $value=$safe[$this->definition['grade_field']]??'';
        return is_scalar($value)?mb_substr((string)$value,0,100):'';
    }
    public function storeName(array $safe): string
    {
        $value=$safe[$this->definition['store_name_field']??'']??'';
        return is_scalar($value)?mb_substr((string)$value,0,120):'';
    }

    /** Missing/empty secrets may be reused only when the explicit non-secret identity is unchanged. */
    public function credentials(#[\SensitiveParameter] array $input,#[\SensitiveParameter] array $saved=[]): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $config=$this->configuration($input);
        $multi=!empty($this->definition['auth_modes']);
        $result=$multi?['auth_mode'=>$this->authMode($input)]:[];
        $reuse=!$multi || $saved===[] || $this->authMode($saved)===$result['auth_mode'];
        foreach($config['fields'] as $field) {
            if ($field['secret']) continue;
            $key=$field['key'];
            $result[$key]=Data::text($input,$key,$field['max_length'],!empty($field['required']));
            if (!empty($field['identity']) && $result[$key]!==($saved[$key]??'')) $reuse=false;
        }
        foreach($config['fields'] as $field) {
            if (!$field['secret']) continue;
            $key=$field['key']; $value=Data::secret($input,$key,$field['max_length']);
            if ($value==='' && $reuse) $value=(string)($saved[$key]??'');
            if ($value==='' && !empty($field['required'])) throw new CommonException('请填写'.$field['label'].'；更换账号时不能沿用原密钥');
            $result[$key]=$value;
        }
        return $result;
    }
    public function scope(#[\SensitiveParameter] array $input,#[\SensitiveParameter] array $credentials=[]): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $scope=[];
        foreach($this->configuration($credentials)['scope_fields'] as $field) $scope[$field['key']]=Data::text($input,$field['key'],$field['max_length'],!empty($field['required']));
        return $scope+['tenant_id'=>'','store_id'=>''];
    }
    public function publicCredentials(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $values=empty($this->definition['auth_modes'])?[]:['auth_mode'=>$this->authMode($credentials)]; $configured=[];
        foreach($this->configuration($credentials)['fields'] as $field) {
            $key=$field['key'];
            if (isset($credentials[$key]) && $credentials[$key]!=='') $configured[]=$key;
            if (!$field['secret']) $values[$key]=(string)($credentials[$key]??'');
        }
        return ['credentials'=>$values,'configured_fields'=>$configured];
    }
    public function identityFingerprint(#[\SensitiveParameter] array $credentials): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $identity=empty($this->definition['auth_modes'])?[]:['auth_mode'=>$this->authMode($credentials)];
        foreach($this->configuration($credentials)['fields'] as $field) if (!empty($field['identity'])) $identity[$field['key']]=$credentials[$field['key']]??'';
        return hash('sha256',Data::json($identity));
    }
}
