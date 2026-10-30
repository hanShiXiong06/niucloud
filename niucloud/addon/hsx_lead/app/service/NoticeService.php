<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use app\job\notice\Notice;
use core\exception\CommonException;
use think\facade\Db;

/**
 * Durable notification event state in the plugin audit table. No extra outbox table.
 * Framework execution/logging is deliberately never called terminal delivery.
 */
final class NoticeService
{
    private ?\Closure $dispatcher;
    public function __construct(?callable $dispatcher=null)
    {
        $this->dispatcher=$dispatcher===null?null:\Closure::fromCallable($dispatcher);
    }

    public function taskNotice(int $site,int $taskId,string $phase,string $eventKey): array
    {
        if (!isset(NoticeConfigService::PHASES[$phase]) || $eventKey==='') throw new CommonException('无效通知事件');
        $dedupe=hash('sha256','hsx_lead|'.$site.'|'.$taskId.'|'.$phase.'|'.$eventKey);
        $row=Db::transaction(function()use($site,$taskId,$phase,$eventKey,$dedupe){
            Data::row('task',$site,$taskId,true);
            $existing=Data::query('audit',$site)->where('dedupe_key',$dedupe)->find();
            if ($existing) return $existing;
            $state=['phase'=>$phase,'notice_key'=>NoticeConfigService::PHASES[$phase],
                'status'=>'pending','label'=>'待发送','message'=>'工单已进入商户工作台待办，通知尚未提交',
                'retry_allowed'=>true,'framework_log_id'=>0,'attempts'=>0,'event_hash'=>hash('sha256',$eventKey)];
            $id=(int)Data::query('audit',$site)->insertGetId(['site_id'=>$site,'entity_type'=>'task_notice','entity_id'=>$taskId,
                'action'=>'notice_event','dedupe_key'=>$dedupe,'detail_json'=>Data::json($state),'create_time'=>time(),'update_time'=>time()]);
            return Data::row('audit',$site,$id);
        });
        $state=Data::decode($row['detail_json']);
        if (($state['status']??'')==='pending') return $this->dispatchAudit($site,(int)$row['id']);
        return $this->refresh($row);
    }

    public function statusForTask(int $site,int $taskId): array
    {
        Data::row('task',$site,$taskId);
        $row=Data::query('audit',$site)->where('entity_type','task_notice')->where('entity_id',$taskId)
            ->where('action','notice_event')->order('id desc')->find();
        if (!$row && Data::query('audit',$site)->where('entity_type','task')->where('entity_id',$taskId)->where('action','extension_failed')->count()) {
            return ['status'=>'extension_failed','label'=>'通知扩展执行失败','message'=>'业务已提交，但通知扩展未完成记录；请管理员核对配置后人工重试',
                'framework_log_id'=>0,'retry_allowed'=>true];
        }
        return $row?$this->refresh($row):[
            'status'=>'not_requested','label'=>'站内待办','message'=>'工单可在商户工作台查看；尚未产生需发送的通知事件',
            'framework_log_id'=>0,'retry_allowed'=>true,
        ];
    }

    public function retryTask(int $site,int $taskId,int $uid): array
    {
        $task=Data::row('task',$site,$taskId);
        $row=Data::query('audit',$site)->where('entity_type','task_notice')->where('entity_id',$taskId)
            ->where('action','notice_event')->order('id desc')->find();
        if (!$row) return $this->taskNotice($site,$taskId,$task['status']==='assigned'?'assigned':'changed','manual-initial');
        $status=$this->refresh($row);
        if (empty($status['retry_allowed'])) throw new CommonException('该通知可能已提交或被框架记录，不能盲目重发，请先核对通知日志');
        Data::audit($site,$uid,0,'task_notice',$taskId,'retry_requested',['notice_audit_id'=>(int)$row['id']]);
        return $this->dispatchAudit($site,(int)$row['id']);
    }

