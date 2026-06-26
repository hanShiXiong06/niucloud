<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\adminapi\controller;

use addon\recycle_quote_spider\app\service\admin\QuoteCategoryService;
use core\base\BaseAdminController;

class Category extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['parent_id', ''],
            ['is_show', ''],
            ['keyword', ''],
        ]);
        return success((new QuoteCategoryService())->getPage($data));
    }

    public function tree()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['is_show', ''],
            ['keyword', ''],
        ]);
        return success((new QuoteCategoryService())->tree($data));
    }

    public function add()
    {
        $data = $this->request->params([
            ['source_id', 0],
            ['parent_id', 0],
            ['name', ''],
            ['is_show', 1],
            ['is_hot', 0],
            ['sort', 0],
        ]);
        return success('ADD_SUCCESS', ['id' => (new QuoteCategoryService())->add($data)]);
    }

    public function edit(int $id)
    {
        $data = $this->request->params([
            ['name', ''],
            ['is_show', ''],
            ['is_hot', ''],
            ['sort', ''],
        ]);
        (new QuoteCategoryService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    public function del(int $id)
    {
        (new QuoteCategoryService())->delete($id);
        return success('DELETE_SUCCESS');
    }
}
