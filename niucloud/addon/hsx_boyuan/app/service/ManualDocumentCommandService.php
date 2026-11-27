<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\gateway\{Transport,WholesaleGateway};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

/** Only an authorized submit/verify POST invokes this. No polling side effects, sweep, sleep, retry or financial dispatch. */
final class ManualDocumentCommandService
{
    public static function eligible(array $c): bool
    {
        $p=$c['profiles']['test']??[];
        return isset($c['revision']) && ($c['mode']??'')==='test' && ($c['purchase_mode']??'')==='manual'
            && !empty($c['enabled']) && !empty($c['purchase_enabled']) && empty($c['paused'])
            && ($p['protocol']??'')==='openapi_v153' && ($p['endpoint']??'')===PaymentSyncService::UAT_ENDPOINT
            && ($p['gateway_path']??'')==='/gateway.do' && !empty($p['connection_key']) && !empty($p['contract_confirmed']) && !empty($p['probe_at']);
    }
    public static function sameConfig(array $c,array $expected): bool
    {
        return self::eligible($c) && $c['mode']===$expected['mode'] && $c['profiles']['test']['connection_key']===$expected['connection_key']
            && (int)$c['revision']===$expected['config_revision'];
    }
    public static function canProcess(array $c,array $task): bool
    {
        if(!self::eligible($c)) return false;
        return self::claimable($c,$task,['mode'=>$c['mode'],'connection_key'=>$c['profiles']['test']['connection_key'],
            'config_revision'=>(int)$c['revision'],'revision'=>(int)$task['revision'],'read'=>(bool)$task['query_only']]);
    }
    /** Called under the same row lock used by the queue; never lease another revision or flip a query into a create. */
    public static function claimable(array $c,array $t,array $e): bool
    {
        $r=Contract::decode($t['request_json']);
        if(($t['kind']??'')==='purchase') {
            if(($r['schema']??null)!==PurchaseSyncService::SCHEMA || ($r['stage']??null)!=='frozen' || (!$e['read'] && ($r['auto_verify']??null)!==false)) return false;
        } elseif(($t['kind']??'')!==WholesaleSyncService::KIND || ($r['schema']??null)!==WholesaleGateway::SCHEMA) return false;
        return self::sameConfig($c,$e) && $t['mode']===$e['mode'] && $t['connection_key']===$e['connection_key'] && (int)$t['revision']===$e['revision']
            && $t['status']===($e['read']?'confirming':'pending') && (bool)$t['query_only']===$e['read']
            && ($e['read'] || ($t['side_effect']==='none' && (int)$t['attempts']===0 && empty($t['resolution'])))
            && empty($r['auto_verify']);
    }
    public function run(int $site,int $id,int $uid,string $kind,bool $read,array $input,bool $current=false,?Transport $transport=null): array
    {
        if($site<1 || $uid<1 || $id<1 || !in_array($kind,['purchase',WholesaleSyncService::KIND],true)) throw new Problem('MANUAL_TASK_INVALID','请选择本站采购或同行出库原单');
        $purchase=$kind==='purchase'; $service=$purchase?new PurchaseSyncService():new WholesaleSyncService();
        $config=new ConfigService(); $c=$config->get($site,false); $inline=self::eligible($c);
        $expected=['mode'=>$input['mode']??'','connection_key'=>$input['connection_key']??'','revision'=>$input['revision']??-1,'config_revision'=>(int)$c['revision'],'read'=>$read];
        $trace=$config->trace($site,$c['profiles'][$c['mode']],$c['mode'],['task_id'=>$id,'kind'=>$kind,'operator_uid'=>$uid]);
        $q=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->where('mode',$expected['mode'])->where('connection_key',$expected['connection_key'])->where('kind',$kind);
        try {
            if(!is_int($expected['revision']) || $expected['revision']<1) throw new Problem('TASK_STALE','请刷新后核对当前任务版本');
            if($current) {
                if(!$inline || !$config->installed($site)) throw new Problem('MANUAL_TASK_UNAVAILABLE','当前连接未启用 UAT 人工确认，或已暂停；没有处理此单');
                $row=(clone $q)->find();
                if(!$row || !self::claimable($c,$row,$expected)) throw new Problem('TASK_STALE','此单已变化或不再等待本次操作；请刷新，不要重复提交');
            } else {
                if($purchase) $result=$read?$service->queueVerify($site,$id,$uid,$input,$inline):$service->submit($site,$id,$uid,$input,$inline);
                else $result=$read?$service->verify($site,$id,$uid,$input):$service->submit($site,$id,$uid,$input);
                $trace->local('queued');
                if(!$inline) return $result;
                $expected['revision']++;
            }
        } catch(\Throwable $e) {
            $trace->local('blocked_before_send',$e instanceof Problem?$e->reason:'LOCAL_ERROR');
            throw $e;
        }
        $service->process($site,$id,$transport,$expected);
        $latest=$config->get($site,false);
        if(($latest['mode']??'')!==$expected['mode'] || ($latest['profiles'][$latest['mode']]['connection_key']??'')!==$expected['connection_key'])
            throw new Problem('CONNECTION_CHANGED','账套已切换，请在原账套查看结果；不要重复发单');
        if($purchase) return $service->view($site,$id);
        $row=(clone $q)->field('id,status,reason,external_no,revision')->find();
        if(!$row) throw new Problem('TASK_STALE','当前连接未找到原任务，请刷新核对');
        return $row;
    }
}
