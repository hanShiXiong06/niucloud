<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\adminapi\controller;

use addon\hsx_erp_integration\app\model\{ErpIntegrationSyncTask as TaskModel, ErpIntegrationConnection};
use addon\hsx_erp_integration\app\service\SyncTaskService;
use addon\hsx_erp_integration\app\service\ManualTaskService;
use addon\hsx_erp_integration\app\support\{ConnectionFields, TaskView};

final class Task
{
    private function scoped() { return TaskModel::where('site_id', (int)request()->siteId()); }
    public function lists()
    {
        $q = $this->scoped();
        foreach (['business_type','connection_id'] as $key) if ($v = input($key)) $q->where($key, $v);
        if ($v = input('status/s', '')) $q->whereRaw('(' . TaskView::STATUS_SQL . ') = ?', [$v]);
        if ($v = trim(input('source_no/s', ''))) $q->whereLike('source_no|external_doc_no', '%' . $v . '%');
        if ($v = input('start_date/s', '')) $q->where('created_at', '>=', strtotime($v));
        if ($v = input('end_date/s', '')) $q->where('created_at', '<=', strtotime($v . ' 23:59:59'));
        $count = (clone $q)->count();
        $list = $q->withoutField('payload,receipt_json')->order('id desc')->page(max(1,input('page/d',1)), min(100,max(1,input('limit/d',20))))->select()->toArray();
        foreach ($list as &$row) $row['status'] = TaskView::displayStatus($row);
        return success(['list' => $list, 'count' => $count]);
    }

    public function info(int $id)
    {
        $row = $this->scoped()->where('id', $id)->find();
        if (!$row) return fail('任务不存在');
        $data = (new \addon\hsx_erp_integration\app\service\LocalOriginalReceiptView())->read((int)request()->siteId(),$row->toArray());
        $data['status'] = TaskView::displayStatus($data);
        $data['payload'] = TaskView::snapshot(ConnectionFields::values($row->payload));
        $conn = ErpIntegrationConnection::where('site_id', (int)request()->siteId())->where('id', $row->connection_id)->field('id,adapter_code,adapter_name,status')->find();
        $data['connection'] = $conn ? $conn->toArray() : null;
        $data['can_retry'] = $conn && $conn->status === 'enabled' && TaskView::canRetry($data);
        $data['manual'] = !empty($data['fact_key']);
        $data['receipt'] = ConnectionFields::values($data['receipt_json'] ?? null);
        unset($data['receipt_json']);
        $data['can_prepare'] = $conn && $conn->status === 'enabled' && ManualTaskService::canPrepare($data+['receipt_json'=>$data['receipt']]);
        return success($data);
    }

    private function original(int $id,bool $readOnly)
    {
        try { return success((new ManualTaskService())->run((int)request()->siteId(),$id,(int)request()->uid(),$readOnly)); }
        catch (\Throwable $e) {
            // Do not expose SQL, connection or provider errors to the browser.
            if (get_class($e)===\RuntimeException::class) return fail($e->getMessage());
            \think\facade\Log::warning('ERP 原单回执暂不可读',['task_id'=>$id,'type'=>get_class($e)]);
            return fail('暂时无法读取原单回执，请刷新核对，不要重发');
        }
    }
    public function prepare(int $id) {
        $intent=input('intent/s','');
        if($intent==='') return $this->original($id,false);
        if(!in_array($intent,['capture_purchase_source','capture_payment_source','capture_receipt_source'],true)) return fail('不支持的原单操作');
        try {
            $recovery=new \addon\hsx_erp_integration\app\service\OriginalCaptureRecovery();
            if($intent==='capture_receipt_source') return success($recovery->recoverSaleReceipt((int)request()->siteId(),$id,(int)request()->uid(),input('sale_id/d',0)));
            return success($recovery->recover((int)request()->siteId(),$id,(int)request()->uid(),$intent==='capture_payment_source'?'payment':'purchase',input('purchase_id/d',0)));
        }
        catch(\Throwable $e) {
            if(get_class($e)===\RuntimeException::class || $e instanceof \DomainException || $e instanceof \InvalidArgumentException) return fail($e->getMessage());
            \think\facade\Log::warning('集成中心原采购本地关联未完成',['site_id'=>(int)request()->siteId(),'purchase_id'=>$id,'error_type'=>get_class($e)]);
            return fail('未能关联原单，请刷新后核对；没有重开业务单或收付款');
        }
    }
    public function review(int $id) { return $this->original($id,true); }

    public function retry(int $id)
    {
        $task = $this->scoped()->where('id', $id)->find();
        if (!$task || !TaskView::canRetry($task->toArray())) return fail('只有发送前缺少资料映射的失败任务可以重试；其他情况请先核对外部原单');
        try { (new SyncTaskService())->retryTask($id); }
        catch (\RuntimeException $e) { return fail($e->getMessage()); }
        $updated = $this->scoped()->where('id', $id)->field('id,status,external_doc_no,external_doc_id,error_code')->find()->toArray();
        $updated['status'] = TaskView::displayStatus($updated);
        return success($updated);
    }

    public function statistics()
    {
        $rows = $this->scoped()->whereBetween('created_at', [strtotime('today'), strtotime('tomorrow') - 1])
            ->fieldRaw(TaskView::STATUS_SQL . ' AS display_status, COUNT(*) AS n')->group('display_status')->select()->toArray();
        $today = ['total' => 0,'success' => 0,'failed' => 0,'pending' => 0,'unverified' => 0];
        foreach ($rows as $row) {
            $n = (int)$row['n']; $today['total'] += $n;
            $key = ['succeeded' => 'success', 'failed' => 'failed', 'blocked'=>'failed', 'pending' => 'pending','processing' => 'pending','awaiting_confirmation'=>'pending','linked'=>'pending','waiting_source'=>'pending','unverified' => 'unverified'][$row['display_status']] ?? null;
            if ($key) $today[$key] += $n;
        }
        return success(['today' => $today]);
    }
}
