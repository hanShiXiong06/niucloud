<?php

namespace addon\sd_xiaoyuan\app\adminapi\service;

use addon\sd_xiaoyuan\app\model\House;
use core\base\BaseAdminService;

/**
 * 房屋租赁管理服务
 */
class HouseAdminService extends BaseAdminService
{
    /**
     * 获取房源列表
     */
    public function getList(array $params)
    {
        $where = [['site_id', '=', $this->siteId]];

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['house_type'])) {
            $where[] = ['house_type', '=', $params['house_type']];
        }

        if (!empty($params['keyword'])) {
            $where[] = ['title|address', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new House();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'] ?? 1, $params['limit'] ?? 10)
            ->order('id desc')
            ->select()
            ->toArray();

        return ['count' => $count, 'list' => $list];
    }

    /**
     * 获取房源详情
     */
    public function getInfo(int $id)
    {
        $info = (new House())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->siteId]
        ])->find();

        if (empty($info)) {
            return null;
        }

        return $info->toArray();
    }

    /**
     * 审核房源
     */
    public function audit(int $id, int $status)
    {
        $house = (new House())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->siteId]
        ])->find();

        if (empty($house)) {
            return $this->error('房源不存在');
        }

        $house->save(['status' => $status, 'update_time' => time()]);
        return $this->success('操作成功');
    }

    /**
     * 删除房源
     */
    public function delete(int $id)
    {
        $house = (new House())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->siteId]
        ])->find();

        if (empty($house)) {
            return $this->error('房源不存在');
        }

        $house->delete();
        return $this->success('删除成功');
    }

    /**
     * 统计
     */
    public function getStat()
    {
        $model = new House();
        
        return [
            'total' => $model->where('site_id', $this->siteId)->count(),
            'pending' => $model->where([['site_id', '=', $this->siteId], ['status', '=', 0]])->count(),
            'published' => $model->where([['site_id', '=', $this->siteId], ['status', '=', 1]])->count(),
            'rented' => $model->where([['site_id', '=', $this->siteId], ['status', '=', 3]])->count()
        ];
    }
}
