<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use addon\hsx_lead\app\service\{Context,FrameworkNoticeGateway,NoticeConfigService,NoticePortalEntry,NoticePreflightException};
use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\service\notice\{AccountNoticePolicyService,NoticeDeliveryWindow,NoticeDeliveryDeferredException};
use core\exception\{CommonException,NoticeDeliveryException};
use think\facade\Db;

/** Reuses the framework templates, channels and logs; business facts never depend on delivery. */
final class CooperationNoticeService
{
    private ?\Closure $dispatcher;
    public function __construct(?callable $dispatcher=null) { $this->dispatcher=$dispatcher===null?null:\Closure::fromCallable($dispatcher); }
    public function changed(array $event): void
    {
        // The outbox is the business-transaction entry point. A direct caller
        // cannot safely turn a committed inner claim into an external send
        // while its surrounding transaction could still roll back.
        if(\addon\hsx_lead\app\service\notice\NoticeTaskRuntime::inTransaction())throw new NoticePreflightException('请在业务提交后通过通知任务处理，本次未提交渠道');
        $site=(int)($event['site_id']??0);$id=(int)($event['lead_id']??0);$action=(string)($event['action']??'');
        $lead=Store::row('collaboration',$site,$id);
        if(isset($event['assignment_version'])&&((int)$event['assignment_version']!==(int)$lead['assignment_version']||($event['lead_status']??'')!==$lead['status']))return;
        NoticeDeliveryWindow::assertOpen($site);
        $assignment=TeamService::internalAssignment($lead,true);
        if ($assignment!==null) {
            $merchantId=(int)$lead['publisher_id'];$merchant=Data::row('merchant',$site,$merchantId);
            foreach(self::internalRecipients($site,$lead,$merchant) as $memberId=>$role) {
                $phase=$action==='assigned'&&$lead['status']==='assigned'&&(int)$memberId===$assignment['assignee_member_id']?'assigned':'changed';
                foreach(['weapp','wechat','sms'] as $channel) $this->channel($site,$lead,$merchantId,(int)$memberId,$role,$phase,$action,$channel);
            }
            return;
        }
        $targets=[(int)$lead['publisher_id']=>'changed'];
        if ((int)$lead['receiver_id']>0) $targets[(int)$lead['receiver_id']]=$action==='assigned'&&$lead['status']==='assigned'?'assigned':'changed';
        $previous=self::previousReceiver($site,$lead,$action);
        if ($previous>0) $targets[$previous]='changed';
        foreach($targets as $merchantId=>$phase) {
            $merchant=Data::row('merchant',$site,$merchantId);
            foreach(self::recipientIds($site,$merchant) as $memberId=>$role) foreach(['weapp','wechat','sms'] as $channel) $this->channel($site,$lead,$merchantId,(int)$memberId,$role,$phase,$action,$channel);
        }
    }
    private function channel(int $site,array $lead,int $merchantId,int $memberId,string $role,string $phase,string $action,string $channel): void
    {
        $key=NoticeConfigService::PHASES[$phase];$dedupe=hash('sha256',implode('|',['cooperation',$site,$lead['id'],$lead['assignment_version'],$lead['status'],$action,$merchantId,$memberId,$channel]));
        $claim=Db::transaction(function()use($site,$lead,$merchantId,$memberId,$role,$phase,$action,$channel,$key,$dedupe):?array {
            $current=Store::row('collaboration',$site,(int)$lead['id'],true);
            if ((int)$current['assignment_version']!==(int)$lead['assignment_version']||$current['status']!==$lead['status']
                ||(int)$current['publisher_id']!==(int)$lead['publisher_id']||(int)$current['receiver_id']!==(int)$lead['receiver_id']) return null;
            $existing=Data::query('audit',$site)->where('dedupe_key',$dedupe)->lock(true)->find();
            if($existing){
                $saved=Data::decode($existing['detail_json']);if(($saved['status']??'')!=='deferred')return null;
                // Only locally proven non-submission may resume automatically.
                $saved['attempts']=(int)($saved['attempts']??0)+1;
                [$saved,$template]=$this->prepare($site,$lead,$saved);
                Data::query('audit',$site)->where('id',$existing['id'])->update(['detail_json'=>Data::json($saved),'update_time'=>time()]);
                return$template===null?null:$this->payload($site,$lead,(int)$existing['id'],$saved,$template);
            }
            $state=['phase'=>$phase,'channel'=>$channel,'member_id'=>$memberId,'recipient_role'=>$role,'merchant_id'=>$merchantId,'notice_key'=>$key,
                'assignment_version'=>(int)$lead['assignment_version'],'lead_status'=>$lead['status'],'event_action'=>$action,'attempts'=>1,'retry_count'=>0,'provider_code'=>''];
            [$state,$template]=$this->prepare($site,$lead,$state);
            $id=(int)Data::query('audit',$site)->insertGetId(['site_id'=>$site,'entity_type'=>'collaboration_notice','entity_id'=>$lead['id'],'action'=>'notice_event','dedupe_key'=>$dedupe,
                'detail_json'=>Data::json($state),'create_time'=>time(),'update_time'=>time()]);
            return $template===null?null:$this->payload($site,$lead,$id,$state,$template);
        });
        if (!$claim) return;
        $this->dispatchClaim($claim);
    }
    /** Every retry is an explicit administrative action on the original recipient/channel. */
    public function retry(Context $context,int $leadId,int $auditId,array $input): void
    {
        if($context->uid<=0)throw new CommonException('仅平台管理人员可以核对并重试通知');
        Store::permission($context,'notice_retry');
        try{NoticeDeliveryWindow::assertOpen($context->site);}catch(NoticeDeliveryDeferredException$error){throw new CommonException($error->getMessage());}
        if(\addon\hsx_lead\app\service\notice\NoticeTaskRuntime::inTransaction())throw new CommonException('请在业务保存完成后再重试通知，本次未提交渠道');
        if(($input['confirm']??null)!==true)throw new CommonException('请明确确认重试本条通知；短信可能计费');
        $reason=Data::text($input,'reason',200,true);$site=$context->site;
        $claim=Db::transaction(function()use($context,$site,$leadId,$auditId,$reason):?array{
            $lead=Store::adminLead($context,$leadId,true);$row=Data::row('audit',$site,$auditId,true);$state=Data::decode($row['detail_json']);
            $block=self::retryBlockReason($site,$lead,$row,$state);if($block!=='')throw new CommonException($block);
            $history=is_array($state['retry_history']??null)?$state['retry_history']:[];
            $history[]=['uid'=>$context->uid,'reason'=>$reason,'at'=>time(),'previous_status'=>(string)$state['status'],'previous_provider_code'=>(string)($state['provider_code']??'')];
            $state['retry_count']=(int)($state['retry_count']??0)+1;$state['attempts']=max(1,(int)($state['attempts']??1))+1;
            $state['retry_history']=$history;$state['provider_code']='';
            [$state,$template]=$this->prepare($site,$lead,$state);
            Data::query('audit',$site)->where('id',$auditId)->update(['detail_json'=>Data::json($state),'update_time'=>time()]);
            $context->audit('collaboration_notice_retry',$leadId,'retry_confirmed',['notice_audit_id'=>$auditId,'channel'=>$state['channel'],'recipient_member_id'=>(int)$state['member_id'],'attempts'=>$state['attempts'],'reason'=>$reason]);
            return$template===null?null:$this->payload($site,$lead,$auditId,$state,$template);
        });
        if($claim!==null)$this->dispatchClaim($claim);
    }
    public static function retryBlockReason(int $site,array $lead,array $row,array $state,array &$cache=[]): string
    {
        if($row['entity_type']!=='collaboration_notice'||(int)$row['entity_id']!==(int)$lead['id'])return'这不是当前工单的渠道通知记录';
        if(!isset($cache['ttl_seconds']))$cache['ttl_seconds']=(int)AccountNoticePolicyService::raw($site)['ttl_seconds'];
        if((int)$row['create_time']+$cache['ttl_seconds']<=time())return'通知已超过时效，不再重发；请查看工单当前状态';
        if(!in_array($state['channel']??'',['weapp','wechat','sms'],true)||!isset(NoticeConfigService::PHASES[$state['phase']??''])||($state['notice_key']??'')!==NoticeConfigService::PHASES[$state['phase']])return'通知场景记录不完整，暂不能重试';
        if(!in_array($state['status']??'',['disabled','pending_configuration','pending_recipient','not_submitted','rejected','deferred'],true))return'仅明确未提交或被服务商拒绝的通知可重试；已受理或结果未知时不能重复发送';
        if((int)($state['retry_count']??0)>=3)return'本条通知已重试3次，请先核对渠道日志';
        if((int)($state['assignment_version']??0)!==(int)$lead['assignment_version']||($state['lead_status']??'')!==$lead['status'])return'工单已变化，不再发送过时提醒';
        $merchantId=(int)($state['merchant_id']??0);$action=(string)($state['event_action']??'');
        if(!isset($cache['previous'][$action]))$cache['previous'][$action]=self::previousReceiver($site,$lead,$action);
        $previous=$cache['previous'][$action];
        if(!in_array($merchantId,[(int)$lead['publisher_id'],(int)$lead['receiver_id'],$previous],true)||$merchantId<=0||($merchantId===$previous&&$previous>0&&($state['phase']??'')!=='changed'))return'接收门店已变化，不再发送原提醒';
        if(!array_key_exists($merchantId,$cache['merchants']??[]))$cache['merchants'][$merchantId]=Data::query('merchant',$site)->where('id',$merchantId)->find();
        $merchant=$cache['merchants'][$merchantId];if(!$merchant)return'原接收门店已不可用';
        $assignment=TeamService::internalAssignment($lead,true);
        if($assignment!==null){
            if(!isset($cache['internal'][$merchantId]))$cache['internal'][$merchantId]=self::internalRecipients($site,$lead,$merchant);
            $recipients=$cache['internal'][$merchantId];if(!isset($recipients[(int)($state['member_id']??0)])||($state['phase']==='assigned'&&(int)$state['member_id']!==$assignment['assignee_member_id']))return'原指定成员已变化，不再发送原提醒';
        }
        $identity=$merchantId.'|'.(int)($state['member_id']??0).'|'.($state['recipient_role']??'');
        if(!array_key_exists($identity,$cache['recipients']??[]))$cache['recipients'][$identity]=self::recipient($site,$merchant,(int)($state['member_id']??0),(string)($state['recipient_role']??''))!==null;
        if(!$cache['recipients'][$identity])return'原接收账号已停用、退出门店或归属无法核实';
        return'';
    }
    private function prepare(int $site,array $lead,array $state): array
    {
        NoticeDeliveryWindow::assertOpen($site);
        $channel=(string)$state['channel'];$key=(string)$state['notice_key'];$merchant=Data::row('merchant',$site,(int)$state['merchant_id']);
        NoticeConfigService::syncSite($site);$config=Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->find()?:[];
        $mapping=match($channel){'weapp'=>NoticeConfigService::weappForSite($site),'wechat'=>NoticeConfigService::wechatForSite($site),'sms'=>require dirname(__DIR__,2).'/dict/notice/sms.php'};
        $field=$channel==='sms'?'sms_id':$channel.'_template_id';$state['template_id']=(string)($config[$field]??'');$state['status']='dispatching';$state['message']='正在提交本渠道，请勿重复发送';
        if(empty($config['is_'.$channel])){$state['status']='disabled';$state['message']='本站未开启该渠道，门店仍可在工作台查看';}
        elseif(empty($config[$field])||empty($mapping[$key])){$state['status']='pending_configuration';$state['message']='请在牛云通知设置核验、获取并开启本站模板';}
        $member=self::recipient($site,$merchant,(int)$state['member_id'],(string)$state['recipient_role']);$identity=['weapp'=>'weapp_openid','wechat'=>'wx_openid','sms'=>'mobile'][$channel];
        if($state['status']==='dispatching'&&(!$member||empty($member[$identity]))){$state['status']='pending_recipient';$state['message']='接收会员尚未完成本渠道身份绑定；小程序还需主动订阅';}
        if($state['status']==='dispatching'&&$channel==='sms'&&!NoticeConfigService::smsAccountConfigured($site)){$state['status']='pending_configuration';$state['message']='短信服务尚未开通或账号未就绪，本次未提交';}
        if($state['status']==='dispatching'&&$channel==='wechat'&&!NoticePortalEntry::get($site)['h5_ready']){$state['status']='pending_configuration';$state['message']='尚未配置可供公众号访问的公网HTTPS入口';}
        if($state['status']!=='dispatching')return[$state,null];
        $definitions=require dirname(__DIR__,2).'/dict/notice/notice.php';
        return[$state,array_replace($definitions[$key],['site_id'=>$site,'is_weapp'=>0,'is_wechat'=>0,'is_sms'=>0,'is_'.$channel=>1,$field=>$config[$field],$channel=>$mapping[$key]])];
    }
    private function payload(int $site,array $lead,int $id,array $state,array $template): array
    {
        return['site_id'=>$site,'key'=>$state['notice_key'],'channel'=>$state['channel'],
            'expected_recipient_member_id'=>(int)$state['member_id'],'expected_recipient_merchant_id'=>(int)$state['merchant_id'],
            'data'=>['collaboration_id'=>(int)$lead['id'],'notice_audit_id'=>$id,'notice_attempt'=>(int)$state['attempts']],'template'=>$template];
    }
    private function dispatchClaim(array $claim): void
    {
        $site=(int)$claim['site_id'];
        $id=(int)$claim['data']['notice_audit_id'];
        $attempt=(int)$claim['data']['notice_attempt'];$providerCode='';$deferred=null;
        try {
            $result=$this->dispatcher?($this->dispatcher)($claim):(new FrameworkNoticeGateway())->dispatch($claim);
            $accepted=is_array($result)&&($result['accepted']??null)===true&&($result['channel']??'')===$claim['channel']&&($result['provider_code']??null)==='0';
            $status=$result===false?'uncertain':($accepted?'accepted':'submitted');
            $providerCode=$accepted?'0':'';
            $message=$result===false?'结果待核实，请先核对框架日志，不盲目重发':($accepted?'服务商已受理，不代表用户已查看或终端已送达':'已交牛云通知通道，未返回可核实的服务商回执；不代表终端已收到');
        } catch(NoticeDeliveryDeferredException $error){$deferred=$error;$status='deferred';$message=$error->getMessage();}
        catch(NoticePreflightException $error){$status='not_submitted';$message='发送前校验未通过，本次未提交服务商；请核对渠道配置和原接收账号';}
        catch(NoticeDeliveryException $error){
            $status=$error->channel()!==$claim['channel']?'uncertain':match($error->deliveryStatus()){'rejected'=>'rejected','not_submitted'=>'not_submitted',default=>'uncertain'};
            $code=$error->providerCode();$providerCode=preg_match('/^[A-Za-z0-9_.-]{1,40}$/D',$code)?$code:'';
            $message=self::deliveryMessage($status,$providerCode);
        } catch (\Throwable $error) {$status='uncertain';$message='渠道处理异常，可能已提交；请核对框架日志，业务已保存';}
        Db::transaction(function()use($site,$id,$attempt,$status,$message,$providerCode,$deferred):void {
            $row=Data::row('audit',$site,$id,true);$state=Data::decode($row['detail_json']);
            if(($state['status']??'')!=='dispatching'||(int)($state['attempts']??1)!==$attempt)return;
            $state['status']=$status;$state['message']=$message;$state['provider_code']=$providerCode;
            if($deferred!==null){$state['next_retry_at']=$deferred->until();$state['attempts']=max(0,$attempt-1);}
            Data::query('audit',$site)->where('id',$id)->update(['detail_json'=>Data::json($state),'update_time'=>time()]);
        });
        if($deferred!==null)throw$deferred;
    }
    private static function deliveryMessage(string $status,string $code): string
    {
        if($status==='uncertain')return'服务商结果尚未确认；请核对渠道日志，不要重复发送';
        if($status==='not_submitted')return'渠道尚未提交发送，请核对配置后重试';
        return match($code){
            '43101'=>'接收用户未订阅该消息或可发送次数已用尽，请用户重新订阅后再试',
            '40003'=>'接收账号的微信身份无效，请重新登录本站对应微信渠道',
            '40037'=>'本站通知模板无效，请重新获取并开启正确模板',
            '41030'=>'通知跳转页面无效，请核对本站小程序页面配置',
            '47003'=>'模板字段内容不符合微信要求，请核对字段映射',
            '40001','40014','42001'=>'渠道凭据校验失败，请核对本站账号授权',
            default=>'服务商明确拒绝本次通知，请核对渠道配置后重试',
        };
    }
    public static function statusForLead(int $site,int $id,bool $includeRecipients=false): array
    {
        $rows=Data::query('audit',$site)->whereIn('entity_type',['collaboration_notice',CooperationNoticeOutbox::TYPE])->where('entity_id',$id)->order('id desc')->limit(18)->select()->toArray();
        $members=[];$merchants=[];$states=[];$memberIds=[];$merchantIds=[];
        foreach($rows as $row){
            $state=Data::decode($row['detail_json']);$states[(int)$row['id']]=$state;
            if(!$includeRecipients||$row['entity_type']!=='collaboration_notice')continue;
            if((int)($state['member_id']??0)>0)$memberIds[]=(int)$state['member_id'];
            if((int)($state['merchant_id']??0)>0)$merchantIds[]=(int)$state['merchant_id'];
        }
        // Admin-only labels use two bounded, tenant-scoped identity projections;
        // never fetch contact details or repeat a query for each channel row.
        if($memberIds)foreach(Db::name('member')->where('site_id',$site)->whereIn('member_id',array_unique($memberIds))->field('member_id,nickname,member_no')->select()->toArray() as $member)$members[(int)$member['member_id']]=$member;
        if($merchantIds)foreach(Data::query('merchant',$site)->whereIn('id',array_unique($merchantIds))->field('id,name')->select()->toArray() as $merchant)$merchants[(int)$merchant['id']]=$merchant;
        return array_map(static function(array $row)use($states,$includeRecipients,$members,$merchants):array {
            $s=$states[(int)$row['id']];$queue=$row['entity_type']===CooperationNoticeOutbox::TYPE;
            $out=['id'=>(int)$row['id'],'channel'=>$queue?'queue':($s['channel']??''),'status'=>$s['status']??'uncertain','message'=>$s['message']??'待核实','create_time'=>(int)$row['create_time']];
            if($includeRecipients&&!$queue){
                $memberId=(int)($s['member_id']??0);$merchantId=(int)($s['merchant_id']??0);$member=$members[$memberId]??[];
                $shop=trim((string)($merchants[$merchantId]['name']??''))?:($merchantId>0?'门店#'.$merchantId:'未记录门店');
                $account=trim((string)($member['nickname']??''))?:trim((string)($member['member_no']??''));
                $out+=['recipient_label'=>$shop.' · '.($account!==''?$account:($memberId>0?'账号#'.$memberId:'未记录账号')),
                    'recipient_member_id'=>$memberId,'recipient_merchant_id'=>$merchantId];
            }
            return $out;
        },$rows);
    }
    public static function templateData(int $site,int $leadId,int $auditId,string $channel,string $expectedKey,int $expectedAttempt=0): array
    {
        NoticeDeliveryWindow::assertOpen($site);
        $audit=Data::row('audit',$site,$auditId);$state=Data::decode($audit['detail_json']);
        if((int)$audit['create_time']+(int)AccountNoticePolicyService::raw($site)['ttl_seconds']<=time())throw new NoticePreflightException('通知已超过时效，未发送');
        if ($audit['entity_type']!=='collaboration_notice'||(int)$audit['entity_id']!==$leadId||($state['channel']??'')!==$channel||($state['status']??'')!=='dispatching'
            ||($expectedAttempt>0&&$expectedAttempt!==(int)($state['attempts']??1))) throw new NoticePreflightException('通知与原协作记录不对应，未发送');
        $key=NoticeConfigService::PHASES[$state['phase']??'']??'';
        if($key===''||($state['notice_key']??'')!==$key||$expectedKey!==$key)throw new NoticePreflightException('通知模板场景与原发送记录不对应，未发送');
        $config=Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->find()?:[];$field=$channel==='sms'?'sms_id':$channel.'_template_id';
        if(empty($config['is_'.$channel])||empty($config[$field])||(string)$config[$field]!==($state['template_id']??''))throw new NoticePreflightException('通知渠道或模板配置已变化，未发送');
        $lead=Store::row('collaboration',$site,$leadId);
        if ((int)$state['assignment_version']!==(int)$lead['assignment_version']||$state['lead_status']!==$lead['status']) throw new NoticePreflightException('原交付已变化，不再发送过时提醒');
        $merchant=Data::row('merchant',$site,(int)$state['merchant_id']);
        $previous=self::previousReceiver($site,$lead,(string)($state['event_action']??''));
        $returned=(int)$merchant['id']===$previous&&$previous>0;
        if (((int)$merchant['id']!==(int)$lead['publisher_id']&&(int)$merchant['id']!==(int)$lead['receiver_id']&&!$returned)
            ||($returned&&$state['phase']!=='changed')) throw new NoticePreflightException('通知接收身份已变化');
        $assignment=TeamService::internalAssignment($lead,true);
        if ($assignment!==null) {
            $recipients=self::internalRecipients($site,$lead,$merchant);
            if (!isset($recipients[(int)$state['member_id']])||($state['phase']==='assigned'&&(int)$state['member_id']!==$assignment['assignee_member_id'])) throw new NoticePreflightException('本店交付接收成员已变化，未发送');
        }
        // Recheck after claiming: disabling a merchant/member or changing its binding
        // must stop an in-flight reminder before it reaches a framework sender.
        $member=self::recipient($site,$merchant,(int)$state['member_id'],(string)($state['recipient_role']??'owner'));
        $identity=['weapp'=>'weapp_openid','wechat'=>'wx_openid','sms'=>'mobile'][$channel]??'';
        if (!$member||$identity===''||empty($member[$identity])) throw new NoticePreflightException('通知接收账号已停用或渠道身份失效，未发送');
        // A returned receiver may know the outcome, but no longer has access to
        // the customer record. Its reminder opens only the authenticated desk.
        $entry=$returned?NoticePortalEntry::get($site):NoticePortalEntry::forLead($site,$leadId);
        $page=$entry['miniapp_path'];$url=$entry['h5_url'];
        $internalComplete=(int)$lead['publisher_id']>0&&(int)$lead['publisher_id']===(int)$lead['receiver_id']&&$lead['status']==='paid';
        $vars=['__weapp_page'=>$page,'__wechat_page'=>$url,'url'=>$url,'__hsx_lead_notice_id'=>(string)$auditId,'__hsx_lead_notice_channel'=>$channel,'__hsx_lead_notice_attempt'=>(string)max(1,(int)($state['attempts']??1)),
            '__hsx_lead_notice_merchant_id'=>(string)$state['merchant_id'],
            'task_id'=>(string)$leadId,'task_no'=>mb_substr($lead['lead_no'],0,32),'status_text'=>$internalComplete?'已完成':Rules::status($lead['status']),'notice_remark'=>$internalComplete?'本店协作已完成，请登录核实':($state['phase']==='assigned'?'有新的协作交付，请登录处理':'协作状态已更新，请登录核实'),
            'occurred_at'=>date('Y-m-d H:i:s'),'demand_summary'=>$returned?'本次交付已退回，原客户资料不再可见':'请进入商户工作台查看需求'];
        $mapping=$channel==='weapp'?NoticeConfigService::weappForSite($site):($channel==='wechat'?NoticeConfigService::wechatForSite($site):[]);
        foreach($mapping[$key]['content']??[] as $field) {$v=trim((string)$field[1],'{}');$k=(string)$field[2];if(!isset($vars[$v]))continue;
            if(str_starts_with($k,'thing'))$vars[$v]=mb_substr($vars[$v],0,20);elseif(str_starts_with($k,'phrase'))$vars[$v]=mb_substr($vars[$v],0,5);elseif(str_starts_with($k,'character_string'))$vars[$v]=mb_substr($vars[$v],0,32);elseif(str_starts_with($k,'date'))$vars[$v]=date('Y-m-d');}
        return ['to'=>['member_id'=>(int)$state['member_id']],'vars'=>$vars];
    }

