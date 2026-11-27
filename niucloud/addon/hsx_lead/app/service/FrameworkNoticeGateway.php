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
        $result=array_values(array_filter(event('NoticeData',$payload)));
        // Multiple matching hooks are an identity ambiguity, not permission to
        // silently use whichever plugin happened to run first.
        if(count($result)!==1)throw new NoticePreflightException('通知数据钩子未唯一匹配，未提交发送');
        $data=$result[0];
        if(!is_array($data)||!is_array($data['to']??null)||!is_array($data['vars']??null)||empty($data['to']['member_id'])) {
            throw new NoticePreflightException('通知数据钩子未就绪，未提交发送；请核对插件事件加载');
        }
        $source=$payload['data']??[];
        $cooperation=isset($source['collaboration_id']);
        if($cooperation&&!isset($payload['expected_recipient_member_id'],$payload['expected_recipient_merchant_id']))
            throw new NoticePreflightException('协作通知缺少独立接收身份，未提交发送');
        if(isset($payload['expected_recipient_member_id'])){
            $sourceId=$cooperation?($source['notice_audit_id']??0):($source['financial_task_id']??0);
            if((int)$payload['expected_recipient_member_id']<=0||(int)$sourceId<=0
                ||($cooperation&&isset($source['financial_task_id']))
                ||(int)$data['to']['member_id']!==(int)$payload['expected_recipient_member_id']
                ||($data['vars']['__hsx_lead_notice_id']??'')!==(string)$sourceId
                ||($data['vars']['__hsx_lead_notice_attempt']??'')!==(string)($payload['data']['notice_attempt']??'')
                ||($data['vars']['__hsx_lead_notice_channel']??'')!==$channel
                ||(!$cooperation&&($data['vars']['__hsx_lead_notice_lease']??'')!==($source['lease_token']??''))
                ||($cooperation&&((int)$payload['expected_recipient_merchant_id']<=0
                    ||($data['vars']['__hsx_lead_notice_merchant_id']??'')!==(string)$payload['expected_recipient_merchant_id']
                    ||($data['vars']['task_id']??'')!==(string)$source['collaboration_id'])))
                throw new NoticePreflightException('通知钩子接收会员或执行关联不一致，未提交发送');
        }
        $event=['site_id'=>(int)$payload['site_id'],'key'=>$payload['key'],'template'=>$payload['template'],
            'to'=>$data['to'],'vars'=>$data['vars']];
        if(in_array($channel,['weapp','wechat','sms'],true))$event['return_receipt']=true;
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
        \addon\hsx_lead\app\service\notice\NoticeDeliveryWindow::assertOpen((int)$payload['site_id']);
        if($this->sender)return($this->sender)($channel,$event);
        $sender=match($channel){'weapp'=>new \app\listener\notice\Weapp(),'wechat'=>new \app\listener\notice\Wechat(),'sms'=>new \app\listener\notice\Sms()};
        return$sender->handle($event);
    }
}
