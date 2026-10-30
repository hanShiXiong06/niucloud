<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\provider;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;

/** One source of truth for field validation, auth renewal and public onboarding capabilities. */
final class ProviderDefinition
{
    public function __construct(private readonly array $definition) {}
    public function key(): string { return $this->definition['key']; }
    public function adapter(?TransportContract $transport=null): ProviderContract
    {
        $class=$this->definition['adapter'];
        return new $class($transport);
    }
    public function matches(string $key): bool { return $key===$this->key() || in_array($key,$this->definition['aliases']??[],true); }
    public function metadata(): array
    {
        return array_intersect_key($this->definition,array_flip(['key','name','auth_mode','auth_type','fields','scope_fields','renewal','notes','renderer','read_only']));
    }
    public function renewable(): bool { return (bool)$this->definition['renewal']['automatic']; }
    public function loginRequired(#[\SensitiveParameter] array $credentials,int $pageCount): bool
    {
        if (!$this->renewable()) return false;
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
        $result=[]; $reuse=true;
        foreach($this->definition['fields'] as $field) {
            if ($field['secret']) continue;
            $key=$field['key'];
            $result[$key]=Data::text($input,$key,$field['max_length'],!empty($field['required']));
            if (!empty($field['identity']) && $result[$key]!==($saved[$key]??'')) $reuse=false;
        }
        foreach($this->definition['fields'] as $field) {
            if (!$field['secret']) continue;
            $key=$field['key']; $value=Data::secret($input,$key,$field['max_length']);
            if ($value==='' && $reuse) $value=(string)($saved[$key]??'');
            if ($value==='' && !empty($field['required'])) throw new CommonException('请填写'.$field['label'].'；更换账号时不能沿用原密钥');
            $result[$key]=$value;
        }
        return $result;
    }
    public function scope(#[\SensitiveParameter] array $input): array
    {
        $scope=[];
        foreach($this->definition['scope_fields'] as $field) $scope[$field['key']]=Data::text($input,$field['key'],$field['max_length'],!empty($field['required']));
        return $scope+['tenant_id'=>'','store_id'=>''];
    }
    public function publicCredentials(#[\SensitiveParameter] array $credentials): array
    {
        $values=[]; $configured=[];
        foreach($this->definition['fields'] as $field) {
            $key=$field['key'];
            if (isset($credentials[$key]) && $credentials[$key]!=='') $configured[]=$key;
            if (!$field['secret']) $values[$key]=(string)($credentials[$key]??'');
        }
        return ['credentials'=>$values,'configured_fields'=>$configured];
    }
    public function identityFingerprint(#[\SensitiveParameter] array $credentials): string
    {
        $identity=[];
        foreach($this->definition['fields'] as $field) if (!empty($field['identity'])) $identity[$field['key']]=$credentials[$field['key']]??'';
        return hash('sha256',Data::json($identity));
    }
}
