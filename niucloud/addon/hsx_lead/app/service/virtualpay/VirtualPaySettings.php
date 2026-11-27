<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\virtualpay;

use addon\hsx_lead\app\support\Data;
use app\service\core\weapp\{CoreWeappAuthService,CoreWeappConfigService,CoreWeappMessageSecurity,CoreWeappService};
use core\exception\CommonException;
use think\facade\Db;

/** Credentials/configuration use the existing cashier channel; transactions do not. */
final class VirtualPaySettings
{
    public const TYPE='hsx_lead_virtualpay';
    public const CLIENT_ACTION='wechat_virtual_payment';

    public static function get(int $site,bool $requireEnabled=true): array
    {
        if($site<=0)throw new CommonException('支付所属站点无效');
        $rows=Db::name('pay_channel')->where('site_id',$site)->where('channel','weapp')->where('type',self::TYPE)->limit(2)->select()->toArray();
        if(count($rows)>1)throw new CommonException('会员微信支付配置不唯一，请平台核对');
        $row=$rows[0]??[];$raw=Data::decode($row['config']??'');
        $mode=$raw['payment_mode']??'standard';
        if(!in_array($mode,['standard','virtual'],true))throw new CommonException('会员微信支付方式配置异常');
        $env=$raw['env']??0;
        if(!in_array($env,[0,1,'0','1'],true))throw new CommonException('虚拟支付环境标识无效');
        $settings=['enabled'=>(int)($row['status']??0)===1,'payment_mode'=>$mode,'env'=>(int)$env,
            'offer_id'=>trim((string)($raw['offer_id']??'')),'app_key'=>trim((string)($raw[(int)$env===1?'sandbox_app_key':'app_key']??'')),
            'app_id'=>'','products'=>is_array($raw['products']??null)?$raw['products']:[],
            'allow_deposit'=>self::flag($raw['allow_deposit']??false),'allow_experience'=>self::flag($raw['allow_experience']??false)];
        // Never let a payment form override the app belonging to this tenant.
        if($row){$app=(new CoreWeappConfigService())->getWeappConfig($site);$settings['app_id']=(string)($app['app_id']??'');}
        if($requireEnabled){
            if(!$settings['enabled']||$mode!=='virtual')throw new CommonException('当前业务未启用微信支付，请稍后重试');
            if(!preg_match('/^wx[A-Za-z0-9]{16}$/D',$settings['app_id'])||!preg_match('/^[0-9]{1,32}$/D',$settings['offer_id'])
                ||strlen($settings['app_key'])<16||strlen($settings['app_key'])>256)
                throw new CommonException('微信支付参数尚未配置完整，请联系平台核对');
            $security=CoreWeappMessageSecurity::configuration($site);
            if(empty($security['encrypted_ready']))throw new CommonException('请先在牛云小程序消息配置中完成安全模式配置；支付设置无需重复填写 Token 或 AESKey');
        }
        return $settings;
    }

    /** Fresh code is consumed on the server only; session_key is never persisted/returned. */
    public static function session(int $site,string $loginCode): array
    {
        if($loginCode===''||strlen($loginCode)>256||preg_match('/[\s\x00-\x1f]/',$loginCode))throw new CommonException('请重新点击微信支付获取登录凭据');
        try{$result=(new CoreWeappAuthService())->session($site,$loginCode);}catch(\Throwable $error){throw new CommonException('微信身份暂未核实，请重新点击付款');}
        if(!is_array($result)||!is_string($result['openid']??null)||$result['openid']===''||!is_string($result['session_key']??null)||$result['session_key']==='')throw new CommonException('微信身份暂未核实，请重新点击付款');
        return ['openid'=>$result['openid'],'session_key'=>$result['session_key']];
    }
    public static function accessToken(int $site): string
    {
        try{$token=CoreWeappService::app($site)->getAccessToken()->getToken();}catch(\Throwable $error){throw new CommonException('微信支付服务暂不可用，请稍后核对原订单');}
        if(!is_string($token)||$token==='')throw new CommonException('微信支付服务暂不可用，请稍后核对原订单');return $token;
    }
    private static function flag($value): bool
    {
        if(!in_array($value,[true,false,0,1,'0','1'],true))throw new CommonException('微信支付开关配置无效');return in_array($value,[true,1,'1'],true);
    }
}
