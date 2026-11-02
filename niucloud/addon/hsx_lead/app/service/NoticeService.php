<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** One durable business event, independent channel claims/results; no extra outbox table. */
final class NoticeService
{
    private const CHANNELS=['weapp'=>'小程序','wechat'=>'公众号','sms'=>'短信'];
    private ?\Closure $dispatcher;
    public function __construct(?callable $dispatcher=null)
    {
        $this->dispatcher=$dispatcher===null?null:\Closure::fromCallable($dispatcher);
    }

    public function taskNotice(int $site,int $taskId,string $phase,string $eventKey): array
    {
        if(!isset(NoticeConfigService::PHASES[$phase])||$eventKey==='')throw new CommonException('无效通知事件');
        $dedupe=hash('sha256','hsx_lead|'.$site.'|'.$taskId.'|'.$phase.'|'.$eventKey);
        $row=Db::transaction(function()use($site,$taskId,$phase,$eventKey,$dedupe){
            Data::row('task',$site,$taskId,true);
            $existing=Data::query('audit',$site)->where('dedupe_key',$dedupe)->find();if($existing)return$existing;
            $state=['phase'=>$phase,'notice_key'=>NoticeConfigService::PHASES[$phase],'event_hash'=>hash('sha256',$eventKey),'channels'=>[]];
            $configuration=Db::name('sys_notice')->where('site_id',$site)->where('key',$state['notice_key'])->find()?:[];
            $anyEnabled=!empty($configuration['is_weapp'])||!empty($configuration['is_wechat'])||!empty($configuration['is_sms']);
            foreach(self::CHANNELS as$channel=>$name)$state['channels'][$channel]=$this->pending()+[
                'selected'=>!empty($configuration['is_'.$channel])||(!$anyEnabled&&$channel==='weapp')];
            $id=(int)Data::query('audit',$site)->insertGetId(['site_id'=>$site,'entity_type'=>'task_notice','entity_id'=>$taskId,
                'action'=>'notice_event','dedupe_key'=>$dedupe,'detail_json'=>Data::json($state),'create_time'=>time(),'update_time'=>time()]);
            return Data::row('audit',$site,$id);
        });
        $state=$this->state($row);
        foreach($state['channels']as$channel)if(($channel['status']??'')==='pending')return$this->dispatchAudit($site,(int)$row['id']);
        return$this->refresh($row);
    }

    public function statusForTask(int $site,int $taskId): array
    {
        Data::row('task',$site,$taskId);
        $row=Data::query('audit',$site)->where('entity_type','task_notice')->where('entity_id',$taskId)->where('action','notice_event')->order('id desc')->find();
        if($row)return$this->refresh($row);
        $extension=Data::query('audit',$site)->where('entity_type','task')->where('entity_id',$taskId)->where('action','extension_failed')->count()>0;
        return ['status'=>$extension?'extension_failed':'not_requested','label'=>$extension?'通知扩展执行失败':'商户工作台待办',
            'message'=>$extension?'业务已提交，但通知扩展未完成记录；请核对配置后人工重试':'工单可在商户工作台查看；尚未产生外部通知事件',
            'framework_log_id'=>0,'retry_allowed'=>true,'channels'=>[]];
    }

    public function retryTask(int $site,int $taskId,int $uid): array
    {
        $task=Data::row('task',$site,$taskId);
        $row=Data::query('audit',$site)->where('entity_type','task_notice')->where('entity_id',$taskId)->where('action','notice_event')->order('id desc')->find();
        if(!$row)return$this->taskNotice($site,$taskId,$task['status']==='assigned'?'assigned':'changed','manual-initial');
        $status=$this->refresh($row);
        if(empty($status['retry_allowed']))throw new CommonException('没有可安全重试的通知渠道；已提交或结果不明的渠道不能重复发送');
        Data::audit($site,$uid,0,'task_notice',$taskId,'retry_requested',['notice_audit_id'=>(int)$row['id']]);
        return$this->dispatchAudit($site,(int)$row['id']);
    }

    private function dispatchAudit(int $site,int $id): array
    {
        foreach(array_keys(self::CHANNELS)as$channel){
            try{$this->dispatchChannel($site,$id,$channel);}
            catch(\Throwable$error){
                // A malformed mapping in one channel must not suppress another.
                // A persisted claim means submission may have happened; do not retry it blindly.
                $row=Data::row('audit',$site,$id);$leaf=$this->state($row)['channels'][$channel];
                if(in_array($leaf['status']??'',['submitted','framework_logged'],true))continue;
                $attempt=(int)($leaf['attempts']??0);
                $changes=$attempt>0?$this->values([],'uncertain','结果待核实','本渠道处理中断；请先核对框架日志，不可盲目重发',false)
                    :$this->values(['selected'=>true],'pending_configuration','发送前配置异常','本渠道尚未提交，请核对模板配置；其他渠道继续处理',true);
                $this->updateChannel($site,$id,$channel,$changes,$attempt);
            }
        }
        return$this->refresh(Data::row('audit',$site,$id));
    }