    private function dispatchAudit(int $site,int $auditId): array
    {
        $claim=Db::transaction(function()use($site,$auditId){
            $audit=Data::row('audit',$site,$auditId,true); $state=Data::decode($audit['detail_json']);
            if (empty($state['retry_allowed'])) return null;
            $task=Data::row('task',$site,(int)$audit['entity_id']);
            $merchant=Data::row('merchant',$site,(int)$task['merchant_id']);
            $memberId=(int)$merchant['member_id'];
            if (($state['phase']??'')==='assigned') {
                $lead=Data::row('lead',$site,(int)$task['lead_id']);
                if ($task['status']!=='assigned' || (int)$lead['active_task_id']!==(int)$task['id']) {
                    $this->setState($audit,$state,'obsolete','通知已失效','该派单已处理或转派，不再提醒原接收者',false);
                    return null;
                }
            }
            $member=$memberId>0?Db::name('member')->where('site_id',$site)->where('member_id',$memberId)->where('status',1)->where('is_del',0)->where('delete_time',0)->find():null;
            if ((int)$merchant['status']!==1 || !$member) {
                $this->setState($audit,$state,'recipient_unavailable','接收会员不可用','请核对启用商户和本站绑定会员；站内工单不受影响',true); return null;
            }
            NoticeConfigService::syncSite($site);
            $key=$state['notice_key']; $mapping=NoticeConfigService::weappForSite($site);
            $configured=Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->find()?:[];
            if (!isset($mapping[$key])) {
                $this->setState($audit,$state,'pending_configuration','待配置模板','未配置已核验的小程序公共模板；请先配置，工单仍在商户工作台待办中',true); return null;
            }
            if (empty($configured['weapp_template_id'])) {
                $this->setState($audit,$state,'pending_template','待获取本站模板','请在小程序订阅消息重新获取本站模板，不可使用其他小程序的模板ID',true); return null;
            }
            if (empty($configured['is_weapp'])) {
                $this->setState($audit,$state,'disabled','通知开关关闭','请在牛云小程序订阅消息开启；会员仍需主动订阅授权',true); return null;
            }
            if (empty($member['weapp_openid'])) {
                $this->setState($audit,$state,'pending_recipient','待会员小程序登录','接收会员尚无本站小程序openid；请会员登录小程序并主动订阅，不会静默标记成功',true); return null;
            }
            $definitions=require dirname(__DIR__).'/dict/notice/notice.php';
            $template=array_merge($definitions[$key],[
                'site_id'=>$site,'is_weapp'=>1,'is_wechat'=>0,'is_sms'=>0,
                'weapp_template_id'=>$configured['weapp_template_id'],'weapp'=>$mapping[$key],
            ]);
            $state['member_id']=$memberId;
            $state['log_after_id']=(int)(Db::name('sys_notice_log')->where('site_id',$site)->where('key',$key)->where('member_id',$memberId)->max('id')??0);
            $state['attempts']=(int)($state['attempts']??0)+1;
            $state['attempted_at']=time();
            $this->setState($audit,$state,'dispatching','正在提交通知','已取得本通知事件执行权；不要重复发送',false);
            return [$audit,$state,$template,$task];
        });
        if (!$claim) return $this->refresh(Data::row('audit',$site,$auditId));
        [$audit,$state,$template,$task]=$claim;
        try {
            $payload=['site_id'=>$site,'key'=>$state['notice_key'],'data'=>[
                'task_id'=>(int)$task['id'],'phase'=>$state['phase'],'notice_audit_id'=>$auditId,
            ],'template'=>$template];
            // Explicit site/template: worker behavior never depends on another request's site.
            $result=$this->dispatcher?($this->dispatcher)($payload):Notice::dispatch($payload,is_async:false);
            if ($result===false) $this->setState($audit,$state,'uncertain','通知结果待核实','框架返回未确认结果；请先核对通知日志，不可盲目重发',false);
            else $this->setState($audit,$state,'submitted','已交框架（送达待核实）','通知任务已交牛云，未证明微信接口成功或终端收到；请查看框架通知记录',false);
        } catch(\Throwable $error) {
            // No upstream exception/body is persisted: it may contain recipient PII or credentials.
            $this->setState($audit,$state,'uncertain','通知结果待核实','发送期间出现异常，可能已交渠道；请先核对框架日志，不可盲目重发',false);
        }
        return $this->refresh(Data::row('audit',$site,$auditId));
    }

