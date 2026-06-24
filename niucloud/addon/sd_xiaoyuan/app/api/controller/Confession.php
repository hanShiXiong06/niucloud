<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\ConfessionService;
use core\base\BaseApiController;

/**
 * 表白墙控制器
 */
class Confession extends BaseApiController
{
    /**
     * 获取表白列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['keyword', ''],
            ['type', ''],
            ['school_id', 0]
        ]);

        $service = new ConfessionService();
        $data = $service->getList($params);
        return success($data);
    }

    /**
     * 获取表白墙统计
     */
    public function stats()
    {
        $service = new ConfessionService();
        $data = $service->getStats();
        return success($data);
    }

    /**
     * 获取表白详情
     */
    public function detail($id)
    {
        $service = new ConfessionService();
        $data = $service->getDetail($id);
        return success($data);
    }

    /**
     * 发布表白
     */
    public function publish()
    {
        $data = $this->request->params([
            ['content', ''],
            ['images', []],
            ['is_anonymous', 0],
            ['target_name', ''],
            ['target_info', ''],
            ['school_id', 0],
            ['campus', '']
        ]);

        $service = new ConfessionService();
        $data = $service->publish($data);
        return success($data);
    }

    /**
     * 删除表白
     */
    public function delete()
    {
        $id = $this->request->param('id');
        $service = new ConfessionService();
        $service->delete($id);
        return success();
    }

    /**
     * 点赞表白
     */
    public function like()
    {
        $confession_id = $this->request->param('confession_id');
        $service = new ConfessionService();
        $service->like($confession_id);
        return success();
    }

    /**
     * 获取我的表白
     */
    public function myList()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $service = new ConfessionService();
        $data = $service->getMyList($params);
        return success($data);
    }
}