    private function dispatchChannel(int $site,int $id,string $channel): void
    {
        $claim=Db::transaction(function()use($site,$id,$channel){
            $audit=Data::row('audit',$site,$id,true);$state=$this->state($audit);$leaf=$state['channels'][$channel];
            if(empty($leaf['retry_allowed']))return null;
            $task=Data::row('task',$site,(int)$audit['entity_id']);$merchant=Data::row('merchant',$site,(int)$task['merchant_id']);
            NoticeConfigService::syncSite($site);
            $key=$state['notice_key'];$config=Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->find()?:[];
            // Selection is snapshotted when the business event is created. A later
            // configuration change cannot silently expand a historical event.
            if(!$leaf['selected'])return$this->blocked($audit,$state,$channel,$leaf,'disabled','未开启','本站未选择此渠道，不会发送',false);
            if(($state['phase']??'')==='assigned'){
                $lead=Data::row('lead',$site,(int)$task['lead_id']);
                if($task['status']!=='assigned'||(int)$lead['active_task_id']!==(int)$task['id'])return$this->blocked($audit,$state,$channel,$leaf,'obsolete','通知已失效','该派单已处理或转派，不再提醒原接收者',false);
            }
            $memberId=(int)$merchant['member_id'];
            $member=$memberId>0?Db::name('member')->where('site_id',$site)->where('member_id',$memberId)->where('status',1)->where('is_del',0)->where('delete_time',0)->find():null;
            if((int)$merchant['status']!==1||!$member)return$this->blocked($audit,$state,$channel,$leaf,'recipient_unavailable','接收会员不可用','请核对启用商户与本站绑定会员；商户工作台待办不受影响',true);
            $mapping=match($channel){'weapp'=>NoticeConfigService::weappForSite($site),'wechat'=>NoticeConfigService::wechatForSite($site),'sms'=>require dirname(__DIR__).'/dict/notice/sms.php'};
            if(!isset($mapping[$key]))return$this->blocked($audit,$state,$channel,$leaf,'pending_configuration','待配置模板','请先登记本站已核验的'.self::CHANNELS[$channel].'公共模板与关键词',true);
            $templateField=$channel==='sms'?'sms_id':$channel.'_template_id';
            if(empty($config[$templateField]))return$this->blocked($audit,$state,$channel,$leaf,'pending_template','待获取本站模板','请在牛云对应渠道配置或获取本站模板；不要使用其他应用的私有模板ID',true);
            if(empty($config['is_'.$channel]))return$this->blocked($audit,$state,$channel,$leaf,'disabled','通知开关关闭','请在牛云对应通知设置开启；不会自动代替管理员启用',true);
            if($channel==='sms'&&!NoticeConfigService::smsAccountConfigured($site))return$this->blocked($audit,$state,$channel,$leaf,'pending_account','待开通短信账号','请在牛云短信设置开通本站账号并核对签名、模板和余额；当前未提交短信',true);
            $identityField=match($channel){'weapp'=>'weapp_openid','wechat'=>'wx_openid','sms'=>'mobile'};
            if(empty($member[$identityField]))return$this->blocked($audit,$state,$channel,$leaf,'pending_recipient','待会员完成渠道绑定',
                match($channel){'weapp'=>'接收会员尚未登录本站小程序，请先登录并主动订阅','wechat'=>'接收会员尚未绑定本站公众号身份；小程序openid不能当作公众号openid','sms'=>'接收会员没有可用手机号，请先完善本人手机号'},true);
            if($channel==='wechat'&&!NoticePortalEntry::get($site,(int)$task['id'])['h5_ready'])return$this->blocked($audit,$state,$channel,$leaf,'pending_entry','待配置访问入口','尚无可用于外部访问的本站入口，不会发送localhost链接',true);
            $definitions=require dirname(__DIR__).'/dict/notice/notice.php';
            $template=array_replace($definitions[$key],['site_id'=>$site,'is_weapp'=>0,'is_wechat'=>0,'is_sms'=>0,
                'weapp_template_id'=>'','wechat_template_id'=>'','sms_id'=>'','is_'.$channel=>1,$templateField=>$config[$templateField],$channel=>$mapping[$key]]);
            $leaf['member_id']=$memberId;$leaf['attempts']=(int)($leaf['attempts']??0)+1;$leaf['attempted_at']=time();
            $leaf['log_after_id']=(int)(Db::name('sys_notice_log')->where('site_id',$site)->where('key',$key)->where('member_id',$memberId)->where('notice_type',$channel)->max('id')??0);
            $leaf=$this->values($leaf,'dispatching','正在提交通知','本渠道已取得执行权；请勿重复发送',false);
            $state['channels'][$channel]=$leaf;$this->saveState($audit,$state);
            return[$state,$leaf,$template,(int)$task['id']];
        });
        if(!$claim)return;
        [$state,$leaf,$template,$taskId]=$claim;
        $payload=['site_id'=>$site,'key'=>$state['notice_key'],'channel'=>$channel,'data'=>[
            'task_id'=>$taskId,'phase'=>$state['phase'],'notice_audit_id'=>$id,'channel'=>$channel,'attempt'=>(int)$leaf['attempts']],
            'template'=>$template];
        try{
            $result=$this->dispatcher?($this->dispatcher)($payload):(new FrameworkNoticeGateway())->dispatch($payload);
            $changes=$result===false?$this->values([],'uncertain','结果待核实','框架返回未确认结果；请核对日志，不可盲目重发',false)
                :$this->values([],'submitted','已交框架（送达待核实）','任务已交牛云；未证明接口成功或终端收到，请查看对应渠道日志',false);
        }catch(NoticePreflightException$error){
            $changes=$this->values([],'pending_configuration','发送前检查未完成',$error->getMessage(),true);
        }catch(\Throwable$error){
            $changes=$this->values([],'uncertain','结果待核实','提交期间出现异常，可能已交渠道；请先核对日志，不可盲目重发',false);
        }
        $this->updateChannel($site,$id,$channel,$changes,(int)$leaf['attempts']);
    }

