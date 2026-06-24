<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\Community as CommunityModel;
use core\base\BaseAdminController;

/**
 * 树洞管理控制器
 */
class Community extends BaseAdminController
{
    /**
     * 帖子列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', ''],
            ['school_id', ''],
            ['category_id', 0],
            ['keyword', '']
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['school_id'])) {
            $where[] = ['school_id', '=', $params['school_id']];
        }

        if ($params['category_id'] > 0) {
            $where[] = ['category_id', '=', $params['category_id']];
        }

        if (!empty($params['keyword'])) {
            $where[] = ['title|content', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new CommunityModel();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'], $params['limit'])
            ->order('id desc')
            ->select()
            ->toArray();

        // 关联学校名称
        if (!empty($list)) {
            $school_ids = array_unique(array_filter(array_column($list, 'school_id')));
            $schools = [];
            if (!empty($school_ids)) {
                $schools = (new \addon\sd_xiaoyuan\app\model\School())->where([['id', 'in', $school_ids]])->column('name', 'id');
            }
            foreach ($list as &$item) {
                $item['school_name'] = $schools[$item['school_id'] ?? 0] ?? '';
            }
            unset($item);
        }

        return success(['count' => $count, 'list' => $list]);
    }

    /**
     * 帖子详情
     */
    public function info($id)
    {
        $info = (new CommunityModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($info)) {
            return $this->error('帖子不存在');
        }

        return success($info->toArray());
    }

    /**
     * 审核帖子
     */
    public function audit()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status', 1);

        $post = (new CommunityModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($post)) {
            return $this->error('帖子不存在');
        }

        $post->save(['status' => $status, 'update_time' => time()]);
        return success('操作成功');
    }

    /**
     * 置顶/取消置顶
     */
    public function setTop()
    {
        $id = $this->request->param('id');
        $is_top = $this->request->param('is_top', 0);

        $post = (new CommunityModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($post)) {
            return $this->error('帖子不存在');
        }

        $post->save(['is_top' => $is_top, 'update_time' => time()]);
        return success('操作成功');
    }

    /**
     * 删除帖子
     */
    public function delete()
    {
        $id = $this->request->param('id');

        $post = (new CommunityModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($post)) {
            return $this->error('帖子不存在');
        }

        $post->delete();
        return success('删除成功');
    }
}
