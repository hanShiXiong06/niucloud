<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\stat;

use addon\hsx_recycle\app\service\admin\stat\TaskService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 店员「我的任务」
 */
class Task extends BaseAdminController
{
    /** 我负责的环节 */
    public function myStages(): Response
    {
        return success((new TaskService())->getMyStages());
    }

    /** 我的工单列表 */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['stage', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 15],
        ]);
        return success((new TaskService())->getTaskList($data));
    }

    /** 当前环节可分配员工 */
    public function assignableUsers(): Response
    {
        $stageKey = (string)$this->request->param('stage_key', '');
        return success((new TaskService())->getAssignableUsers($stageKey));
    }

    public function assignmentSettings(): Response
    {
        return success((new TaskService())->assignmentSettings());
    }

    public function saveAssignmentSettings(): Response
    {
        $data = $this->request->params([['defaults', []]]);
        return success((new TaskService())->saveDefaultAssignees((array)$data['defaults']));
    }

    /** 指定或转交责任人 */
    public function assign(): Response
    {
        $data = $this->request->params([
            ['device_id', 0], ['stage_key', ''], ['assignee_uid', 0],
        ]);
        (new TaskService())->assign((int)$data['device_id'], (string)$data['stage_key'], (int)$data['assignee_uid']);
        return success('任务已分配');
    }

    /** 认领 */
    public function claim(): Response
    {
        $data = $this->request->params([
            ['device_id', 0],
            ['stage_key', ''],
        ]);
        (new TaskService())->claim((int)$data['device_id'], (string)$data['stage_key']);
        return success('CLAIM_SUCCESS');
    }

    /** 释放 */
    public function release(): Response
    {
        $data = $this->request->params([
            ['device_id', 0],
            ['stage_key', ''],
        ]);
        (new TaskService())->release((int)$data['device_id'], (string)$data['stage_key']);
        return success('SUCCESS');
    }

    /** 一次性回填当前在途计数（部署后执行一次） */
    public function rebuild(): Response
    {
        return success('REBUILD_SUCCESS', (new TaskService())->rebuildStat());
    }

    /** 经营看板（各环节在途 + 今日数字 + 趋势，只读汇总表） */
    public function board(): Response
    {
        $days = (int)$this->request->param('days', 7);
        return success((new TaskService())->getBoard($days));
    }

    /** 维度分布 TOP（型号/分类/成色/来源，读每日维度汇总表，抗千万级） */
    public function dim(): Response
    {
        $data = $this->request->params([
            ['dim_type', 'model'],
            ['start_time', date('Y-m-d', strtotime('-6 days'))],
            ['end_time', date('Y-m-d')],
            ['limit', 10],
        ]);
        return success((new TaskService())->getDimBreakdown(
            (string)$data['dim_type'],
            (string)$data['start_time'],
            (string)$data['end_time'],
            (int)$data['limit']
        ));
    }
}