    private function refresh(array $row): array
    {
        $state=Data::decode($row['detail_json']);
        if (in_array($state['status']??'',['submitted','dispatching','uncertain'],true) && !empty($state['member_id'])) {
            $logs=Db::name('sys_notice_log')->where('site_id',$row['site_id'])->where('key',$state['notice_key'])
                ->where('notice_type','weapp')->where('member_id',$state['member_id'])->where('id','>',(int)($state['log_after_id']??0))
                ->order('id desc')->limit(50)->select()->toArray();
            foreach($logs as $log) {
                $params=Data::decode($log['params']??'');
                if ((int)($params['vars']['__hsx_lead_notice_id']??0)!==(int)$row['id']) continue;
                $state['framework_log_id']=(int)$log['id'];
                if ((string)($log['result']??'')!=='') $this->setState($row,$state,'failed','框架记录发送失败','框架已记录失败；请在有权限的通知日志中核对原因，未证明终端收到',true);
                else $this->setState($row,$state,'framework_logged','框架已记录（送达未核实）','框架有本次处理日志，但现有发送器未给出可核验送达回执；不能当作终端已收到',false);
                break;
            }
        }
        return $this->publicState(Data::row('audit',(int)$row['site_id'],(int)$row['id']));
    }

    private function setState(array $row,array $state,string $status,string $label,string $message,bool $retry): void
    {
        $state=array_replace($state,['status'=>$status,'label'=>$label,'message'=>$message,'retry_allowed'=>$retry]);
        Data::query('audit',(int)$row['site_id'])->where('id',$row['id'])->update(['detail_json'=>Data::json($state),'update_time'=>time()]);
    }

    private function publicState(array $row): array
    {
        $state=Data::decode($row['detail_json']);
        return ['status'=>$state['status']??'pending','label'=>$state['label']??'待发送','message'=>$state['message']??'',
            'framework_log_id'=>(int)($state['framework_log_id']??0),'retry_allowed'=>(bool)($state['retry_allowed']??false),
            'phase'=>$state['phase']??'','notice_audit_id'=>(int)$row['id'],'attempts'=>(int)($state['attempts']??0)];
    }

    /** Called by NoticeData using database-owned task and recipient, never caller-provided PII. */
    public static function templateData(int $site,int $taskId,string $phase,int $noticeId=0): array
    {
        $task=Data::row('task',$site,$taskId); $merchant=Data::row('merchant',$site,(int)$task['merchant_id']);
        $states=['assigned'=>'待接单','following'=>'跟进中','deal_pending'=>'待核验','completed'=>'已核验',
            'rejected'=>'已退回','reassigned'=>'已转派','closed'=>'已关闭','cancelled'=>'已取消'];
        $page='addon/hsx_lead/pages/workbench/index';
        $vars=[
            '__weapp_page'=>$page,'__hsx_lead_notice_id'=>(string)$noticeId,
            'task_no'=>mb_substr((string)$task['task_no'],0,32),'task_id'=>(string)$taskId,
            'status_text'=>$states[$task['status']]??'状态更新',
            'notice_remark'=>$phase==='assigned'?'有新的门店工单，请登录处理':'工单处理状态有更新，请登录核实',
            'occurred_at'=>date('Y-m-d H:i:s'),'demand_summary'=>'请进入商户工作台查看完整需求',
        ];
        $key=NoticeConfigService::PHASES[$phase]??'';
        $mapping=NoticeConfigService::weappForSite($site)[$key]['content']??[];
        foreach($mapping as $field) {
            $variable=trim((string)$field[1],'{}'); $keyword=(string)$field[2];
            if (!isset($vars[$variable])) continue;
            if (str_starts_with($keyword,'thing')) $vars[$variable]=mb_substr($vars[$variable],0,20);
            elseif (str_starts_with($keyword,'phrase')) $vars[$variable]=mb_substr($vars[$variable],0,5);
            elseif (str_starts_with($keyword,'character_string')) $vars[$variable]=mb_substr($vars[$variable],0,32);
            elseif (str_starts_with($keyword,'date')) $vars[$variable]=date('Y-m-d');
        }
        return ['to'=>['member_id'=>(int)$merchant['member_id']],'vars'=>$vars];
    }
}