    /** Existing business audits retain the exact receiver after reject/withdraw clears it. */
    private static function previousReceiver(int $site,array $lead,string $action): int
    {
        if ((int)$lead['receiver_id']!==0||!in_array($action,['reject','withdraw','cancel'],true)
            ||$lead['status']!==($action==='cancel'?'cancelled':'selecting')) return 0;
        $rows=Data::query('audit',$site)->where('entity_type','collaboration')->where('entity_id',(int)$lead['id'])
            ->whereIn('action',['assigned','reject','withdraw','cancel'])->order('id desc')->limit($action==='cancel'?3:2)->select()->toArray();
        $offset=$action==='cancel'?2:1;
        if (count($rows)!==$offset+1||$rows[0]['action']!==$action||$rows[$offset]['action']!=='assigned'
            ||($action==='cancel'&&!in_array($rows[1]['action'],['reject','withdraw'],true))) return 0;
        $snapshot=Data::decode($rows[$offset]['detail_json']);$receiver=(int)($snapshot['receiver_id']??0);
        if ((int)($snapshot['assignment_version']??0)<=0||(int)$snapshot['assignment_version']!==(int)$lead['assignment_version']-$offset
            ||$receiver<=0||$receiver===(int)$lead['publisher_id']) return 0;
        return $receiver;
    }

