<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\Confession as ConfessionModel;
use core\base\BaseAdminController;

/**
 * 表白墙管理控制器
 */
class Confession extends BaseAdminController
{
    /**
     * 表白列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', ''],
            ['school_id', ''],
            ['keyword', '']
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['school_id'])) {
            $where[] = ['school_id', '=', $params['school_id']];
        }

        if (!empty($params['keyword'])) {
            $where[] = ['content|target_name', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new ConfessionModel();
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
     * 审核表白
     */
    public function audit()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status', 1);

        $confession = (new ConfessionModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($confession)) {
            return $this->error('表白不存在');
        }

        $confession->save(['status' => $status, 'update_time' => time()]);
        return success('操作成功');
    }

    /**
     * 置顶/取消置顶
     */
    public function setTop()
    {
        $id = $this->request->param('id');
        $is_top = $this->request->param('is_top', 0);

        $confession = (new ConfessionModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($confession)) {
            return $this->error('表白不存在');
        }

        $confession->save(['is_top' => $is_top, 'update_time' => time()]);
        return success('操作成功');
    }

    /**
     * 删除表白
     */
    public function delete()
    {
        $id = $this->request->param('id');

        $confession = (new ConfessionModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($confession)) {
            return $this->error('表白不存在');
        }

        $confession->delete();
        return success('删除成功');
    }
}
