<?php
declare(strict_types=1);

namespace addon\phone_shop\app\adminapi\controller\goods;

use addon\phone_shop\app\service\admin\goods\MemoryService;
use core\base\BaseAdminController;

/**
 * 内存规格控制器
 */
class Memory extends BaseAdminController
{
    /**
     * @var MemoryService
     */
    protected $service;

    public function initialize()
    {
        parent::initialize();
        $this->service = new MemoryService();
    }

    /**
     * 获取内存规格列表
     */
    public function list()
    {
       
        $where = $this->request->params([]);
     
        return success($this->service->getPage($where));
    }

    public function lists()
    {
       
      
        $where = $this->request->params([
            ['id', ''],
        ]);
       
        return success($this->service->getList($where));
    }

    /**
     * 根据分组ID获取内存规格列表
     * @param int $group_id
     */
    public function getListByGroupId($group_id)
    {
        return success($this->service->getListByGroupId((int)$group_id));
    }

    /**
     * 获取内存规格详情
     * @param int $id
     */
    public function detail($id)
    {
        return success($this->service->getInfo((int)$id));
    }

    /**
     * 添加内存规格
     */
    public function add()
    {
        $data = $this->request->params([
            ['group_id', 0],
            ['spec_name', ''],
            ['sort', 0],
        ]);
        // $this->validate($data, 'addon\phone_shop\app\validate\goods\Memory.add');
        return success($this->service->add($data));
    }

    /**
     * 编辑内存规格
     * @param int $id
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ['group_id', 0],
            ['spec_name', ''],
            ['sort', 0],
        ]);
        $data['spec_id'] = (int)$id;
       //  $this->validate($data, 'addon\phone_shop\app\validate\goods\Memory.edit');
        return success($this->service->edit($data));
    }

    /**
     * 删除内存规格
     * @param int $id
     */
    public function delete($id)
    {
        return success($this->service->delete((int)$id));
    }
}