    /** New internal handoffs notify the selected recipient and the publishing actor, not the store-wide queue. */
    private static function internalRecipients(int $site,array $lead,array $merchant): array
    {
        $assignment=TeamService::internalAssignment($lead,true);if ($assignment===null) return [];
        if ($assignment['publisher_member_id']<=0||$assignment['assignee_member_id']<=0||$assignment['publisher_member_id']===$assignment['assignee_member_id']) return [];
        $allowed=[$assignment['publisher_member_id'],$assignment['assignee_member_id']];$result=[];
        foreach(TeamService::assignmentMembers($site,$merchant) as $member) if (in_array($member['member_id'],$allowed,true)) $result[$member['member_id']]=$member['role'];
        return $result;
    }

    /** IDs only, never contact data. A duplicated owner link is one recipient. */
    public static function recipientIds(int $site,array $merchant): array
    {
        $owner=(int)($merchant['member_id']??0);$ids=[$owner=>'owner'];
        foreach(Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',(int)$merchant['id'])->where('status','active')->column('member_id') as $memberId) {
            $memberId=(int)$memberId;if($memberId>0&&!isset($ids[$memberId]))$ids[$memberId]='employee';
        }
        return$ids;
    }
    public static function recipient(int $site,array $merchant,int $memberId,string $role): ?array
    {
        if ((int)($merchant['site_id']??0)!==$site||(int)($merchant['status']??0)!==1||(int)($merchant['member_id']??0)<=0) return null;
        $partner=Store::query('partner',$site)->where('merchant_id',(int)$merchant['id'])->find();
        if ($partner&&(int)$partner['blocked_at']>0) return null;
        if($memberId<=0||!in_array($role,['owner','employee'],true))return null;
        if($role==='owner'&&(int)$merchant['member_id']!==$memberId)return null;
        if($role==='employee'&&((int)$merchant['member_id']===$memberId||!Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',(int)$merchant['id'])->where('member_id',$memberId)->where('status','active')->find()))return null;
        // A valid link alone is insufficient: ambiguous active links/ownership
        // cannot authenticate into this merchant through the workbench either.
        try {$resolved=TeamService::resolve(new Context($site,0,$memberId),false);}catch(\core\exception\CommonException $ignored){return null;}
        if (!$resolved||(int)$resolved['id']!==(int)$merchant['id']) return null;
        return Db::name('member')->where('site_id',$site)->where('member_id',$memberId)
            ->where('status',1)->where('is_del',0)->where('delete_time',0)->find()?:null;
    }
}
