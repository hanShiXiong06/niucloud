<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 规格/成色 管理控制器
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\adminapi\controller\goods;

use addon\phone_shop\app\service\admin\goods\SpecService;
use core\base\BaseAdminController;

class Spec extends BaseAdminController
{
    // ===== 规格分组 =====
    public function groupList()
    {
        $where = $this->request->params([['category_id', 0]]);
        return success((new SpecService())->groupList($where));
    }

    public function groupAdd()
    {
        $data = $this->request->params([['category_id', 0], ['category_ids', ''], ['label', '内存'], ['sort', 0]]);
        return success((new SpecService())->groupAdd($data));
    }

    public function groupEdit(int $id)
    {
        $data = $this->request->params([['category_id', 0], ['category_ids', ''], ['label', '内存'], ['sort', 0]]);
        return success((new SpecService())->groupEdit($id, $data));
    }

    public function groupDel(int $id)
    {
        return success((new SpecService())->groupDel($id));
    }

    // ===== 规格子项 =====
    public function itemAdd()
    {
        $data = $this->request->params([['group_id', 0], ['item_value', ''], ['sort', 0]]);
        return success((new SpecService())->itemAdd($data));
    }

    public function itemEdit(int $id)
    {
        $data = $this->request->params([['item_value', ''], ['sort', 0]]);
        return success((new SpecService())->itemEdit($id, $data));
    }

    public function itemDel(int $id)
    {
        return success((new SpecService())->itemDel($id));
    }

    // ===== 成色等级 =====
    public function gradeList()
    {
        return success((new SpecService())->gradeList());
    }

    public function gradeAdd()
    {
        $data = $this->request->params([
            ['grade_name', ''], ['grade_desc', ''], ['grade_image', ''], ['sort', 0], ['status', 1]
        ]);
        return success((new SpecService())->gradeAdd($data));
    }

    public function gradeEdit(int $id)
    {
        $data = $this->request->params([
            ['grade_name', ''], ['grade_desc', ''], ['grade_image', ''], ['sort', 0], ['status', 1]
        ]);
        return success((new SpecService())->gradeEdit($id, $data));
    }

    public function gradeDel(int $id)
    {
        return success((new SpecService())->gradeDel($id));
    }

    // ===== 建品表单：按分类取规格 + 成色 =====
    public function optionsForCategory()
    {
        $p = $this->request->params([['category_id', 0], ['category_path', []]]);
        return success((new SpecService())->optionsForCategory((int)$p['category_id'], (array)$p['category_path']));
    }
}
