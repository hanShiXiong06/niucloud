<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\SchoolClassService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 班级管理控制器
 */
class SchoolClass extends BaseAdminController
{
    /**
     * 班级列表(分页)
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['name', ''],
            ['status', ''],
            ['grade', ''],
            ['school_id', 0],
            ['page', 1],
            ['limit', 10],
        ]);

        $list = (new SchoolClassService())->getPage($data);
        return success($list);
    }

    /**
     * 班级列表(不分页)
     */
    public function all(): Response
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['grade', ''],
        ]);

        $list = (new SchoolClassService())->getList($data);
        return success($list);
    }

    /**
     * 班级详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        $info = (new SchoolClassService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 添加班级
     */
    public function add(): Response
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['department_id', 0],
            ['major_id', 0],
            ['name', ''],
            ['code', ''],
            ['grade', ''],
            ['sort', 0],
            ['status', 1],
        ]);

        if (empty($data['name'])) {
            return fail('请填写班级名称');
        }

        $id = (new SchoolClassService())->add($data);
        return success(['id' => $id]);
    }

    /**
     * 编辑班级
     */
    public function edit(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['school_id', 0],
            ['department_id', 0],
            ['major_id', 0],
            ['name', ''],
            ['code', ''],
            ['grade', ''],
            ['sort', 0],
            ['status', 1],
        ]);

        if (empty($id)) {
            return fail('参数错误');
        }

        (new SchoolClassService())->edit((int)$id, $data);
        return success('编辑成功');
    }

    /**
     * 删除班级
     */
    public function del(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        (new SchoolClassService())->del((int)$id);
        return success('删除成功');
    }

    /**
     * 修改班级状态
     */
    public function setStatus(): Response
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);

        if (empty($id)) {
            return fail('参数错误');
        }

        (new SchoolClassService())->setStatus((int)$id, (int)$status);
        return success('操作成功');
    }
}
