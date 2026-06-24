<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\CommunityService;
use core\base\BaseApiController;

/**
 * 树洞控制器
 */
class Community extends BaseApiController
{
    /**
     * 获取帖子列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['category_id', 0],
            ['sort', 'new'],
            ['keyword', ''],
            ['school_id', 0]
        ]);

        $service = new CommunityService();
        $data = $service->getList($params);
        return success($data);
    }

    /**
     * 获取社区统计
     */
    public function stats()
    {
        $service = new CommunityService();
        $data = $service->getStats();
        return success($data);
    }

    /**
     * 获取帖子详情
     */
    public function detail($id)
    {
        $service = new CommunityService();
        $data = $service->getDetail($id);
        return success($data);
    }

    /**
     * 发布帖子
     */
    public function publish()
    {
        $data = $this->request->params([
            ['category_id', 0],
            ['title', ''],
            ['content', ''],
            ['images', []],
            ['video', ''],
            ['school_id', 0],
            ['campus', '']
        ]);

        $service = new CommunityService();
        $data = $service->publish($data);
        return success($data);
    }

    /**
     * 删除帖子
     */
    public function delete()
    {
        $id = $this->request->param('id');
        $service = new CommunityService();
        $service->delete($id);
        return success('删除成功');
    }

    /**
     * 点赞帖子
     */
    public function like()
    {
        $post_id = $this->request->param('post_id');
        $service = new CommunityService();
        $service->like($post_id);
        return success('操作成功');
    }

    /**
     * 获取我的帖子
     */
    public function myPosts()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $service = new CommunityService();
        $data = $service->getMyList($params);
        return success($data);
    }

    /**
     * 获取树洞分类
     */
    public function categories()
    {
        $service = new CommunityService();
        $data = $service->getCategories();
        return success($data);
    }
}
