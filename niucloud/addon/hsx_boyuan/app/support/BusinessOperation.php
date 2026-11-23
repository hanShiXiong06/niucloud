<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

use think\facade\{Db,Event,Log};

/** Optional internal activity projection. Never publish payloads, operator notes, names of payees, or credentials. */
final class BusinessOperation
{
    private const EVENT='HsxRecycleBusinessOperationRecorded';
    private const ACTIONS=[
        'sale_receipt_capture'=>'准备原销售收款凭证','sale_receipt_submit'=>'提交原销售收款凭证','sale_receipt_result'=>'处理销售收款回执',
        'purchase_capture'=>'接收采购入库事实','original_purchase_prepared'=>'准备采购核对草稿','purchase_draft'=>'保存采购资料选择',
        'purchase_submit'=>'提交采购同步','purchase_verify'=>'提交采购核对请求','purchase_created'=>'处理创建采购的回执',
        'purchase_verified'=>'核对博远原采购','purchase_process'=>'检查采购同步异常','purchase_invalidate'=>'原采购资料变化',
        'purchase_automatic'=>'检查采购自动匹配条件','payment_capture'=>'准备已登记的付款凭证','payment_draft'=>'保存付款资料选择',
        'payment_submit'=>'提交已有付款凭证','payment_result'=>'处理付款登记回执','payment_process'=>'检查付款同步异常',
        'inventory_queue'=>'提交在库核对','inventory_result'=>'核对在库串号','inventory_query'=>'检查在库核对异常',
        'wholesale_capture'=>'接收销售出库事实','wholesale_submit'=>'提交销售同步','wholesale_verify'=>'提交销售核对',
        'wholesale_readback'=>'核对博远销售结果','wholesale_create'=>'处理销售出库回执','wholesale_source_changed'=>'销售原资料变化',
        'wholesale_preflight'=>'核对出库条件','batch_submit'=>'提交批量同步','receive'=>'接收同步任务','result'=>'处理任务结果',
        'process'=>'处理同步异常','send'=>'发起同步任务','record_manual'=>'记录人工处理结果','retry'=>'重试原任务',
        'config'=>'保存博远连接','mapping'=>'维护资料对应','probe'=>'核验博远账套','interface_trace'=>'变更接口诊断设置',
        'purchase_release'=>'变更采购同步设置','voucher_release'=>'变更凭证同步设置','reference_start'=>'申请同步基础资料',
        'reference_retry'=>'重试基础资料同步','reference_page'=>'处理基础资料同步分页','notice'=>'处理业务提醒',
        'query'=>'申请核对已发出的原单','capture'=>'处理业务事实',
    ];
    private const OUTCOMES=['failed'=>'failed','blocked'=>'failed','needs_review'=>'review','partial'=>'review','attention'=>'review',
        'simulated'=>'skipped','skipped'=>'skipped','queued'=>'pending','started'=>'pending','processing'=>'pending','confirming'=>'pending',
        'pending'=>'pending','retry'=>'pending','awaiting_confirmation'=>'pending','succeeded'=>'success','verified'=>'success','completed'=>'success'];

