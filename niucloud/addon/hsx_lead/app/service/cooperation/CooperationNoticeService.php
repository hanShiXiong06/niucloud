<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use addon\hsx_lead\app\service\{FrameworkNoticeGateway,NoticeConfigService,NoticePortalEntry};
use addon\hsx_lead\app\support\Data;
use think\facade\Db;

/** Reuses the framework templates, channels and logs; business facts never depend on delivery. */
final class CooperationNoticeService
{
    private ?\Closure $dispatcher;
    public function __construct(?callable $dispatcher=null) { $this->dispatcher=$dispatcher===null?null:\Closure::fromCallable($dispatcher); }
    public function changed(array $event): void
    {
        $site=(int)($event['site_id']??0);$id=(int)($event['lead_id']??0);$action=(string)($event['action']??'');
        $lead=Store::row('collaboration',$site,$id);$targets=[(int)$lead['publisher_id']=>'changed'];
        if ($action==='assigned' && $lead['status']==='assigned' && (int)$lead['receiver_id']>0) $targets[(int)$lead['receiver_id']]='assigned';
        foreach($targets as $merchantId=>$phase) foreach(['weapp','wechat','sms'] as $channel) $this->channel($site,$lead,$merchantId,$phase,$action,$channel);
    }
    private function channel(int $site,array $lead,int $merchantId,string $phase,string $action,string $channel): void
    {
        $key=NoticeConfigService::PHASES[$phase];$dedupe=hash('sha256',implode('|',['cooperation',$site,$lead['id'],$lead['assignment_version'],$lead['status'],$action,$merchantId,$channel]));
        $claim=Db::transaction(function()use($site,$lead,$merchantId,$phase,$channel,$key,$dedupe):?array {
            Store::row('collaboration',$site,(int)$lead['id'],true);
            if (Data::query('audit',$site)->where('dedupe_key',$dedupe)->find()) return null;
            $merchant=Data::row('merchant',$site,$merchantId);$memberId=(int)$merchant['member_id'];
            NoticeConfigService::syncSite($site);
            $config=Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->find()?:[];
            $mapping=match($channel){'weapp'=>NoticeConfigService::weappForSite($site),'wechat'=>NoticeConfigService::wechatForSite($site),'sms'=>require dirname(__DIR__,2).'/dict/notice/sms.php'};
            $status='dispatching';$message='已取得本渠道发送权，结果需查框架日志';
            $field=$channel==='sms'?'sms_id':$channel.'_template_id';
            if (empty($config['is_'.$channel])) {$status='disabled';$message='本站未开启该渠道，门店仍可在工作台查看';}
            elseif (empty($config[$field])||empty($mapping[$key])) {$status='pending_configuration';$message='请在牛云通知设置核验、获取并开启本站模板';}
            $member=$memberId>0?Db::name('member')->where('site_id',$site)->where('member_id',$memberId)->where('status',1)->where('is_del',0)->where('delete_time',0)->find():null;
            $identity=['weapp'=>'weapp_openid','wechat'=>'wx_openid','sms'=>'mobile'][$channel];
            if ($status==='dispatching'&&(!$member||empty($member[$identity]))) {$status='pending_recipient';$message='接收会员尚未完成本渠道身份绑定；小程序还需主动订阅';}
            if ($status==='dispatching'&&$channel==='sms'&&!NoticeConfigService::smsAccountConfigured($site)) {$status='pending_configuration';$message='短信服务尚未开通或账号未就绪，本次未提交';}
            if ($status==='dispatching'&&$channel==='wechat'&&!NoticePortalEntry::get($site)['h5_ready']) {$status='pending_configuration';$message='尚未配置可供公众号访问的公网HTTPS入口';}
            $state=['phase'=>$phase,'channel'=>$channel,'member_id'=>$memberId,'merchant_id'=>$merchantId,'notice_key'=>$key,'status'=>$status,'message'=>$message,
                'assignment_version'=>(int)$lead['assignment_version'],'lead_status'=>$lead['status']];
            $id=(int)Data::query('audit',$site)->insertGetId(['site_id'=>$site,'entity_type'=>'collaboration_notice','entity_id'=>$lead['id'],'action'=>'notice_event','dedupe_key'=>$dedupe,
                'detail_json'=>Data::json($state),'create_time'=>time(),'update_time'=>time()]);
            if ($status!=='dispatching') return null;
            $definitions=require dirname(__DIR__,2).'/dict/notice/notice.php';
            $template=array_replace($definitions[$key],['site_id'=>$site,'is_weapp'=>0,'is_wechat'=>0,'is_sms'=>0,'is_'.$channel=>1,$field=>$config[$field],$channel=>$mapping[$key]]);
            return ['site_id'=>$site,'key'=>$key,'channel'=>$channel,'data'=>['collaboration_id'=>(int)$lead['id'],'notice_audit_id'=>$id],'template'=>$template];
        });
        if (!$claim) return;
        $id=(int)$claim['data']['notice_audit_id'];
        try {
            $result=$this->dispatcher?($this->dispatcher)($claim):(new FrameworkNoticeGateway())->dispatch($claim);
            $status=$result===false?'uncertain':'submitted';$message=$result===false?'结果待核实，请先核对框架日志，不盲目重发':'已交牛云通知通道，不代表终端已收到';
        } catch (\Throwable $error) {$status='uncertain';$message='渠道处理异常，可能已提交；请核对框架日志，业务已保存';}
        Db::transaction(function()use($site,$id,$status,$message):void {
            $row=Data::row('audit',$site,$id,true);$state=Data::decode($row['detail_json']);
            $state['status']=$status;$state['message']=$message;Data::query('audit',$site)->where('id',$id)->update(['detail_json'=>Data::json($state),'update_time'=>time()]);
        });
    }
    public static function statusForLead(int $site,int $id): array
    {
        $rows=Data::query('audit',$site)->where('entity_type','collaboration_notice')->where('entity_id',$id)->order('id desc')->limit(18)->select()->toArray();
        return array_map(static function(array $row):array {$s=Data::decode($row['detail_json']);return ['id'=>(int)$row['id'],'channel'=>$s['channel']??'','status'=>$s['status']??'uncertain','message'=>$s['message']??'待核实','create_time'=>(int)$row['create_time']];},$rows);
    }
    public static function templateData(int $site,int $leadId,int $auditId,string $channel): array
    {
        $audit=Data::row('audit',$site,$auditId);$state=Data::decode($audit['detail_json']);
        if ($audit['entity_type']!=='collaboration_notice'||(int)$audit['entity_id']!==$leadId||($state['channel']??'')!==$channel||($state['status']??'')!=='dispatching') throw new \RuntimeException('通知与原协作记录不对应，未发送');
        $lead=Store::row('collaboration',$site,$leadId);
        if ((int)$state['assignment_version']!==(int)$lead['assignment_version']||$state['lead_status']!==$lead['status']) throw new \RuntimeException('原交付已变化，不再发送过时提醒');
        $merchant=Data::row('merchant',$site,(int)$state['merchant_id']);
        if ((int)$merchant['member_id']!==(int)$state['member_id']||((int)$merchant['id']!==(int)$lead['publisher_id']&&(int)$merchant['id']!==(int)$lead['receiver_id'])) throw new \RuntimeException('通知接收身份已变化');
        $entry=NoticePortalEntry::get($site);$page=$entry['miniapp_path'].'?collaboration_id='.$leadId;$url=$entry['h5_url']!==''?$entry['h5_url'].'?collaboration_id='.$leadId:'';
        $vars=['__weapp_page'=>$page,'__wechat_page'=>$url,'url'=>$url,'__hsx_lead_notice_id'=>(string)$auditId,'__hsx_lead_notice_channel'=>$channel,'__hsx_lead_notice_attempt'=>'1',
            'task_id'=>(string)$leadId,'task_no'=>mb_substr($lead['lead_no'],0,32),'status_text'=>Rules::status($lead['status']),'notice_remark'=>$state['phase']==='assigned'?'有新的协作交付，请登录处理':'协作状态已更新，请登录核实',
            'occurred_at'=>date('Y-m-d H:i:s'),'demand_summary'=>'请进入商户工作台查看需求'];
        $key=NoticeConfigService::PHASES[$state['phase']];$mapping=$channel==='weapp'?NoticeConfigService::weappForSite($site):($channel==='wechat'?NoticeConfigService::wechatForSite($site):[]);
        foreach($mapping[$key]['content']??[] as $field) {$v=trim((string)$field[1],'{}');$k=(string)$field[2];if(!isset($vars[$v]))continue;
            if(str_starts_with($k,'thing'))$vars[$v]=mb_substr($vars[$v],0,20);elseif(str_starts_with($k,'phrase'))$vars[$v]=mb_substr($vars[$v],0,5);elseif(str_starts_with($k,'character_string'))$vars[$v]=mb_substr($vars[$v],0,32);elseif(str_starts_with($k,'date'))$vars[$v]=date('Y-m-d');}
        return ['to'=>['member_id'=>(int)$state['member_id']],'vars'=>$vars];
    }
}
