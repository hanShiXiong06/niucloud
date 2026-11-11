<?php
declare(strict_types=1);
namespace app\service\core\weapp;

use app\service\core\wxoplatform\CoreOplatformConfigService;
use app\dict\sys\ConfigKeyDict;
use app\model\sys\SysConfig;
use core\exception\CommonException;

/** Native message configuration health only. No secret is returned, no token
 * is refreshed, and no separate business callback configuration is created. */
final class CoreWeappMessageSecurity
{
    public static function configuration(int $site): array
    {
        if($site<=0)throw new CommonException('小程序消息所属站点无效');
        $service=new CoreWeappConfigService();$weapp=$service->getWeappConfig($site);
        $appId=(string)($weapp['app_id']??'');$original=trim((string)($weapp['weapp_original']??''));
        $authorized=!empty($weapp['is_authorization']);
        if($authorized){
            $authorization=$service->getWeappAuthorizationInfo($site);
            if($original===''&&is_array($authorization))$original=trim((string)($authorization['authorizer_info']['user_name']??''));
            $native=(new CoreOplatformConfigService())->getConfig();
            $token=$native['token']??'';$aes=$native['aes_key']??'';$secret=$native['app_secret']??'';$mode='safe';$source='open_platform';
            $component=(string)($native['app_id']??'');
        }else{
            $token=$weapp['token']??'';$aes=$weapp['encoding_aes_key']??'';$secret=$weapp['app_secret']??'';
            $mode=(string)($weapp['encryption_type']??'not_encrypt');$source='self_hosted';$component='';
        }
        $ready=preg_match('/^wx[A-Za-z0-9]{16}$/D',$appId)===1
            &&is_string($secret)&&$secret!==''
            &&is_string($token)&&$token!==''&&strlen($token)<=256&&!preg_match('/[\x00-\x20\x7f]/',$token)
            &&is_string($aes)&&preg_match('/^[A-Za-z0-9+\/]{43}$/D',$aes)===1
            &&in_array($mode,['safe','compatible'],true)
            &&(!$authorized||preg_match('/^wx[A-Za-z0-9]{16}$/D',$component)===1);
        return ['app_id'=>$appId,'original_id'=>$original,'source'=>$source,'component_app_id'=>$component,'encryption_type'=>$mode,
            'encrypted_ready'=>$ready,'scope_binding_ready'=>!$authorized||$original!=='',
            'message'=>$ready?($authorized?'沿用牛云开放平台消息配置；授权消息须核实所属小程序':'沿用牛云小程序消息安全配置'):'请先在牛云小程序或开放平台配置中完善消息安全模式、Token 与 EncodingAESKey'];
    }

    /** Candidate lookup only narrows the native configuration rows; unlike
     * the legacy LIKE-first lookup, exact ownership must be unique. */
    public static function uniqueAuthorizedSite(string $appId): int
    {
        if(preg_match('/^wx[A-Za-z0-9]{16}$/D',$appId)!==1)return 0;
        $rows=(new SysConfig())->where([
            ['config_key','=',ConfigKeyDict::WEAPP],
            ['value','like','%'.$appId.'%']
        ])->field('site_id,value')->select()->toArray();
        $sites=[];
        foreach($rows as$row){
            $value=$row['value']??[];
            if(is_string($value))$value=json_decode($value,true);
            if(!is_array($value)||empty($value['is_authorization'])||!is_string($value['app_id']??null)||!hash_equals($appId,$value['app_id']))continue;
            $site=(int)($row['site_id']??0);
            if($site>0)$sites[$site]=true;
        }
        return count($sites)===1?(int)array_key_first($sites):0;
    }
}
