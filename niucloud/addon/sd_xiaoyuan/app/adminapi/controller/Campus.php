<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\CampusService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 校区管理控制器
 */
class Campus extends BaseAdminController
{
    /**
     * 获取校区列表
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['school_id', ''],
            ['status', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new CampusService())->getPage($data);
        return success($list);
    }

    /**
     * 获取校区详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new CampusService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 添加校区
     */
    public function add(): Response
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['name', ''],
            ['address', ''],
            ['lng', ''],
            ['lat', ''],
            ['status', 1],
            ['sort', 0],
        ]);
        
        if (empty($data['name'])) {
            return fail('请输入校区名称');
        }
        
        if (empty($data['school_id'])) {
            return fail('请选择学校');
        }
        
        $data['site_id'] = $this->request->siteId();
        
        (new CampusService())->add($data);
        return success('添加成功');
    }

    /**
     * 编辑校区
     */
    public function edit(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['name', ''],
            ['address', ''],
            ['lng', ''],
            ['lat', ''],
            ['status', 1],
            ['sort', 0],
        ]);
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        if (empty($data['name'])) {
            return fail('请输入校区名称');
        }
        
        (new CampusService())->edit((int)$id, $data);
        return success('修改成功');
    }

    /**
     * 删除校区
     */
    public function delete(): Response
    {
        $id = $this->request->param('id', 0);
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new CampusService())->delete((int)$id);
        return success('删除成功');
    }

    /**
     * 设置状态
     */
    public function setStatus(): Response
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new CampusService())->setStatus((int)$id, (int)$status);
        return success('操作成功');
    }

    /**
     * 获取校区列表(不分页)
     */
    public function all(): Response
    {
        $schoolId = $this->request->param('school_id', 0);
        $where = [];
        
        if ($schoolId > 0) {
            $where['school_id'] = $schoolId;
        }
        
        $list = (new CampusService())->getList($where);
        return success($list);
    }
}
