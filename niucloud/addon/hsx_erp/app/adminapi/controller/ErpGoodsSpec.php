<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpGoodsSpecService;
use core\base\BaseAdminController;

class ErpGoodsSpec extends BaseAdminController
{
    public function meta()
    {
        return success((new ErpGoodsSpecService())->meta());
    }

    public function saveGroup(int $id = 0)
    {
        $data = $this->request->params([
            ['label', ''],
            ['title_part', 1],
            ['sort', 0],
        ]);
        return success((new ErpGoodsSpecService())->saveGroup($id, $data));
    }

    public function deleteGroup(int $id)
    {
        return success((new ErpGoodsSpecService())->deleteGroup($id));
    }

    public function saveItem(int $id = 0)
    {
        $data = $this->request->params([
            ['group_id', 0],
            ['item_value', ''],
            ['sort', 0],
        ]);
        return success((new ErpGoodsSpecService())->saveItem($id, $data));
    }

    public function deleteItem(int $id)
    {
        return success((new ErpGoodsSpecService())->deleteItem($id));
    }

    public function saveGrade(int $id = 0)
    {
        $data = $this->request->params([
            ['grade_name', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        return success((new ErpGoodsSpecService())->saveGrade($id, $data));
    }

    public function deleteGrade(int $id)
    {
        return success((new ErpGoodsSpecService())->deleteGrade($id));
    }
}
