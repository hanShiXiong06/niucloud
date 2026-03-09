<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\CommentService;
use core\base\BaseApiController;

/**
 * 评论接口控制器
 */
class Comment extends BaseApiController
{
    /**
     * 获取树洞帖子评论
     */
    public function communityList()
    {
        $postId = $this->request->param('post_id', 0);
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 20);

        $service = new CommentService();
        $result = $service->getCommunityComments($postId, $page, $limit);

        return success($result);
    }

    /**
     * 发布树洞评论
     */
    public function addCommunity()
    {
        $data = $this->request->params([
            ['post_id', 0],
            ['parent_id', 0],
            ['reply_member_id', 0],
            ['content', '']
        ]);
        if (empty($data['content'])) {
            return $this->error('请输入评论内容');
        }

        $service = new CommentService();
        $data = $service->addCommunityComment($data);
        return success($data);
    }

    /**
     * 获取表白墙评论
     */
    public function confessionList()
    {
        $confessionId = $this->request->param('confession_id', 0);
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 20);

        $service = new CommentService();
        $result = $service->getConfessionComments($confessionId, $page, $limit);

        return success($result);
    }

    /**
     * 发布表白墙评论
     */
    public function addConfession()
    {
        $data = $this->request->params([
            ['confession_id', 0],
            ['parent_id', 0],
            ['content', ''],
            ['is_anonymous', 0]
        ]);

        if (empty($data['content'])) {
            return $this->error('请输入评论内容');
        }

        $service = new CommentService();
        $data = $service->addConfessionComment($data);
        return success($data);
    }

    /**
     * 删除评论
     */
    public function delete()
    {
        $id = $this->request->param('id', 0);
        $type = $this->request->param('type', 'community');

        $service = new CommentService();
        $service->deleteComment($id, $type);
        return success();
    }
}
