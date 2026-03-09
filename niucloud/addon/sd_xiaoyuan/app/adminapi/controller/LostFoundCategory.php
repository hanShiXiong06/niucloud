<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\LostFoundCategory as CategoryModel;
use core\base\BaseAdminController;

/**
 * 失物招领分类管理控制器
 */
class LostFoundCategory extends BaseAdminController
{
    /**
     * 分类列表
     */
    public function lists()
    {
        $where = [['site_id', '=', $this->request->siteId()]];

        $model = new CategoryModel();
        $list = $model->where($where)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();

        return success($list);
    }

    /**
     * 添加分类
     */
    public function add()
    {
        $data = $this->request->params([
            ['name', ''],
            ['icon', ''],
            ['sort', 0],
            ['status', 1]
        ]);

        if (empty($data['name'])) {
            return $this->error('请输入分类名称');
        }

        CategoryModel::create([
            'site_id' => $this->request->siteId(),
            'name' => $data['name'],
            'icon' => $data['icon'],
            'sort' => $data['sort'],
            'status' => $data['status'],
            'create_time' => time()
        ]);

        return success('添加成功');
    }

    /**
     * 编辑分类
     */
    public function edit()
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['name', ''],
            ['icon', ''],
            ['sort', 0],
            ['status', 1]
        ]);

        $category = (new CategoryModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($category)) {
            return $this->error('分类不存在');
        }

        $category->save($data);
        return success('保存成功');
    }

    /**
     * 删除分类
     */
    public function delete()
    {
        $id = $this->request->param('id', 0);

        $category = (new CategoryModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($category)) {
            return $this->error('分类不存在');
        }

        $category->delete();
        return success('删除成功');
    }
}
