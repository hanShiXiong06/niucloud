<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\adminapi\controller;

use addon\hsx_erp_integration\app\model\{ErpIntegrationFailure, ErpIntegrationSyncTask};

final class Failure
{
    private function scoped() { return ErpIntegrationFailure::where('site_id', (int)request()->siteId()); }

    public function lists()
    {
        $q = $this->scoped();
        $resolved = input('resolved', '');
        if (in_array((string)$resolved, ['0','1'], true)) $q->where('resolved', (int)$resolved);
        foreach (['failure_type','business_type','connection_id'] as $key) if ($v = input($key)) $q->where($key, $v);
        if ($v = trim(input('source_no/s', ''))) $q->whereLike('source_no','%' . $v . '%');
        $count = (clone $q)->count();
        $list = $q->withoutField('error_detail,resolution_note')->order('id desc')->page(max(1,input('page/d',1)), min(100,max(1,input('limit/d',20))))->select()->toArray();
        return success(['list' => $list, 'count' => $count]);
    }

    public function info(int $id)
    {
        $row = $this->scoped()->where('id', $id)->find();
        if (!$row) return fail('记录不存在');
        $data = $row->toArray();
        $task = ErpIntegrationSyncTask::where('site_id', (int)request()->siteId())->where('id', $row->task_id)
            ->field('id,status,source_no,external_doc_no,external_doc_id,error_code')->find();
        $data['task'] = $task ? $task->toArray() : null;
        if ($task) $data['task']['status'] = \addon\hsx_erp_integration\app\support\TaskView::displayStatus($data['task']);
        return success($data);
    }

    /** 只标记异常工单，不改单据状态，不向 ERP 发送请求。 */
    public function resolve(int $id)
    {
        $note = trim(input('note/s', ''));
        if ($note === '' || mb_strlen($note) > 500) return fail('请填写 1–500 字的处理说明');
        $n = $this->scoped()->where('id', $id)->where('resolved', 0)->update([
            'resolved' => 1, 'resolved_at' => time(), 'resolved_by' => (int)request()->uid(), 'resolution_note' => $note,
        ]);
        return $n ? success() : fail('记录已处理或不存在，请刷新');
    }

    public function batchDel()
    {
        $ids = array_values(array_filter(array_map('intval', input('ids/a', []))));
        if (!$ids || count($ids) > 100) return fail('请勾选 1–100 条已处理的记录');
        return success(['count' => $this->scoped()->whereIn('id', $ids)->where('resolved', 1)->delete()]);
    }
}
