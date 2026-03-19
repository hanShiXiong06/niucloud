<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\TaskService;
use core\base\BaseApiController;
use think\Response;

/**
 * 任务悬赏接口
 */
class Task extends BaseApiController
{
    /**
     * 获取任务类型列表
     */
    public function typeList(): Response
    {
        $list = (new TaskService())->getTypeList();
        return success($list);
    }

    /**
     * 获取任务列表
     */
    public function list(): Response
    {
        $data = $this->request->params([
            ['task_type', ''],
            ['status', ''],
            ['school_id', ''],
            ['campus', ''],
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
     * 发布任务
     */
    public function publish(): Response
    {
        $data = $this->request->params([
            ['task_type', ''],
            ['title', ''],
            ['content', ''],
            ['images', ''],
            ['pickup_address', ''],
            ['pickup_lng', ''],
            ['pickup_lat', ''],
            ['delivery_address', ''],
            ['delivery_lng', ''],
            ['delivery_lat', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['express_company', ''],
            ['express_no', ''],
            ['pickup_code', ''],
            ['reward', 0],
            ['tip', 0],
            ['is_urgent', 0],
            ['deadline', 0],
        ]);
        
        if (empty($data['task_type']) || empty($data['title'])) {
            return fail('请填写完整信息');
        }
        
        $member_id = $this->request->memberId();
        $id = (new TaskService())->publish((int)$member_id, $data);
        return success(['id' => $id]);
    }

    /**
     * 支付任务
     */
    public function pay(): Response
    {
        $task_id = $this->request->param('task_id', 0);
        $pay_type = $this->request->param('pay_type', 'wechat');
        
        if (empty($task_id)) {
            return fail('参数错误');
        }
        
        (new TaskService())->pay((int)$task_id, $this->request->memberId(), $pay_type);
        return success('支付成功');
    }

    /**
     * 接单
     */
    public function accept(): Response
    {
        $task_id = $this->request->param('task_id', 0);
        if (empty($task_id)) {
            return fail('参数错误');
        }
        
        (new TaskService())->accept((int)$task_id, $this->request->memberId());
        return success('接单成功');
    }

    /**
     * 开始执行任务
     */
    public function start(): Response
    {
        $task_id = $this->request->param('task_id', 0);
        if (empty($task_id)) {
            return fail('参数错误');
        }
        
        (new TaskService())->start((int)$task_id, $this->request->memberId());
        return success('操作成功');
    }

    /**
     * 提交完成
     */
    public function submitComplete(): Response
    {
        $task_id = $this->request->param('task_id', 0);
        if (empty($task_id)) {
            return fail('参数错误');
        }
        
        (new TaskService())->submitComplete((int)$task_id, $this->request->memberId());
        return success('操作成功');
    }

    /**
     * 确认完成
     */
    public function confirmComplete(): Response
    {
        $task_id = $this->request->param('task_id', 0);
        if (empty($task_id)) {
            return fail('参数错误');
        }
        
        (new TaskService())->confirmComplete((int)$task_id, $this->request->memberId());
        return success('确认成功');
    }

    /**
     * 取消任务
     */
    public function cancel(): Response
    {
        $task_id = $this->request->param('task_id', 0);
        $reason = $this->request->param('reason', '');
        
        if (empty($task_id)) {
            return fail('参数错误');
        }
        
        (new TaskService())->cancel((int)$task_id, $this->request->memberId(), $reason);
        return success('取消成功');
    }

    /**
     * 我发布的任务
     */
    public function myPublish(): Response
    {
        $data = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $service = new TaskService();
        $list = $service->getMyPublishPage($data);
        return success($list);
    }

    /**
     * 我接的任务
     */
    public function myAccept(): Response
    {
        $data = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $service = new TaskService();
        $list = $service->getMyAcceptPage($data);
        return success($list);
    }
}
