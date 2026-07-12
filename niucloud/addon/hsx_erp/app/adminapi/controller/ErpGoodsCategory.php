<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpGoodsCategoryService;
use addon\hsx_erp\app\service\admin\ErpCategorySyncService;
use core\base\BaseAdminController;

class ErpGoodsCategory extends BaseAdminController
{
    public function tree()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['category_name', ''],
            ['level', ''],
        ]);
        return success((new ErpGoodsCategoryService())->tree($params));
    }

    public function lists()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['category_name', ''],
            ['level', ''],
        ]);
        return success((new ErpGoodsCategoryService())->lists($params));
    }

    public function info(int $id)
    {
        return success((new ErpGoodsCategoryService())->info($id));
    }

    public function save(int $id = 0)
    {
        $data = $this->request->params([
            ['category_name', ''],
            ['pid', 0],
            ['is_show', 1],
            ['sort', 0],
            ['source_plugin', 'erp'],
            ['source_id', ''],
        ]);
        return success((new ErpGoodsCategoryService())->save($id, $data));
    }

    public function delete(int $id)
    {
        return success((new ErpGoodsCategoryService())->delete($id));
    }

    public function export()
    {
        return success((new ErpGoodsCategoryService())->exportRows());
    }

    public function import()
    {
        $data = $this->request->params([
            ['rows', []],
        ]);
        return success((new ErpGoodsCategoryService())->importRows((array)$data['rows']));
    }

    public function syncStatus()
    {
        return success((new ErpCategorySyncService())->status());
    }

    public function sync()
    {
        $data = $this->request->params([
            ['action', 'reconcile'],
            ['provider', 'phone_shop'],
        ]);
        return success((new ErpCategorySyncService())->sync((string)$data['action'], (string)$data['provider']));
    }
}
