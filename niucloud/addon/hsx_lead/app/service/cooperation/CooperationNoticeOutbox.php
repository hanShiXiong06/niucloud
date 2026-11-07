<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\job\DispatchCooperationNotice;
use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use think\facade\{Db,Log};

/** Durable business intents; NiuCloud owns the queue, templates and senders. */
final class CooperationNoticeOutbox
{
    public const TYPE='collaboration_notice_outbox';
    private const CURSOR='HSX_LEAD_NOTICE_RECOVERY_CURSOR';
    public static function record(int $site,int $leadId,string $action): int
    {
        if(!in_array($action,['assigned','accept','accepted','reject','withdraw','cancel','deal','no_deal','paid'],true))return 0;
        return Db::transaction(function()use($site,$leadId,$action):int {
            $lead=Store::row('collaboration',$site,$leadId,true);
            $key=hash('sha256',implode('|',['notice-outbox',$site,$leadId,$lead['assignment_version'],$lead['status'],$action]));
            $old=Data::query('audit',$site)->where('dedupe_key',$key)->find();if($old)return(int)$old['id'];
            return(int)Data::query('audit',$site)->insertGetId(['site_id'=>$site,'entity_type'=>self::TYPE,'entity_id'=>$leadId,
                'action'=>'notice_pending','dedupe_key'=>$key,'detail_json'=>Data::json(['action'=>$action,'assignment_version'=>(int)$lead['assignment_version'],
                    'lead_status'=>$lead['status'],'status'=>'pending','attempts'=>0,'message'=>'业务已保存，通知等待处理']),
                'create_time'=>time(),'update_time'=>time()]);
        });
    }
    public static function enqueue(array $event): void
    {
        $site=(int)($event['site_id']??0);$id=self::record($site,(int)($event['lead_id']??0),(string)($event['action']??''));
        if(!$id)return;
        // A PaySuccess listener may still be inside the framework's outer
        // transaction. Never send externally before that transaction commits.
        // Dispatch falls back to synchronous execution when the framework
        // queue is disabled. Keep the durable intent for scheduled recovery
        // instead of waiting on external channels in a customer's request.
        if(Db::connect()->getPdo()->inTransaction()||!filter_var(env('queue.state',false),FILTER_VALIDATE_BOOLEAN))return;
        try{DispatchCooperationNotice::dispatch([['site_id'=>$site,'id'=>$id]]);}
        catch(\Throwable $error){Log::warning('协作通知已保留待补偿事件：site='.$site.', id='.$id.', type='.get_class($error));}
    }
    public static function process(int $site,int $id,?callable $dispatcher=null): bool
    {
        // Dispatchers must run after the caller's business transaction commits,
        // even when an integration directly invokes this worker synchronously.
        if(Db::connect()->getPdo()->inTransaction())return false;
        $event=Db::transaction(function()use($site,$id):?array {
            $row=Data::row('audit',$site,$id,true);if($row['entity_type']!==self::TYPE||$row['action']!=='notice_pending')return null;
            $state=Data::decode($row['detail_json']);
            if(($state['status']??'')==='processing'&&(int)$row['update_time']>time()-180)return null;
            $lead=Store::row('collaboration',$site,(int)$row['entity_id']);
            if((int)$lead['assignment_version']!==(int)$state['assignment_version']||$lead['status']!==$state['lead_status']){
                self::finish($site,$id,$state,'obsolete','工单已更新，不发送过时提醒');return null;
            }
            if((int)($state['attempts']??0)>=10){self::finish($site,$id,$state,'failed','通知处理多次未完成，请平台核对框架通知日志');return null;}
            $state['status']='processing';$state['attempts']=(int)($state['attempts']??0)+1;$state['message']='通知任务处理中';
            Data::query('audit',$site)->where('id',$id)->update(['detail_json'=>Data::json($state),'update_time'=>time()]);
            return ['site_id'=>$site,'lead_id'=>(int)$row['entity_id'],'action'=>$state['action'],
                'assignment_version'=>(int)$state['assignment_version'],'lead_status'=>$state['lead_status'],'outbox_attempt'=>$state['attempts']];
        });
        if(!$event)return false;
        try{
            // Each recipient/channel has its own persisted claim. Replaying
            // this event only fills missing claims; it never blindly resends an
            // already-submitted or uncertain SMS/WeChat notification.
            (new CooperationNoticeService($dispatcher))->changed($event);
            return Db::transaction(function()use($site,$id,$event):bool{
                $row=Data::row('audit',$site,$id,true);$state=Data::decode($row['detail_json']);
                if(!self::ownsAttempt($row,$state,$event))return false;
                $lead=Store::row('collaboration',$site,(int)$row['entity_id']);
                if((int)$lead['assignment_version']!==(int)$event['assignment_version']||$lead['status']!==$event['lead_status']){
                    self::finish($site,$id,$state,'obsolete','工单已更新，不再继续发送过时提醒');return false;
                }
                self::finish($site,$id,$state,'processed','事件已处理，各渠道结果以通知日志为准');return true;
            });
        }catch(\Throwable $error){
            Db::transaction(function()use($site,$id,$event):void{$row=Data::row('audit',$site,$id,true);$state=Data::decode($row['detail_json']);if(!self::ownsAttempt($row,$state,$event))return;$state['status']='pending';$state['message']='业务已保存，通知等待再次处理';Data::query('audit',$site)->where('id',$id)->update(['detail_json'=>Data::json($state),'update_time'=>time()]);});
            Log::warning('协作通知事件待补偿：site='.$site.', id='.$id.', type='.get_class($error));return false;
        }
    }
    private static function ownsAttempt(array $row,array $state,array $event): bool
    {
        return $row['action']==='notice_pending'&&($state['status']??'')==='processing'&&(int)($state['attempts']??0)===(int)$event['outbox_attempt'];
    }
    private static function finish(int $site,int $id,array $state,string $status,string $message): void
    {
        $state['status']=$status;$state['message']=$message;
        Data::query('audit',$site)->where('id',$id)->update(['action'=>'notice_'.$status,'detail_json'=>Data::json($state),'update_time'=>time()]);
    }
    /** A rotating site cursor plus existing (site_id,action,id) index bounds recovery. */
    public static function recover(int $siteLimit=5,int $eventLimit=20,?callable $dispatcher=null): array
    {
        $db=Db::connect();$scope=(string)$db->getConfig('database').':'.(string)$db->getConfig('prefix');
        $lock='lead_notice_recover:'.substr(hash('sha256',$scope),0,35);$owned=$db->query('SELECT GET_LOCK(:key_name,0) AS acquired',['key_name'=>$lock],true);
        if((int)($owned[0]['acquired']??0)!==1)return['examined'=>0,'processed'=>0,'busy'=>true];
        try{
            $config=new CoreConfigService();$cursor=(int)($config->getConfigValue(0,self::CURSOR)['site_id']??0);
            $sites=Db::name('site')->where('status',1)->where('site_id','>',$cursor)->order('site_id')->limit(max(1,min(20,$siteLimit)))->column('site_id');
            if(!$sites)$sites=Db::name('site')->where('status',1)->order('site_id')->limit(max(1,min(20,$siteLimit)))->column('site_id');
            $result=['examined'=>0,'processed'=>0,'busy'=>false];
            foreach($sites as$site){$site=(int)$site;
                $ids=Data::query('audit',$site)->where('action','notice_pending')->where('entity_type',self::TYPE)->where('update_time','<=',time()-180)->order('id')->limit(max(1,min(50,$eventLimit)))->column('id');
                foreach($ids as$id){$result['examined']++;if(self::process($site,(int)$id,$dispatcher))$result['processed']++;}
            }
            if($sites)$config->setConfig(0,self::CURSOR,['site_id'=>(int)end($sites)]);
            return$result;
        }finally{try{$db->query('SELECT RELEASE_LOCK(:key_name) AS released',['key_name'=>$lock],true);}catch(\Throwable $ignored){}}
    }
}
