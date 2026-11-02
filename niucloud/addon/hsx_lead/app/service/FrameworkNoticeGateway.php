<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

/** Reuse NoticeData and exactly one official sender; disabled Sms must not abort other channels. */
final class FrameworkNoticeGateway
{
    private ?\Closure $sender;
    public function __construct(?callable $sender=null)
    {
        $this->sender=$sender===null?null:\Closure::fromCallable($sender);
    }

    public function dispatch(array $payload)
    {
        $channel=(string)($payload['channel']??'');
        if(!in_array($channel,['weapp','wechat','sms'],true))throw new NoticePreflightException('通知渠道未接入，未提交发送');
        $result=event('NoticeData',$payload);$data=array_values(array_filter($result))[0]??[];
        if(!is_array($data)||!is_array($data['to']??null)||!is_array($data['vars']??null)||empty($data['to']['member_id'])) {
            throw new NoticePreflightException('通知数据钩子未就绪，未提交发送；请核对插件事件加载');
        }
        $event=['site_id'=>(int)$payload['site_id'],'key'=>$payload['key'],'template'=>$payload['template'],
            'to'=>$data['to'],'vars'=>$data['vars']];
        if($channel==='sms'){
            // The official SMS listener persists the whole event as params, but
            // forwards vars to the SMS provider. Correlation belongs to the log
            // envelope, never to the provider's approved template parameters.
            $dictionary=require dirname(__DIR__).'/dict/notice/sms.php';
            $content=$dictionary[$event['key']]['content']??null;
            if(!is_string($content)||($event['template']['sms']['content']??null)!==$content)
                throw new NoticePreflightException('短信内容与插件通知字典不一致，未提交发送');
            preg_match_all('/\{([a-z][a-z0-9_]*)\}/D',$content,$matches);
            $smsVars=[];
            foreach(array_unique($matches[1])as$variable){
                if(!isset($event['vars'][$variable])||!is_scalar($event['vars'][$variable]))
                    throw new NoticePreflightException('短信模板变量不完整，未提交发送');
                $smsVars[$variable]=(string)$event['vars'][$variable];
            }
            $event['__hsx_lead_notice']=array_intersect_key($event['vars'],array_flip([
                '__hsx_lead_notice_id','__hsx_lead_notice_channel','__hsx_lead_notice_attempt']));
            $event['vars']=$smsVars;
        }
        if($this->sender)return($this->sender)($channel,$event);
        $sender=match($channel){'weapp'=>new \app\listener\notice\Weapp(),'wechat'=>new \app\listener\notice\Wechat(),'sms'=>new \app\listener\notice\Sms()};
        return$sender->handle($event);
    }
}
