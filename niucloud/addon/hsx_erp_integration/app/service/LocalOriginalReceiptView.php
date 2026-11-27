<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\contract\LocalOriginalReceiptInterface;
use addon\hsx_erp_integration\app\support\{BridgeFact,BridgeTarget,ConnectionFields,ManualReceipt};
use think\facade\Db;

/** One request's read model. Opening an original ERP order is not a sync or allocation command. */
final class LocalOriginalReceiptView
{
    private array $connections=[];
    private array $registries=[];

    public function read(int $site,array $task): array
    {
        try {
            $uid=(int)request()->uid();
            if($site<=0 || $uid<=0 || (int)request()->siteId()!==$site || (int)($task['site_id']??0)!==$site ||
                !in_array($task['business_type']??'',['purchase','payment','receipt'],true) || empty($task['fact_key']) || empty($task['target_key'])
                || in_array($task['error_code']??'',['BRIDGE_SOURCE_INVALIDATED','BRIDGE_SOURCE_REVIEW'],true)) return $task;
            $event=ConnectionFields::values($task['payload']??null);
            if(($event['site_id']??null)!==$site || empty($task['origin_event_id']) || $task['origin_event_id']!==($event['event_id']??null)
                || BridgeFact::kind($event)!==$task['business_type']) return $task;
            $source=BridgeFact::project($event,$task['business_type']);
            foreach(['source_no','source_type','fact_key'] as $field) if(($task[$field]??null)!==$source[$field]) return $task;
            if((int)($task['source_id']??0)!==$source['source_id'] || !empty($source['error_message'])) return $task;
            $cid=(int)($task['connection_id']??0);
            if(!array_key_exists($cid,$this->connections[$site]??[])) $this->connections[$site][$cid]=Db::name('erp_integration_connection')
                ->where(['site_id'=>$site,'id'=>$cid])->field('id,site_id,sync_mode,adapter_code,config')->find();
            $conn=$this->connections[$site][$cid];
            if(!$conn || $conn['sync_mode']!=='manual' || !hash_equals($task['target_key'],BridgeTarget::key($conn))) return $task;
            $this->registries[$site]??=AdapterRegistry::forSite($site);
            $adapter=$this->registries[$site]->getAdapter($conn['adapter_code']);
            if(!$adapter instanceof LocalOriginalReceiptInterface) return $task;
            $old=ConnectionFields::values($task['receipt_json']??null);
            $ctx=array_intersect_key(ConnectionFields::values($conn['config']),array_flip(['mode','account_code','app_id','api_url','gateway_path']))+
                ['site_id'=>$site,'connection_id'=>$cid,'task_id'=>(int)$task['id'],'operator_uid'=>$uid,'vendor_task_id'=>(int)($old['vendor_task_id']??0)];
            $reply=$adapter->localOriginalReceipt($event,$ctx);
            // Unprepared drafts stay available for the normal explicit association action. A real returned
            // number, however, may already exist through an authorized inline vendor workflow.
            if(($reply['persist']??true)===false || empty($reply['vendor_task_id']) ||
                (empty($old['vendor_task_id']) && empty($reply['external_no']) && ($reply['status']??'')!=='unknown')) return $task;
            $payment=in_array($task['business_type'],['payment','receipt'],true);
            if($payment && !isset($reply['allocation']) && ($old['status']??null)==='confirmed' &&
                (int)$old['vendor_task_id']===(int)$reply['vendor_task_id'] && !empty($reply['external_no']) && ($old['external_no']??null)===$reply['external_no'])
                $reply['allocation']=$old['allocation']??[];
            $receipt=ManualReceipt::normalize($reply,$old,$payment);
            $task['receipt_json']=$receipt;
            $task['external_doc_no']=$receipt['external_no'] ?: null;
            $task['status']=ManualReceipt::state($receipt['status']);
            $task['error_message']=$receipt['message'];
            if($receipt['status']==='confirmed') $task['receipt_adapter']=$conn['adapter_code'];
        } catch(\Throwable $e) {
            // Optional plugin/read access/configuration can be unavailable. Its failure cannot wipe
            // a saved bridge receipt or make the ERP page unusable.
        }
        return $task;
    }
}
