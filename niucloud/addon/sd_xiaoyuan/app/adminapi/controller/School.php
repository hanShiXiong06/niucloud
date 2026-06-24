<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\SchoolService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 学校管理控制器
 */
class School extends BaseAdminController
{
    /**
     * 获取学校列表
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['name', ''],
            ['status', ''],
            ['province', ''],
            ['city', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new SchoolService())->getPage($data);
        return success($list);
    }

    /**
     * 获取学校详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new SchoolService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 添加学校
     */
    public function add(): Response
    {
        $data = $this->request->params([
            ['name', ''],
            ['short_name', ''],
            ['logo', '', false],
            ['province', ''],
            ['city', ''],
            ['address', ''],
            ['campus_list', ''],
            ['lng', ''],
            ['lat', ''],
            ['semester_start', null],
            ['semester_end', null],
            ['sections', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        
        if (empty($data['name'])) {
            return fail('请填写学校名称');
        }
        
        $id = (new SchoolService())->add($data);
        return success(['id' => $id]);
    }

    /**
     * 编辑学校
     */
    public function edit(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['name', ''],
            ['short_name', ''],
            ['logo', '', false],
            ['province', ''],
            ['city', ''],
            ['address', ''],
            ['campus_list', ''],
            ['lng', ''],
            ['lat', ''],
            ['semester_start', null],
            ['semester_end', null],
            ['sections', ''],
            ['sort', 0],
            ['status', 1],
        ]);

        if (empty($id)) {
            return fail('参数错误');
        }

        (new SchoolService())->edit((int)$id, $data);
        return success('编辑成功');
    }

    /**
     * 删除学校
     */
    public function del(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new SchoolService())->del((int)$id);
        return success('删除成功');
    }

    /**
     * 修改学校状态
     */
    public function setStatus(): Response
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new SchoolService())->setStatus((int)$id, (int)$status);
        return success('操作成功');
    }

    /**
     * 获取学校列表(不分页)
     */
    public function all(): Response
    {
        $list = (new SchoolService())->getList([]);
        return success($list);
    }
}