    public static function audit(int $site,int $task,string $connection,string $action,string $outcome,int $uid,array $detail): void
    {
        if(!isset(self::ACTIONS[$action]))return; // includes pure opens; viewing a list is not a new action
        self::safe($site,$task,$connection,function(array $r)use($task,$action,$outcome,$uid,$detail){
            // A persisted transition is identified by its task/version and operation, not by its wall time.
            $stable=$task>0?[$task,(int)$r['revision'],(int)$r['attempts'],$action,$outcome,$detail['operation_id']??'',$detail['code']??'']:
                (!empty($detail['sync_id'])?['sync',$detail['sync_id'],$detail['cursor']??'',$detail['attempt']??0,$action,$outcome]:['op',bin2hex(random_bytes(16))]);
            return ['event_id'=>'action:'.hash('sha256',Contract::json($stable)),'action'=>$action,'label'=>self::ACTIONS[$action],
                'result'=>self::OUTCOMES[$outcome]??'recorded','operator_id'=>$uid,
                'external_no'=>self::number((string)($detail['external_no']??''))];
        });
    }
    public static function wire(int $site,string $connection,array $row): void
    {
        $call=(string)($row['call_id']??'');$stage=$row['stage']??'';
        if(!preg_match('/^[a-f0-9]{16}$/D',$call)||!in_array($stage,['request','result'],true))return;
        self::safe($site,(int)($row['task_id']??0),$connection,static function()use($row,$call,$stage){
            $at=(int)($row[$stage==='request'?'sent_ms':'finished_ms']??0);
            $result='pending';$label='发起博远接口请求';
            if($stage==='result'){
                $received=($row['delivery']??'')==='response_received';
                $result=!$received?(($row['delivery']??'')==='not_sent'?'failed':'review'):
                    (($row['gateway_reply']??'')==='reported_error'||(int)($row['http_status']??0)>=400?'failed':'received');
                $label=$received?'收到博远接口响应':($result==='failed'?'博远请求未发出':'未收到可确认的博远响应');
            }
            return ['event_id'=>'wire:'.$call.':'.$stage,'action'=>'interface_'.$stage,'label'=>$label,'result'=>$result,'call_id'=>$call,
                'business_no'=>self::number((string)($row['business_no']??'')),'operator_id'=>(int)($row['operator_uid']??0),'occurred_at'=>$at>0?intdiv($at,1000):time()];
        });
    }
    private static function number(string $value): string
    {
        return preg_match('/^[a-zA-Z0-9_.:-]{1,80}$/D',$value) && !preg_match('/^1[3-9]\d{9}$/D',$value)?$value:'';
    }
    private static function safe(int $site,int $task,string $connection,callable $build): void
    {
        if($site<=0)return;
        try{
            if(!Event::hasListener(self::EVENT))return;
            $row=[];
            if($task>0){
                $row=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'id'=>$task,'connection_key'=>$connection])
                    ->field('id,kind,business_no,revision,attempts,snapshot_json')->find();
                if(!$row)return;
            }
            $event=$build($row)+['site_id'=>$site,'source'=>'boyuan','task_id'=>$task,'occurred_at'=>time(),
                'business_no'=>($row['kind']??'')==='party_resolve'?'':self::number((string)($row['business_no']??'')),'device_ids'=>[]];
            $uid=(int)($event['operator_id']??0);
            if($uid>0){
                // Do not impersonate the operator who originally created a task when a worker sends it.
                try{
                    $name=Db::name('sys_user')->alias('u')->join(Db::name('sys_user_role')->getTable().' r','r.uid=u.uid')
                        ->where(['r.site_id'=>$site,'r.uid'=>$uid])->value('u.real_name');
                    if(is_string($name) && !preg_match('/^1[3-9]\d{9}$/D',$name))$event['operator_name']=mb_substr($name,0,50);
                }catch(\Throwable $e){self::warn($site,$task,$e);}
            }
            $snap=json_decode((string)($row['snapshot_json']??''),true);
            $assets=[];
            foreach($snap['lines']??[] as $line)if(is_array($line)&&preg_match('/^asset:([1-9][0-9]*)$/D',(string)($line['device_ref']??''),$m))$assets[(int)$m[1]]=true;
            $resolver=\addon\hsx_erp\app\support\ErpRecycleOperationAudit::class;
            if($assets && is_callable([$resolver,'deviceIdsForAssets'])){
                try{$event['device_ids']=$resolver::deviceIdsForAssets($site,array_keys($assets));}
                catch(\Throwable $e){self::warn($site,$task,$e);} // still preserve an unassigned site operation
            }
            Event::trigger(self::EVENT,$event);
        }catch(\Throwable $e){self::warn($site,$task,$e);}
    }
    private static function warn(int $site,int $task,\Throwable $e): void
    {
        try{Log::warning('hsx_boyuan recycle operation projection unavailable',['site_id'=>$site,'task_id'=>$task,'type'=>get_class($e)]);}catch(\Throwable $ignored){}
    }
}
