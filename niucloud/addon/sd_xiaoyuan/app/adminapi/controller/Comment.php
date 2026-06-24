<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\CommunityComment;
use addon\sd_xiaoyuan\app\model\ConfessionComment;
use addon\sd_xiaoyuan\app\model\Community;
use addon\sd_xiaoyuan\app\model\Confession;
use app\model\member\Member;
use core\base\BaseAdminController;

/**
 * 评论管理控制器
 */
class Comment extends BaseAdminController
{
    /**
     * 树洞评论列表
     */
    public function communityList()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['post_id', 0],
            ['status', ''],
            ['keyword', '']
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['post_id'] > 0) {
            $where[] = ['post_id', '=', $params['post_id']];
        }

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['keyword'])) {
            $where[] = ['content', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new CommunityComment();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'], $params['limit'])
            ->order('id desc')
            ->select()
            ->toArray();

        // 附加帖子标题和用户信息
        if (!empty($list)) {
            $post_ids = array_unique(array_filter(array_column($list, 'post_id')));
            $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
            
            $posts = [];
            $members = [];
            
            if (!empty($post_ids)) {
                $post_list = (new Community())->where([['id', 'in', $post_ids]])->column('title', 'id');
                $posts = $post_list;
            }
            
            if (!empty($member_ids)) {
                $member_list = (new Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg,mobile', 'member_id');
                $members = $member_list;
            }
            
            foreach ($list as &$item) {
                $item['post_title'] = $posts[$item['post_id']] ?? '';
                $item['nickname'] = $members[$item['member_id']]['nickname'] ?? '';
                $item['headimg'] = $members[$item['member_id']]['headimg'] ?? '';
                $item['mobile'] = $members[$item['member_id']]['mobile'] ?? '';
            }
            unset($item);
        }

        return success(['count' => $count, 'list' => $list]);
    }

    /**
     * 表白墙评论列表
     */
    public function confessionList()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['confession_id', 0],
            ['status', ''],
            ['keyword', '']
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['confession_id'] > 0) {
            $where[] = ['confession_id', '=', $params['confession_id']];
        }

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['keyword'])) {
            $where[] = ['content', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new ConfessionComment();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'], $params['limit'])
            ->order('id desc')
            ->select()
            ->toArray();

        // 附加表白标题和用户信息
        if (!empty($list)) {
            $confession_ids = array_unique(array_filter(array_column($list, 'confession_id')));
            $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
            
            $confessions = [];
            $members = [];
            
            if (!empty($confession_ids)) {
                $confession_list = (new Confession())->where([['id', 'in', $confession_ids]])->column('content', 'id');
                $confessions = $confession_list;
            }
            
            if (!empty($member_ids)) {
                $member_list = (new Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg,mobile', 'member_id');
                $members = $member_list;
            }
            
            foreach ($list as &$item) {
                $item['confession_content'] = $confessions[$item['confession_id']] ?? '';
                $item['nickname'] = $members[$item['member_id']]['nickname'] ?? '';
                $item['headimg'] = $members[$item['member_id']]['headimg'] ?? '';
                $item['mobile'] = $members[$item['member_id']]['mobile'] ?? '';
            }
            unset($item);
        }

        return success(['count' => $count, 'list' => $list]);
    }

    /**
     * 审核评论
     */
    public function audit()
    {
        $id = $this->request->param('id', 0);
        $type = $this->request->param('type', 'community');
        $status = $this->request->param('status', 1);

        if ($type === 'community') {
            $comment = (new CommunityComment())->where([
                ['id', '=', $id],
                ['site_id', '=', $this->request->siteId()]
            ])->find();
        } else {
            $comment = (new ConfessionComment())->where([
                ['id', '=', $id],
                ['site_id', '=', $this->request->siteId()]
            ])->find();
        }

        if (empty($comment)) {
            return $this->error('评论不存在');
        }

        $comment->save(['status' => $status]);
        return success('操作成功');
    }

    /**
     * 删除评论
     */
    public function delete()
    {
        $id = $this->request->param('id', 0);
        $type = $this->request->param('type', 'community');

        if ($type === 'community') {
            $comment = (new CommunityComment())->where([
                ['id', '=', $id],
                ['site_id', '=', $this->request->siteId()]
            ])->find();
        } else {
            $comment = (new ConfessionComment())->where([
                ['id', '=', $id],
                ['site_id', '=', $this->request->siteId()]
            ])->find();
        }

        if (empty($comment)) {
            return $this->error('评论不存在');
        }

        $comment->delete();
        return success('删除成功');
    }
}
