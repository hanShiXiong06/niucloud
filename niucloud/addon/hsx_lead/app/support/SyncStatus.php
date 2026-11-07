<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

/** Read-only sync projections. A cached UI or a stale pointer is not a running job. */
final class SyncStatus
{
    public static function progress(array $run,bool $published=false): array
    {
        $status=(string)($run['status']??'unknown');
        $count=max(0,(int)($run['record_count']??0));
        $cursor=Data::decode($run['cursor_json']??null);
        $raw=$cursor['source_total']??null;
        $total=(is_int($raw)||is_string($raw))&&preg_match('/^(?:0|[1-9][0-9]{0,7})$/D',(string)$raw)?(int)$raw:null;
        $complete=$status==='completed';
        // Stored adapter diagnostics stay in the task record, never in a user-facing summary.
        $reason=(string)($run['error_code']??'');
        $message=match($status) {
            'pending'=>'已开始全量同步，正在准备读取库存',
            'running'=>'已读取 '.$count.' 条库存，正在继续同步'.($total!==null?'（共 '.$total.' 条）':''),
            'completed'=>'完整同步已完成，共读取 '.$count.' 条库存；可售情况请向门店核对',
            'cancelled'=>$reason==='generation_expired'?ProviderPublicMessage::forReason($reason):'本次同步已取消，未替换已有完整快照',
            'failed'=>ProviderPublicMessage::forReason($reason!==''?$reason:'sync_failed'),
            default=>'同步状态待核对，请刷新或重新同步',
        };
        return ['run_id'=>(int)($run['id']??0),'status'=>$status,'page_count'=>(int)($run['page_count']??0),
            'record_count'=>$count,'total_records'=>$total,'progress_known'=>$complete||$total!==null,
            'progress_percent'=>$complete?100:($total!==null&&$total>0?min(99,(int)floor($count/$total*100)):null),
            'complete'=>$complete,'published'=>$published,'message'=>$message];
    }

    /** Never mutates historical records. Joins are scoped to the exact connection and merchant. */
    public static function connection(int $site,array $connection): array
    {
        $status=(string)($connection['last_sync_status']??'idle');
        $result=['last_sync_status'=>$status,'last_sync_message'=>ProviderPublicMessage::syncStatus($status),
            'active_run_id'=>(int)($connection['active_run_id']??0),'sync_progress'=>null,'requires_sync_restart'=>false];
        if ($site<=0||(int)($connection['id']??0)<=0||(int)($connection['merchant_id']??0)<=0) return $result;
        $query=static fn()=>Data::query('sync_run',$site)->where('connection_id',(int)$connection['id'])->where('merchant_id',(int)$connection['merchant_id']);
        $activeId=$result['active_run_id'];$revision=(int)($connection['config_version']??0);$run=null;
        if ($activeId>0) $run=$query()->where('id',$activeId)->where('config_version',$revision)->find();
        $busy=in_array($status,['pending','running'],true);
        if (!$run && $busy) $run=$query()->where('config_version',$revision)->order('id desc')->find();
        if (!$run && $status==='completed' && (int)($connection['published_run_id']??0)>0) {
            $run=$query()->where('id',(int)$connection['published_run_id'])->where('status','completed')->find();
        }
        if (!$run && in_array($status,['failed','cancelled'],true)) {
            $run=$query()->where('config_version',$revision)->where('status',$status)->order('id desc')->find();
        }
        if (!$run) {
            if ($activeId>0 || $busy || $status==='completed') {
                $result['active_run_id']=0;$result['last_sync_status']='interrupted';$result['requires_sync_restart']=true;
                $result['last_sync_message']='未找到可继续的同步任务，请重新同步；已有完整快照仍保留';
            }
            return $result;
        }
        $progress=self::progress($run,(int)($connection['published_run_id']??0)===(int)$run['id']);
        $result['sync_progress']=$progress;
        if (in_array($run['status'],['pending','running'],true)) {
            if ($activeId!==(int)$run['id']) {
                $result['active_run_id']=0;$result['last_sync_status']='interrupted';$result['requires_sync_restart']=true;
                $result['last_sync_message']='本次同步已中断，请重新同步；尚未发布部分库存';
            } else {
                $result['last_sync_status']=$run['status'];$result['last_sync_message']=$progress['message'];
            }
        } else {
            $result['active_run_id']=0;$result['last_sync_status']=$run['status'];$result['last_sync_message']=$progress['message'];
            if ($run['status']==='completed'&&!$progress['published']) {
                $result['last_sync_status']='interrupted';$result['requires_sync_restart']=true;
                $result['last_sync_message']='本次读取已结束但未发布，请重新同步；已有完整快照仍保留';
            }
        }
        return $result;
    }
}
