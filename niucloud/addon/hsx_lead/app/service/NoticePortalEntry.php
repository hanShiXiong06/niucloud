<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use think\facade\Db;

/** Explicit-site portal links for workers; never fall back to a request's localhost. */
final class NoticePortalEntry
{
    public static function get(int $site): array
    {
        $page='addon/hsx_lead/pages/workbench/index';
        $custom=(string)(Db::name('site')->where('site_id',$site)->value('site_domain')??'');
        $base=$custom!==''?(str_contains($custom,'://')?$custom:'https://'.$custom):(string)env('system.wap_domain','');
        $parts=parse_url($base); $host=strtolower((string)($parts['host']??''));
        $ip=filter_var($host,FILTER_VALIDATE_IP);
        $safe=filter_var($base,FILTER_VALIDATE_URL)!==false&&is_array($parts)&&($parts['scheme']??'')==='https'&&$host!==''
            &&!isset($parts['user'])&&!isset($parts['pass'])&&!isset($parts['query'])&&!isset($parts['fragment'])
            &&trim((string)($parts['path']??''),'/')===''
            &&$host!=='localhost'&&!str_ends_with($host,'.localhost')&&!str_ends_with($host,'.local')
            &&($ip?filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false:str_contains($host,'.'));
        $url=$safe?rtrim($base,'/').'/wap'.($custom!==''?'':'/'.$site).'/'.$page:'';
        return ['miniapp_path'=>$page,'h5_url'=>$url,'h5_ready'=>$url!=='',
            'message'=>$url!==''?'入口需使用本站会员登录；工单仍按绑定商户校验权限':'请配置有效公网HTTPS根域名（站点域名或system.wap_domain）；不使用HTTP、额外路径、localhost或内网地址'];
    }
}