    private function blocked(array $audit,array $state,string $channel,array $leaf,string $status,string $label,string $message,bool $retry)
    {
        $state['channels'][$channel]=$this->values($leaf,$status,$label,$message,$retry);$this->saveState($audit,$state);return null;
    }

    private function refresh(array $row): array
    {
        $state=$this->state($row);$site=(int)$row['site_id'];$id=(int)$row['id'];
        foreach($state['channels']as$channel=>$leaf){
            if(!in_array($leaf['status']??'',['submitted','dispatching','uncertain'],true)||empty($leaf['member_id']))continue;
            $logs=Db::name('sys_notice_log')->where('site_id',$site)->where('key',$state['notice_key'])->where('notice_type',$channel)
                ->where('member_id',$leaf['member_id'])->where('id','>',(int)($leaf['log_after_id']??0))->order('id desc')->limit(50)->select()->toArray();
            foreach($logs as$log){
                $params=Data::decode($log['params']??'');
                $vars=$channel==='sms'&&is_array($params['__hsx_lead_notice']??null)
                    ?$params['__hsx_lead_notice']:($params['vars']??$params);
                if((int)($vars['__hsx_lead_notice_id']??0)!==$id)continue;
                if(empty($state['legacy_single_channel'])&&((string)($vars['__hsx_lead_notice_channel']??'')!==$channel||(int)($vars['__hsx_lead_notice_attempt']??0)!==(int)$leaf['attempts']))continue;
                $changes=(string)($log['result']??'')!==''?$this->values([],'failed','框架记录发送失败','框架有本次渠道失败记录；请在有权限的日志中核对原因后重试',true)
                    :$this->values([],'framework_logged','框架已记录（送达未核实）','框架有本次渠道处理日志，但没有可核验的终端送达回执',false);
                $changes['framework_log_id']=(int)$log['id'];$this->updateChannel($site,$id,$channel,$changes,(int)$leaf['attempts']);break;
            }
        }
        return$this->publicState(Data::row('audit',$site,$id));
    }

    private function updateChannel(int $site,int $id,string $channel,array $changes,int $attempt): void
    {
        Db::transaction(function()use($site,$id,$channel,$changes,$attempt){
            $row=Data::row('audit',$site,$id,true);$state=$this->state($row);
            if((int)($state['channels'][$channel]['attempts']??0)!==$attempt)return;
            $state['channels'][$channel]=array_replace($state['channels'][$channel],$changes);$this->saveState($row,$state);
        });
    }

