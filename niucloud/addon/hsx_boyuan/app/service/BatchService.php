<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\Problem;
use think\facade\Db;

final class BatchService
{
    public function states(int $site,array $input): array
    {
        $ids=$input['ids']??[];
        if(!is_array($ids) || !$ids || count($ids)>50) throw new Problem('BATCH_INVALID','每次最多查询50笔业务');
        foreach($ids as $id) if(!is_int($id) || $id<=0) throw new Problem('BATCH_INVALID','业务编号无效');
        $c=(new ConfigService())->get($site); $key=$c['profiles'][$c['mode']]['connection_key'];
        if(($input['mode']??'')!==$c['mode'] || ($input['connection_key']??'')!==$key || $key==='') throw new Problem('CONNECTION_CHANGED','目标账套已变化，请返回列表核对');
        return ['items'=>Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$key)->where('mode',$c['mode'])->whereIn('id',$ids)->field('id,business_no,status,reason')->select()->toArray()];
    }
    public static function actionFor(array $task): string
    {
        if(in_array($task['status'],['processing','succeeded','simulated','manual_pending'],true) || $task['kind']==='manual_review') return '';
        if($task['side_effect']!=='none') return $task['request_json']!=='{}'?'query':'';
        return 'retry';
    }
    public function submit(int $site,int $uid,array $input): array
    {
        $ids=$input['ids']??[];
        if(!is_array($ids) || !$ids || count($ids)>50) throw new Problem('BATCH_INVALID','每次请选择1至50笔业务');
        foreach($ids as $id) if(!is_int($id) || $id<=0) throw new Problem('BATCH_INVALID','业务编号无效，请重新选择');
        $mode=(string)($input['mode']??''); $connection=(string)($input['connection_key']??'');
        $batch=(string)($input['request_id']??'');
        if(!preg_match('/^[A-Za-z0-9_]{8,64}$/D',$batch)) throw new Problem('BATCH_INVALID','缺少有效批次编号');
        $results=[];
        foreach(array_unique($ids) as $id) {
            try {
                $results[]=Db::transaction(function() use($site,$uid,$id,$mode,$connection,$batch): array {
                    Db::name('site')->where('site_id',$site)->lock(true)->find();
                    $service=new ConfigService();
                    if(!$service->installed($site)) throw new Problem('ADDON_MISSING','本站未安装ERP及博远插件');
                    $c=$service->get($site); $p=$c['profiles'][$c['mode']];
                    if($mode!==$c['mode'] || $connection==='' || $connection!==$p['connection_key']) throw new Problem('CONNECTION_CHANGED','环境或账套已变化，请刷新后重新选择');
                    if($mode!=='mock' && $p['protocol']==='openapi_v153') throw new Problem('WRITE_UNAVAILABLE','博远单据接口尚未开放，本次未提交业务');
                    if(!$c['enabled'] || $c['paused']) throw new Problem('SYNC_PAUSED','业务投递尚未开启或已暂停，请先检查连接设置');
                    $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->lock(true)->find();
                    if(!$task || $task['mode']!==$mode || $task['connection_key']!==$connection) throw new Problem('TASK_MISSING','业务不属于当前站点及账套');
                    if(!$c['finance_enabled'] && in_array($task['kind'],['financial_item_ensure','settlement_register'],true) && $task['side_effect']==='none') throw new Problem('FINANCE_PAUSED','财务同步未开启，未登记款项');
                    $action=self::actionFor($task);
                    if($action==='') return ['id'=>$id,'business_no'=>$task['business_no'],'status'=>'skipped','message'=>'已完成、处理中或需人工核验，未重复提交'];
                    (new TaskService())->action($site,$id,$uid,$action);
                    TaskService::audit($site,$id,$connection,'batch_submit','queued','批量处理已排队，等待后台核验',$uid,['batch_id'=>$batch,'operation_id'=>$task['operation_id'],'action'=>$action]);
                    return ['id'=>$id,'business_no'=>$task['business_no'],'status'=>'queued','action'=>$action,'message'=>$action==='query'?'已排队查询原操作，不重新建单':'已排队，等待博远确认'];
                });
            } catch(Problem $e) { $results[]=['id'=>$id,'status'=>'blocked','code'=>$e->reason,'message'=>$e->getMessage()]; }
        }
        return ['batch_id'=>$batch,'results'=>$results,'queued'=>count(array_filter($results,fn($r)=>$r['status']==='queued')),'completed'=>false];
    }
}
