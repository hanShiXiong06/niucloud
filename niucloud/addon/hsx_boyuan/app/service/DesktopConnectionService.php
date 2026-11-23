<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\gateway\HttpTransport;
use addon\hsx_boyuan\app\support\Problem;

/** Narrow desktop editor. The existing connection store owns encryption, locking and audit. */
final class DesktopConnectionService
{
    private const HOSTS = ['test'=>'open-api-uat.139erp.com', 'production'=>'open-api.139erp.com'];

    public static function view(array $config): array
    {
        $profiles=[];
        foreach (self::HOSTS as $mode=>$host) {
            $p=$config['profiles'][$mode];
            $profiles[$mode]=array_intersect_key($p,array_flip(['protocol','endpoint','gateway_path','account_id','app_id','app_secret_configured','connection_key','probe_at','contract_confirmed','party_phone_field']));
            $profiles[$mode]['suggested_url']='https://'.$host.'/api';
        }
        return ['mode'=>$config['mode'],'revision'=>(int)$config['revision'],'profiles'=>$profiles,'active_key'=>(string)$config['profiles'][$config['mode']]['connection_key'],
            'interface_trace'=>ConfigService::traceState($config),'dispatching'=>!empty($config['enabled']) && empty($config['paused']),
            'finance_active'=>!empty($config['uat_payment_enabled']) || !empty($config['finance_enabled'])];
    }

    public static function endpoint(string $mode,string $url): string
    {
        $url=rtrim(trim($url),'/');
        if (substr($url,-11)==='/gateway.do') $url=substr($url,0,-11);
        $url=HttpTransport::endpoint($url);
        if (!isset(self::HOSTS[$mode]) || parse_url($url,PHP_URL_HOST)!==self::HOSTS[$mode])
            throw new Problem('ENDPOINT_ENVIRONMENT','请使用当前环境的博远开放平台地址，测试和正式地址不能混用');
        return $url;
    }

    public static function changes(array $config,array $in): array
    {
        $mode=$in['mode']??''; $account=trim((string)($in['account_id']??'')); $app=trim((string)($in['app_id']??''));
        if ((int)($in['revision']??-1)!==(int)$config['revision']) throw new Problem('CONFIG_STALE','设置已被其他人修改，请重新载入后核对');
        if (!isset(self::HOSTS[$mode])) throw new Problem('MODE_INVALID','请选择博远开放平台环境');
        if ($config['enabled'] && !$config['paused']) throw new Problem('PAUSE_REQUIRED','请先暂停单据同步，再修改连接资料');
        $p=$config['profiles'][$mode];
        if ($p['protocol']!=='openapi_v153') throw new Problem('PROTOCOL_INVALID','当前使用兼容网关，不能用开放平台凭据覆盖');
        $endpoint=self::endpoint($mode,(string)($in['endpoint']??''));
        if ($account==='' || $app==='') throw new Problem('AUTH_INVALID','请填写账套代码和 AppID');
        $identity=$app!==$p['app_id'] || $account!==$p['account_id'] || $endpoint!==$p['endpoint'];
        $switch=$config['mode']!==$mode && ($config['mode']!=='mock' || $mode==='production');
        $secret=(string)($in['app_secret']??'');
        if (($identity || !$p['app_secret_configured']) && $secret==='') throw new Problem('APP_SECRET_REQUIRED','请填写与该地址、AppID 和账套配套的 AppSecret');
        if (($switch || ($identity && $p['connection_key']!=='')) && ($in['confirm_target']??'')!==$account)
            throw new Problem('TARGET_CONFIRM_REQUIRED','请确认将要使用的环境和账套');
        if (($config['uat_payment_enabled'] || $config['finance_enabled']) && ($identity || $switch || $secret!==''))
            throw new Problem('FINANCE_ACTIVE','有财务同步开关在用，不能在连接表单更换账套或密钥');
        $profile=['endpoint'=>$endpoint,'app_id'=>$app,'account_id'=>$account,'contract_confirmed'=>filter_var($in['contract_confirmed']??false,FILTER_VALIDATE_BOOLEAN)];
        if(isset($in['party_phone_field'])) $profile['party_phone_field']=$in['party_phone_field'];
        if ($secret!=='') $profile['app_secret']=$secret;
        return ['revision'=>$config['revision'],'mode'=>$mode,'profile'=>$profile];
    }

    public static function assertProbeTarget(array $config,array $expected): void
    {
        $mode=$config['mode']; $p=$config['profiles'][$mode];
        if (!isset(self::HOSTS[$mode]) || $mode!==($expected['mode']??'') || (int)$config['revision']!==(int)($expected['revision']??-1)
            || $p['connection_key']==='' || $p['connection_key']!==($expected['connection_key']??''))
            throw new Problem('CONFIG_STALE','账套或连接已改变，请重新载入后核验');
        if ($p['protocol']!=='openapi_v153') throw new Problem('PROTOCOL_INVALID','当前不是博远开放平台连接');
        self::endpoint($mode,$p['endpoint']);
    }
}
