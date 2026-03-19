<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\TaskService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 任务悬赏管理控制器
 */
class Task extends BaseAdminController
{
    /**
     * 获取任务列表
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['task_type', ''],
            ['status', ''],
            ['school_id', ''],
            ['campus', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new TaskService())->getPage($data);
        return success($list);
    }

    /**
     * 获取任务详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new TaskService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 获取任务类型列表
     */
    public function typeList(): Response
    {
        $list = (new TaskService())->getTypeList();
        return success($list);
    }

    /**
     * 获取任务状态列表
     */
    public function statusList(): Response
    {
        $list = (new TaskService())->getStatusList();
        return success($list);
    }
}
