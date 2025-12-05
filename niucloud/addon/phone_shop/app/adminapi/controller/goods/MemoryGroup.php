<?php
declare(strict_types=1);

namespace addon\phone_shop\app\adminapi\controller\goods;

use addon\phone_shop\app\service\admin\goods\MemoryGroupService;
use core\base\BaseAdminController;

/**
 * 内存规格分组控制器
 */
class MemoryGroup extends BaseAdminController
{
    /**
     * @var MemoryGroupService
     */
    protected $service;

    public function initialize()
    {
        parent::initialize();
        $this->service = new MemoryGroupService();
    }

    /**
     * 获取内存规格分组列表
     */
    public function lists()
    {
       
        $where = $this->request->params([]);
        return success($this->service->getPage($where));
    }

    /**
     * 获取内存规格分组详情
     * @param int $id
     */
    public function detail($id)
    {
        return success($this->service->getInfo((int)$id));
    }

    /**
     * 添加内存规格分组
     */
    public function add()
    {
        $data = $this->request->params([
            ['group_name', ''],
            ['sort', 0],
            ['memory_ids', ''],
        ]);
       // $this->validate($data, 'addon\phone_shop\app\validate\goods\MemoryGroup.add');
        return success($this->service->add($data));
    }

    /**
     * 编辑内存规格分组
     * @param int $id
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ['group_name', ''],
            ['sort', 0],
            ['memory_ids', ''],
        ]);
        $data['group_id'] = (int)$id;
        
        //$this->validate($data, 'addon\phone_shop\app\validate\goods\MemoryGroup.edit');

        return success($this->service->edit( $data));
    }

    /**
     * 删除内存规格分组
     * @param int $id
     */
    public function delete($id)
    {
        return success($this->service->delete((int)$id));
    }
}
