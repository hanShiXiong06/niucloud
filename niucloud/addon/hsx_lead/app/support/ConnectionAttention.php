<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

use addon\hsx_lead\app\provider\ProviderDefinition;

/** Shared read-only guidance for a connection detail and one batch-list row.
 * No HTTP, login, task creation, credential persistence or SQL belongs here. */
final class ConnectionAttention
{
    private const REAUTHORIZE=['auth_required','invalid_credentials','invalid_captcha','invalid_challenge','auth_challenge','captcha_invalid_request'];
    private const RETRY=['http_error','upstream_error','rate_limited','generation_expired','sync_interrupted'];

    public static function project(array $view,ProviderDefinition $definition,#[\SensitiveParameter] ?array $credentials,bool $credentialError=false): array
    {
        $secretTrace=PhpRuntime::guardSecrets();
        $out=['sync_attention'=>null,'authorization_attention'=>false];
        if (($view['connected']??false)!==true) return $out;
        if (($view['channel_enabled']??true)!==true) {
            $out['last_sync_status']='paused';$out['last_sync_message']=ProviderPublicMessage::forReason('channel_disabled');$out['sync_error_code']='channel_disabled';
            $out['sync_attention']=self::attention('channel_disabled','contact_support');return $out;
        }
        if ($credentialError) {
            $out['authorization_attention']=true;$out['sync_attention']=self::attention('credential_error','contact_support');return $out;
        }
        $status=$view['last_sync_status']??'';
        $code=in_array($status,['failed','cancelled'],true)?ProviderPublicMessage::safeReason($view['sync_error_code']??null,$status==='failed'?'sync_failed':''):'';
        if (in_array($code,self::REAUTHORIZE,true)) return self::reauthorize($definition,$code);
        // Expiry alone does not interrupt password adapters that can renew on
        // the next run. A terminal rejected renewal above still needs action.
        $expires=$credentials['expires_at']??0;
        $validExpiry=(is_int($expires)||is_string($expires))&&preg_match('/^[1-9][0-9]{0,10}$/D',(string)$expires)===1;
        if ($credentials!==null && !$definition->renewable($credentials) && $validExpiry && (int)$expires<=time()) return self::reauthorize($definition,'authorization_expired');
        if ($code!=='') {$out['sync_attention']=self::attention($code,in_array($code,self::RETRY,true)?'retry':'contact_support');return $out;}
        if (($view['requires_sync_restart']??false)===true || ($view['last_sync_status']??'')==='interrupted') $out['sync_attention']=self::attention('sync_interrupted','retry');
        return $out;
    }

    private static function reauthorize(ProviderDefinition $definition,string $code): array
    {
        $sms=($definition->metadata()['interactive_auth']['type']??'')==='sms_code';
        $message=$sms?'ERP 短信授权已失效或未通过验证，请重新获取短信验证码完成授权；已有完整库存快照仍保留。':ProviderPublicMessage::forReason($code);
        return ['authorization_attention'=>true,'sync_attention'=>['code'=>$code,'message'=>$message,'action'=>'reauthorize','action_label'=>$sms?'重新获取验证码':'重新验证账号']];
    }

    private static function attention(string $code,string $action): array
    {
        return ['code'=>$code,'message'=>ProviderPublicMessage::forReason($code),'action'=>$action,'action_label'=>$action==='retry'?'重试同步':'联系平台处理'];
    }
}
