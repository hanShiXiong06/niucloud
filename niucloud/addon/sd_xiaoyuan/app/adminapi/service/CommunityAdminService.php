<?php

namespace addon\sd_xiaoyuan\app\adminapi\service;

use addon\sd_xiaoyuan\app\model\Community;
use core\base\BaseAdminService;

/**
 * 树洞管理服务
 */
class CommunityAdminService extends BaseAdminService
{
    /**
     * 获取帖子列表
     */
    public function getList(array $params)
    {
        $where = [['site_id', '=', $this->siteId]];

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['category_id'])) {
            $where[] = ['category_id', '=', $params['category_id']];
        }

        if (!empty($params['keyword'])) {
            $where[] = ['title|content', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new Community();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'] ?? 1, $params['limit'] ?? 10)
            ->order('is_top desc, id desc')
            ->select()
            ->toArray();

        return ['count' => $count, 'list' => $list];
    }

    /**
     * 审核帖子
     */
    public function audit(int $id, int $status)
    {
        $post = (new Community())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->siteId]
        ])->find();

        if (empty($post)) {
            return $this->error('帖子不存在');
        }

        $post->save(['status' => $status, 'update_time' => time()]);
        return $this->success('操作成功');
    }

    /**
     * 设置置顶
     */
    public function setTop(int $id, int $isTop)
    {
        $post = (new Community())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->siteId]
        ])->find();

        if (empty($post)) {
            return $this->error('帖子不存在');
        }

        $post->save(['is_top' => $isTop, 'update_time' => time()]);
        return $this->success('操作成功');
    }

    /**
     * 删除帖子
     */
    public function delete(int $id)
    {
        $post = (new Community())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->siteId]
        ])->find();

        if (empty($post)) {
            return $this->error('帖子不存在');
        }

        $post->delete();
        return $this->success('删除成功');
    }
}
