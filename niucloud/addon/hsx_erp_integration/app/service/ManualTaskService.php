<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\contract\ManualDocumentAdapterInterface;
use addon\hsx_erp_integration\app\model\{ErpIntegrationSyncTask as Task,ErpIntegrationConnection as Connection};
use addon\hsx_erp_integration\app\support\{BridgeTarget,ConnectionFields,ManualReceipt};
use think\facade\{Db,Log};

final class ManualTaskService
{
    /** A short local transaction only: the adapter links/reads the original local vendor task.
     * Sending the document remains the vendor's independently authorized, explicit original-order action.
     */
    public function run(int $site,int $taskId,int $uid,bool $readOnly): array
    {
        if ($site<1 || $taskId<1 || $uid<1) throw new \RuntimeException('请重新登录并打开本站原单');
        return Db::transaction(function() use($site,$taskId,$uid,$readOnly) {
            $task=Task::where('site_id',$site)->where('id',$taskId)->lock(true)->find();
            if (!$task || !$task->fact_key || !$task->target_key) throw new \RuntimeException('不是人工同步任务');
            $conn=Connection::where('id',$task->connection_id)->where('site_id',$site)->lock(true)->find();
            if (!$conn || $conn->sync_mode!=='manual' || !hash_equals((string)$task->target_key,BridgeTarget::key($conn->getData())))
                throw new \RuntimeException('目标账套已变化，请保留并查看原回执，不可重新发单');
            $prior=ConnectionFields::values($task->receipt_json);
            if (!$readOnly && ($conn->status!=='enabled' || !self::canPrepare($task->toArray())))
                throw new \RuntimeException('当前只能核对原任务，不能再次提交');
            if (in_array($task->error_code,['BRIDGE_SOURCE_REVIEW','BRIDGE_SOURCE_INVALIDATED'],true)) throw new \RuntimeException('原业务单需人工核对，请查看异常原因');
            $driver=AdapterRegistry::forSite($site)->getAdapter($conn->adapter_code);
            if (!$driver instanceof ManualDocumentAdapterInterface) throw new \RuntimeException('此适配器未提供原单关联能力');
            $cfg=ConnectionFields::values($conn->config);
            // No secret is needed to link/read a vendor task. The vendor owns the credentials for the eventual submission.
            $ctx=array_intersect_key($cfg,array_flip(['mode','account_code','app_id','api_url','gateway_path','manual_scope']))+
                ['site_id'=>$site,'connection_id'=>(int)$conn->id,'task_id'=>$taskId,'operator_uid'=>$uid,'vendor_task_id'=>(int)($prior['vendor_task_id']??0)];
            $result=$driver->originalDocument(ConnectionFields::values($task->payload),$ctx,$readOnly);
            if (($result['persist']??true)===false) throw new \RuntimeException((string)($result['message']??'暂时无法读取原单，已保留原回执'));
            $receipt=ManualReceipt::normalize($result,$prior,in_array($task->business_type,['payment','receipt'],true));
            $task->status=ManualReceipt::state($receipt['status']);
            $task->receipt_json=json_encode($receipt, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $task->external_doc_no=$receipt['external_no'] ?: null;
            $task->error_message=$receipt['message'] ?: null;
            $task->updated_at=time();
            if ($task->status==='succeeded' && !$task->succeeded_at) $task->succeeded_at=time();
            $task->save();
            Log::info("ERP 原单关联 site={$site} task={$taskId} kind={$task->business_type} uid={$uid} read_only=".(int)$readOnly." status={$task->status}");
            return $receipt;
        });
    }

    public static function canPrepare(array $row): bool
    {
        return !empty($row['fact_key']) && empty($row['external_doc_no']) && empty($row['external_doc_id'])
            && !in_array($row['error_code']??'',['BRIDGE_SOURCE_REVIEW','BRIDGE_SOURCE_INVALIDATED'],true)
            && in_array($row['status']??'', ['awaiting_confirmation','waiting_source','blocked'],true)
            && empty(ConnectionFields::values($row['receipt_json']??[])['vendor_task_id']);
    }
}
