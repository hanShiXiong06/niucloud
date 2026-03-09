<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\GuestbookService;
use core\base\BaseApiController;

/**
 * 留言板控制器
 */
class Guestbook extends BaseApiController
{
    /**
     * 获取留言列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);

        $service = new GuestbookService();
        $data = $service->getList($params);
        return success($data);
    }

    /**
     * 获取留言详情
     */
    public function detail($id)
    {
        $service = new GuestbookService();
        $data = $service->getDetail($id);
        return success($data);
    }

    /**
     * 发布留言
     */
    public function publish()
    {
        $data = $this->request->params([
            ['content', ''],
            ['images', []],
            ['is_anonymous', 0]
        ]);

        $service = new GuestbookService();
        $data = $service->publish($data);
        return success($data);
    }

    /**
     * 删除留言
     */
    public function delete()
    {
        $id = $this->request->param('id');
        $service = new GuestbookService();
        $service->delete($id);
        return success('删除成功');
    }

    /**
     * 获取我的留言
     */
    public function my()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);

        $service = new GuestbookService();
        $data = $service->getMyList($params);
        return success($data);
    }

    /**
     * 获取留言统计
     */
    public function stats()
    {
        $service = new GuestbookService();
        $data = $service->getStats();
        return success($data);
    }
}