    private function saveState(array $row,array $state): void
    {
        Data::query('audit',(int)$row['site_id'])->where('id',$row['id'])->update(['detail_json'=>Data::json($state),'update_time'=>time()]);
    }
    private function pending(): array{return['status'=>'pending','label'=>'待发送','message'=>'本渠道尚未提交','retry_allowed'=>true,'framework_log_id'=>0,'attempts'=>0];}
    private function values(array $state,string $status,string $label,string $message,bool $retry): array{return array_replace($state,['status'=>$status,'label'=>$label,'message'=>$message,'retry_allowed'=>$retry]);}
    private function state(array $row): array
    {
        $state=Data::decode($row['detail_json']);
        if(!isset($state['channels'])){
            // Existing records stay readable, but are never expanded into new channels.
            $state['legacy_single_channel']=true;$leaf=$state;$leaf['selected']=true;
            $state['channels']=['weapp'=>$leaf];
            foreach(['wechat','sms']as$channel)$state['channels'][$channel]=$this->values(['selected'=>false,'attempts'=>0,'framework_log_id'=>0],'disabled','未选择','此历史事件未选择该渠道，不补发',false);
        }
        return$state;
    }

    private function publicState(array $row): array
    {
        $state=$this->state($row);$channels=[];$selected=[];
        foreach(self::CHANNELS as$channel=>$name){$leaf=$state['channels'][$channel];$public=[
            'channel'=>$channel,'name'=>$name,'status'=>$leaf['status'],'label'=>$leaf['label'],'message'=>$leaf['message'],
            'retry_allowed'=>(bool)$leaf['retry_allowed'],'framework_log_id'=>(int)($leaf['framework_log_id']??0),'attempts'=>(int)($leaf['attempts']??0),
            'selected'=>(bool)($leaf['selected']??false)];$channels[]=$public;if($public['selected'])$selected[]=$public;}
        $primary=$selected[0]??$channels[0];$retry=count(array_filter($selected,static fn(array $leaf):bool=>$leaf['retry_allowed']))>0;
        if(count($selected)>1&&count(array_unique(array_column($selected,'status')))>1){
            $primary=['status'=>'partial','label'=>'多渠道通知需核对','message'=>'各渠道独立记录；仅重试明确未提交或失败的渠道，不重复发送结果待核实的渠道',
                'framework_log_id'=>max(array_column($selected,'framework_log_id')),'attempts'=>array_sum(array_column($selected,'attempts'))];
        }
        return array_intersect_key($primary,array_flip(['status','label','message','framework_log_id','attempts']))+[
            'retry_allowed'=>$retry,'phase'=>$state['phase']??'','notice_audit_id'=>(int)$row['id'],'channels'=>$channels];
    }

    /** Database-owned recipient and safe variables; no customer contact or ERP credentials. */
    public static function templateData(int $site,int $taskId,string $phase,int $noticeId=0,string $channel='weapp',int $attempt=0): array
    {
        $task=Data::row('task',$site,$taskId);$merchant=Data::row('merchant',$site,(int)$task['merchant_id']);
        $states=['assigned'=>'待接单','following'=>'跟进中','deal_pending'=>'结果已反馈','completed'=>'已成单',
            'rejected'=>'已退回','reassigned'=>'已转派','closed'=>'已关闭','cancelled'=>'已取消'];
        $entry=NoticePortalEntry::get($site,$taskId);
        $vars=['__weapp_page'=>$entry['miniapp_path'],'__wechat_page'=>$entry['h5_url'],'url'=>$entry['h5_url'],
            '__hsx_lead_notice_id'=>(string)$noticeId,'__hsx_lead_notice_channel'=>$channel,'__hsx_lead_notice_attempt'=>(string)$attempt,
            'task_no'=>mb_substr((string)$task['task_no'],0,32),'task_id'=>(string)$taskId,'status_text'=>$states[$task['status']]??'状态更新',
            'notice_remark'=>$phase==='assigned'?'有新的门店工单，请登录处理':'工单处理状态有更新，请登录核实',
            'occurred_at'=>date('Y-m-d H:i:s'),'demand_summary'=>'请进入商户工作台查看完整需求'];
        $key=NoticeConfigService::PHASES[$phase]??'';
        $channelMappings=match($channel){'weapp'=>NoticeConfigService::weappForSite($site),'wechat'=>NoticeConfigService::wechatForSite($site),default=>[]};
        $mapping=$channelMappings[$key]['content']??[];
        foreach($mapping as$field){$variable=trim((string)$field[1],'{}');$keyword=(string)$field[2];if(!isset($vars[$variable]))continue;
            if(str_starts_with($keyword,'thing'))$vars[$variable]=mb_substr($vars[$variable],0,20);
            elseif(str_starts_with($keyword,'phrase'))$vars[$variable]=mb_substr($vars[$variable],0,5);
            elseif(str_starts_with($keyword,'character_string'))$vars[$variable]=mb_substr($vars[$variable],0,32);
            elseif(str_starts_with($keyword,'date'))$vars[$variable]=date('Y-m-d');}
        return['to'=>['member_id'=>(int)$merchant['member_id']],'vars'=>$vars];
    }
}

